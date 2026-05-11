<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Business;
use App\Models\Order;
use App\Models\Delivery;
use App\Models\DeliveryProfile;
use App\Models\Report;
use App\Models\WithdrawalRequest;
use App\Models\PlatformSetting;
use App\Models\BusinessVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    // ─── DASHBOARD ───────────────────────────────────────────────────────

    public function dashboard()
    {
        $stats = [
            'total_users'      => User::count(),
            'active_users'     => User::where('status', 'active')->count(),
            'new_users_today'  => User::whereDate('created_at', today())->count(),
            'total_businesses' => Business::count(),
            'verified_businesses' => Business::where('verification_status', 'verified')->count(),
            'pending_verification' => BusinessVerification::where('status', 'pending')->count(),
            'total_orders'     => Order::count(),
            'orders_today'     => Order::whereDate('created_at', today())->count(),
            'revenue_today'    => Order::whereDate('created_at', today())->where('payment_status', 'paid')->sum('total'),
            'revenue_month'    => Order::whereMonth('created_at', now()->month)->where('payment_status', 'paid')->sum('total'),
            'active_deliveries'=> Delivery::whereIn('status', ['assigned', 'accepted', 'picked_up', 'on_the_way'])->count(),
            'online_drivers'   => DeliveryProfile::where('availability_status', 'online')->count(),
            'pending_reports'  => Report::where('status', 'pending')->count(),
            'pending_withdrawals' => WithdrawalRequest::where('status', 'pending')->count(),
        ];

        $recentOrders = Order::with(['customer.profile', 'business'])->latest()->take(10)->get();
        $revenueChart = Order::selectRaw('DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as count')
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [now()->subDays(7), now()])
            ->groupBy('date')->orderBy('date')->get();

        return response()->json(['success' => true, 'data' => ['stats' => $stats, 'recent_orders' => $recentOrders, 'revenue_chart' => $revenueChart]]);
    }

    // ─── USER MANAGEMENT ─────────────────────────────────────────────────

    public function users(Request $request)
    {
        $users = User::with('profile')
            ->when($request->search, fn($q) => $q->where('username', 'like', "%{$request->search}%")->orWhere('email', 'like', "%{$request->search}%"))
            ->when($request->type, fn($q) => $q->where('account_type', $request->type))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()->paginate(25);
        return response()->json(['success' => true, 'data' => $users]);
    }

    public function banUser(Request $request, User $user)
    {
        $request->validate(['reason' => 'required|string', 'is_permanent' => 'boolean', 'expires_at' => 'nullable|date']);
        $user->update(['status' => 'banned']);
        \App\Models\BannedAccount::updateOrCreate(['user_id' => $user->id], [
            'reason'       => $request->reason,
            'banned_by'    => auth()->id(),
            'banned_at'    => now(),
            'expires_at'   => $request->expires_at,
            'is_permanent' => $request->is_permanent ?? false,
        ]);
        return response()->json(['success' => true, 'message' => 'User banned']);
    }

    public function unbanUser(User $user)
    {
        $user->update(['status' => 'active']);
        \App\Models\BannedAccount::where('user_id', $user->id)->delete();
        return response()->json(['success' => true, 'message' => 'User unbanned']);
    }

    public function suspendUser(Request $request, User $user)
    {
        $user->update(['status' => 'suspended']);
        return response()->json(['success' => true]);
    }

    // ─── BUSINESS MANAGEMENT ─────────────────────────────────────────────

    public function businesses(Request $request)
    {
        $businesses = Business::with(['owner.profile', 'category', 'verification'])
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->type, fn($q) => $q->where('business_type', $request->type))
            ->when($request->status, fn($q) => $q->where('verification_status', $request->status))
            ->latest()->paginate(25);
        return response()->json(['success' => true, 'data' => $businesses]);
    }

    public function verifyBusiness(Request $request, Business $business)
    {
        $request->validate(['action' => 'required|in:approve,reject', 'notes' => 'nullable|string']);
        $status = $request->action === 'approve' ? 'verified' : 'rejected';
        $business->update(['verification_status' => $status]);
        $business->verification()?->update(['status' => $status, 'verified_by' => auth()->id(), 'verified_at' => now(), 'rejection_reason' => $request->notes]);
        // Notify business owner
        app(\App\Services\NotificationService::class)->send(
            $business->owner,
            'business_verification',
            $status === 'verified' ? 'Business Verified! ✅' : 'Business Verification Update',
            $status === 'verified' ? "Congratulations! Your business has been verified on Vezafud." : "Your business verification was {$status}. {$request->notes}",
        );
        return response()->json(['success' => true, 'message' => "Business {$status}"]);
    }

    public function featureBusiness(Business $business)
    {
        $business->update(['is_featured' => !$business->is_featured]);
        return response()->json(['success' => true, 'is_featured' => $business->is_featured]);
    }

    // ─── DELIVERY MANAGEMENT ─────────────────────────────────────────────

    public function drivers(Request $request)
    {
        $drivers = DeliveryProfile::with('user.profile', 'vehicles')
            ->when($request->status, fn($q) => $q->where('approval_status', $request->status))
            ->latest()->paginate(25);
        return response()->json(['success' => true, 'data' => $drivers]);
    }

    public function approveDriver(Request $request, DeliveryProfile $driver)
    {
        $request->validate(['action' => 'required|in:approve,reject,suspend']);
        $status = match ($request->action) {
            'approve' => 'approved',
            'reject'  => 'rejected',
            'suspend' => 'suspended',
        };
        $driver->update(['approval_status' => $status]);
        app(\App\Services\NotificationService::class)->send($driver->user, 'driver_approval', "Driver Application {$status}", "Your delivery driver application has been {$status}.");
        return response()->json(['success' => true]);
    }

    // ─── FINANCIAL MANAGEMENT ────────────────────────────────────────────

    public function financialOverview()
    {
        $data = [
            'total_revenue'      => Order::where('payment_status', 'paid')->sum('total'),
            'total_commissions'  => Order::where('payment_status', 'paid')->sum('commission_amount'),
            'total_payouts'      => \App\Models\DriverEarning::sum('amount'),
            'pending_withdrawals'=> WithdrawalRequest::where('status', 'pending')->sum('amount'),
            'monthly_revenue'    => Order::whereMonth('created_at', now()->month)->where('payment_status', 'paid')->sum('total'),
        ];
        $withdrawals = WithdrawalRequest::with('user.profile')->where('status', 'pending')->latest()->paginate(20);
        return response()->json(['success' => true, 'data' => ['overview' => $data, 'pending_withdrawals' => $withdrawals]]);
    }

    public function processWithdrawal(Request $request, WithdrawalRequest $withdrawal)
    {
        $request->validate(['action' => 'required|in:approve,reject', 'notes' => 'nullable|string']);
        $status = $request->action === 'approve' ? 'processing' : 'rejected';
        $withdrawal->update(['status' => $status, 'rejection_reason' => $request->notes, 'processed_by' => auth()->id()]);
        if ($status === 'rejected') {
            // Refund wallet
            $withdrawal->user->getOrCreateWallet()->credit($withdrawal->amount, 'adjustment', "Withdrawal request rejected: {$request->notes}");
        }
        return response()->json(['success' => true]);
    }

    // ─── CONTENT MODERATION ──────────────────────────────────────────────

    public function reports(Request $request)
    {
        $reports = Report::with(['reporter.profile'])->when($request->status, fn($q) => $q->where('status', $request->status))->latest()->paginate(25);
        return response()->json(['success' => true, 'data' => $reports]);
    }

    public function resolveReport(Request $request, Report $report)
    {
        $request->validate(['action' => 'required|in:dismiss,remove_content,warn_user,suspend_user,ban_user']);
        $report->update(['status' => 'resolved', 'action_taken' => $report->action, 'resolved_by' => auth()->id()]);
        // Apply action
        if (in_array($request->action, ['suspend_user', 'ban_user'])) {
            $reportable = $report->reportable;
            $userId = $reportable?->user_id ?? $reportable?->id;
            if ($userId) User::find($userId)?->update(['status' => $request->action === 'ban_user' ? 'banned' : 'suspended']);
        }
        if ($request->action === 'remove_content' && method_exists($report->reportable, 'delete')) {
            $report->reportable->delete();
        }
        return response()->json(['success' => true]);
    }

    // ─── PLATFORM SETTINGS ───────────────────────────────────────────────

    public function settings()
    {
        $settings = PlatformSetting::all()->keyBy('key');
        return response()->json(['success' => true, 'data' => $settings]);
    }

    public function updateSettings(Request $request)
    {
        foreach ($request->settings as $key => $value) {
            PlatformSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        return response()->json(['success' => true, 'message' => 'Settings updated']);
    }

    // ─── ANALYTICS ───────────────────────────────────────────────────────

    public function analytics(Request $request)
    {
        $period = $request->period ?? 30;
        $from = now()->subDays($period);
        $data = [
            'users_growth'     => User::selectRaw('DATE(created_at) d, COUNT(*) c')->where('created_at', '>=', $from)->groupBy('d')->orderBy('d')->pluck('c', 'd'),
            'orders_by_day'    => Order::selectRaw('DATE(created_at) d, COUNT(*) c, SUM(total) rev')->where('created_at', '>=', $from)->groupBy('d')->orderBy('d')->get(),
            'top_businesses'   => Business::withCount('orders')->orderByDesc('orders_count')->take(10)->get(['id', 'name', 'business_type', 'orders_count']),
            'top_cuisines'     => Order::join('businesses', 'orders.business_id', 'businesses.id')->selectRaw('businesses.business_type, COUNT(*) as count')->groupBy('businesses.business_type')->orderByDesc('count')->take(10)->get(),
            'revenue_summary'  => [
                'today'    => Order::whereDate('created_at', today())->where('payment_status', 'paid')->sum('total'),
                'week'     => Order::whereBetween('created_at', [now()->startOfWeek(), now()])->where('payment_status', 'paid')->sum('total'),
                'month'    => Order::whereMonth('created_at', now()->month)->where('payment_status', 'paid')->sum('total'),
                'all_time' => Order::where('payment_status', 'paid')->sum('total'),
            ],
        ];
        return response()->json(['success' => true, 'data' => $data]);
    }
}
