<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Delivery;
use App\Models\DeliveryProfile;
use App\Models\DeliveryTracking;
use App\Models\DriverEarning;
use Illuminate\Support\Facades\DB;

class DeliveryService
{
    public function __construct(protected NotificationService $notificationService) {}

    public function assignDriver(Order $order): ?Delivery
    {
        // Find nearest available driver
        $branch = $order->branch ?? $order->business->mainBranch;
        if (!$branch) return null;

        $driver = DeliveryProfile::where('availability_status', 'online')
            ->where('approval_status', 'approved')
            ->whereRaw("(6371 * acos(cos(radians(?)) * cos(radians(current_lat)) * cos(radians(current_lng) - radians(?)) + sin(radians(?)) * sin(radians(current_lat)))) < 15",
                [$branch->latitude, $branch->longitude, $branch->latitude])
            ->selectRaw("*, (6371 * acos(cos(radians(?)) * cos(radians(current_lat)) * cos(radians(current_lng) - radians(?)) + sin(radians(?)) * sin(radians(current_lat)))) as distance",
                [$branch->latitude, $branch->longitude, $branch->latitude])
            ->orderBy('distance')
            ->first();

        if (!$driver) return null;

        return DB::transaction(function () use ($order, $driver, $branch) {
            $delivery = Delivery::create([
                'order_id'           => $order->id,
                'driver_id'          => $driver->id,
                'pickup_address'     => ['address' => $branch->address, 'name' => $order->business->name],
                'pickup_lat'         => $branch->latitude,
                'pickup_lng'         => $branch->longitude,
                'delivery_address'   => $order->delivery_address,
                'delivery_lat'       => $order->delivery_lat,
                'delivery_lng'       => $order->delivery_lng,
                'delivery_fee'       => $order->delivery_fee,
                'driver_earning'     => $order->rider_earning ?? $order->delivery_fee * 0.8,
                'status'             => 'assigned',
                'assigned_at'        => now(),
            ]);

            $driver->update(['availability_status' => 'busy']);
            $this->notificationService->notifyDriverAssigned($delivery);

            return $delivery;
        });
    }

    public function driverAccept(Delivery $delivery, DeliveryProfile $driver): void
    {
        if ($delivery->driver_id !== $driver->id) throw new \Exception('Unauthorized');
        $delivery->update(['status' => 'accepted', 'accepted_at' => now()]);
        $this->notificationService->notifyOrderStatus($delivery->order, 'Driver is on the way to pick up your order');
    }

    public function driverPickup(Delivery $delivery, DeliveryProfile $driver): void
    {
        $delivery->update(['status' => 'picked_up', 'picked_up_at' => now()]);
        $delivery->order->updateStatus('on_the_way');
        $this->notificationService->notifyOrderStatus($delivery->order, 'Your order has been picked up and is on the way');
    }

    public function driverDeliver(Delivery $delivery, DeliveryProfile $driver, ?string $proofPhoto = null): void
    {
        DB::transaction(function () use ($delivery, $driver, $proofPhoto) {
            $delivery->update([
                'status'           => 'delivered',
                'delivered_at'     => now(),
                'proof_of_delivery'=> $proofPhoto,
                'actual_duration'  => $delivery->assigned_at ? now()->diffInMinutes($delivery->assigned_at) : null,
            ]);

            $delivery->order->updateStatus('delivered');
            $driver->increment('completed_deliveries');

            // Pay driver
            $this->payDriver($delivery, $driver);
            $this->notificationService->notifyOrderDelivered($delivery->order);
        });
    }

    public function updateLocation(DeliveryProfile $driver, float $lat, float $lng, ?float $speed = null): void
    {
        $driver->update(['current_lat' => $lat, 'current_lng' => $lng, 'last_location_update' => now()]);

        // Record tracking point for active deliveries
        $activeDelivery = Delivery::where('driver_id', $driver->id)
            ->whereIn('status', ['accepted', 'picked_up', 'on_the_way'])
            ->latest()
            ->first();

        if ($activeDelivery) {
            DeliveryTracking::create([
                'delivery_id' => $activeDelivery->id,
                'driver_id'   => $driver->id,
                'latitude'    => $lat,
                'longitude'   => $lng,
                'speed'       => $speed,
                'recorded_at' => now(),
            ]);

            // Broadcast live location
            broadcast(new \App\Events\DriverLocationUpdated($activeDelivery->id, $lat, $lng));
        }
    }

    public function getTrackingData(Delivery $delivery): array
    {
        return [
            'tracking_code'    => $delivery->tracking_code,
            'status'           => $delivery->status,
            'driver'           => $delivery->driver ? [
                'name'         => $delivery->driver->user->profile?->full_name,
                'phone'        => $delivery->driver->user->phone,
                'vehicle_type' => $delivery->driver->vehicle_type,
                'rating'       => $delivery->driver->rating_avg,
                'current_lat'  => $delivery->driver->current_lat,
                'current_lng'  => $delivery->driver->current_lng,
            ] : null,
            'pickup'           => ['lat' => $delivery->pickup_lat, 'lng' => $delivery->pickup_lng, 'address' => $delivery->pickup_address],
            'destination'      => ['lat' => $delivery->delivery_lat, 'lng' => $delivery->delivery_lng, 'address' => $delivery->delivery_address],
            'eta_minutes'      => $this->estimateEta($delivery),
            'tracking_points'  => $delivery->trackingPoints()->latest()->take(50)->get(['latitude', 'longitude', 'recorded_at']),
            'status_timeline'  => $delivery->statusLogs()->orderBy('created_at')->get(),
        ];
    }

    private function payDriver(Delivery $delivery, DeliveryProfile $driver): void
    {
        $earning = $delivery->driver_earning;
        $driver->increment('total_earnings', $earning);
        $driver->increment('balance', $earning);

        DriverEarning::create([
            'driver_id'   => $driver->id,
            'delivery_id' => $delivery->id,
            'amount'      => $earning,
            'earning_type'=> 'delivery',
            'description' => "Earning for delivery #{$delivery->tracking_code}",
            'earned_at'   => now(),
        ]);

        $driver->update(['availability_status' => 'online']);
    }

    private function estimateEta(Delivery $delivery): ?int
    {
        if (!$delivery->driver) return null;
        $lat1 = $delivery->driver->current_lat ?? $delivery->pickup_lat;
        $lng1 = $delivery->driver->current_lng ?? $delivery->pickup_lng;
        $lat2 = $delivery->delivery_lat;
        $lng2 = $delivery->delivery_lng;
        if (!$lat1 || !$lat2) return null;
        $distance = $this->haversineDistance($lat1, $lng1, $lat2, $lng2);
        return (int) ceil(($distance / 30) * 60); // avg 30km/h
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
