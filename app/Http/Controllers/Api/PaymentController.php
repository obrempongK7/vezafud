<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function initializeOrderPayment(Request $request, string $uuid)
    {
        $order = Order::where('uuid',$uuid)->where('customer_id',auth()->id())->firstOrFail();
        if ($order->payment_status === 'paid') return response()->json(['success'=>false,'message'=>'Order already paid'],400);
        $ref = 'VZF-ORD-'.strtoupper(Str::random(12));
        Transaction::create(['uuid'=>Str::uuid(),'reference'=>$ref,'user_id'=>auth()->id(),'order_id'=>$order->id,'transaction_type'=>'order_payment','amount'=>$order->total,'currency'=>'GHS','status'=>'pending','payment_gateway'=>$request->gateway??'paystack']);
        $url = $this->buildPaymentUrl($request->gateway??'paystack',$ref,$order->total,$order->customer->email,"Order #{$order->order_number}");
        return response()->json(['success'=>true,'data'=>['reference'=>$ref,'payment_url'=>$url,'amount'=>$order->total]]);
    }

    public function paystackWebhook(Request $request)
    {
        if (!hash_equals(hash_hmac('sha512',file_get_contents('php://input'),config('paystack.secretKey')),$request->header('x-paystack-signature',''))) return response(null,400);
        if ($request->event === 'charge.success') $this->processSuccessfulPayment($request->input('data.reference'),$request->all());
        return response()->json(['status'=>'ok']);
    }

    public function flutterwaveWebhook(Request $request)
    {
        if ($request->header('verif-hash') !== config('flutterwave.secret_hash')) return response(null,400);
        if ($request->input('event') === 'charge.completed' && $request->input('data.status') === 'successful') {
            $this->processSuccessfulPayment($request->input('data.tx_ref'),$request->all());
        }
        return response()->json(['status'=>'ok']);
    }

    private function processSuccessfulPayment(string $ref, array $data): void
    {
        $tx = Transaction::where('reference',$ref)->where('status','pending')->first();
        if (!$tx) return;
        $tx->update(['status'=>'success','gateway_response'=>$data,'completed_at'=>now()]);
        if ($tx->transaction_type === 'order_payment' && $tx->order_id) {
            $order = Order::find($tx->order_id);
            if ($order) {
                $order->update(['payment_status'=>'paid','payment_reference'=>$ref]);
                $order->updateStatus('accepted');
                app(\App\Services\NotificationService::class)->notifyOrderAccepted($order);
                app(\App\Services\DeliveryService::class)->assignDriver($order);
            }
        }
        if ($tx->transaction_type === 'wallet_topup') {
            $topup = \App\Models\WalletTopup::where('gateway_reference',$ref)->first();
            if ($topup) app(\App\Services\WalletService::class)->confirmTopup($topup,$ref,$data);
        }
    }

    private function buildPaymentUrl(string $gateway, string $ref, float $amount, string $email, string $desc): string
    {
        return match($gateway) {
            'paystack'    => "https://checkout.paystack.com/{$ref}",
            'flutterwave' => "https://checkout.flutterwave.com/v3/hosted/pay/{$ref}",
            'stripe'      => "https://checkout.stripe.com/pay/{$ref}",
            default       => "#",
        };
    }
}
