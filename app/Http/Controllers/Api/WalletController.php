<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\WalletService;
use App\Models\Wallet;
use App\Models\LoyaltyReward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class WalletController extends Controller
{
    public function __construct(protected WalletService $walletService) {}

    public function index()
    {
        $user = auth()->user();
        $wallet = $user->getOrCreateWallet();
        $points = $user->rewardPoints;
        $recentTx = $wallet->transactions()->latest()->take(10)->get();
        return response()->json(['success'=>true,'data'=>['balance'=>$wallet->balance,'currency'=>$wallet->currency,'points'=>$points?->available_points??0,'tier'=>$points?->tier??'bronze','is_frozen'=>$wallet->is_frozen,'pin_enabled'=>$wallet->pin_enabled,'transactions'=>$recentTx]]);
    }

    public function transactions(Request $request)
    {
        $wallet = auth()->user()->getOrCreateWallet();
        $txs = $wallet->transactions()->when($request->type,fn($q)=>$q->where('transaction_type',$request->type))->latest()->paginate(20);
        return response()->json(['success'=>true,'data'=>$txs]);
    }

    public function initTopup(Request $request)
    {
        $request->validate(['amount'=>'required|numeric|min:1','payment_method'=>'required|in:mobile_money,debit_card,credit_card,bank_transfer','gateway'=>'required|in:paystack,flutterwave,stripe']);
        $topup = $this->walletService->topup(auth()->user(),$request->amount,$request->payment_method,['gateway'=>$request->gateway]);
        // Initialize payment with gateway
        $paymentUrl = $this->initPaymentGateway($request->gateway,$topup,auth()->user());
        return response()->json(['success'=>true,'data'=>['topup_id'=>$topup->id,'payment_url'=>$paymentUrl,'amount'=>$topup->amount]]);
    }

    public function webhookPaystack(Request $request)
    {
        if (!hash_equals(hash_hmac('sha512',file_get_contents('php://input'),config('paystack.secretKey')),$request->header('x-paystack-signature',''))) {
            return response()->json(['error'=>'Invalid signature'],400);
        }
        $event = $request->event;
        if ($event === 'charge.success') {
            $topupId = $request->input('data.metadata.topup_id');
            $topup = \App\Models\WalletTopup::find($topupId);
            if ($topup && $topup->status === 'pending') {
                $this->walletService->confirmTopup($topup,$request->input('data.reference'),$request->all());
            }
        }
        return response()->json(['status'=>'ok']);
    }

    public function redeemPoints(Request $request)
    {
        $request->validate(['reward_id'=>'required|exists:loyalty_rewards,id']);
        $reward = LoyaltyReward::findOrFail($request->reward_id);
        try {
            $redemption = $this->walletService->redeemPoints(auth()->user(),$reward->points_required,$reward);
            return response()->json(['success'=>true,'data'=>['redemption_code'=>$redemption->redemption_code,'reward'=>$reward,'expires_at'=>$redemption->expires_at]]);
        } catch (\Exception $e) { return response()->json(['success'=>false,'message'=>$e->getMessage()],400); }
    }

    public function setPin(Request $request)
    {
        $request->validate(['pin'=>'required|string|size:4|regex:/^[0-9]+$/','pin_confirmation'=>'required|same:pin']);
        $wallet = auth()->user()->getOrCreateWallet();
        $wallet->update(['transaction_pin_hash'=>Hash::make($request->pin),'pin_enabled'=>true]);
        return response()->json(['success'=>true,'message'=>'Transaction PIN set successfully']);
    }

    public function requestWithdrawal(Request $request)
    {
        $request->validate(['amount'=>'required|numeric|min:10','withdrawal_method'=>'required|in:mobile_money,bank_transfer','account_details'=>'required|array','pin'=>'required_if:pin_enabled,true|string|size:4']);
        $wallet = auth()->user()->wallet;
        if ($wallet?->pin_enabled && !Hash::check($request->pin,$wallet->transaction_pin_hash)) {
            return response()->json(['success'=>false,'message'=>'Incorrect transaction PIN'],400);
        }
        try {
            $withdrawal = $this->walletService->requestWithdrawal(auth()->user(),$request->amount,$request->withdrawal_method,$request->account_details);
            return response()->json(['success'=>true,'message'=>'Withdrawal request submitted','data'=>$withdrawal],201);
        } catch (\Exception $e) { return response()->json(['success'=>false,'message'=>$e->getMessage()],400); }
    }

    public function loyaltyRewards()
    {
        $rewards = LoyaltyReward::where('is_active',true)->where(fn($q)=>$q->whereNull('expires_at')->orWhere('expires_at','>',now()))->get();
        $userPoints = auth()->user()->rewardPoints?->available_points ?? 0;
        return response()->json(['success'=>true,'data'=>['rewards'=>$rewards,'user_points'=>$userPoints]]);
    }

    public function pointsHistory()
    {
        $txs = \App\Models\RewardPointTransaction::where('user_id',auth()->id())->latest()->paginate(20);
        $summary = auth()->user()->rewardPoints;
        return response()->json(['success'=>true,'data'=>['transactions'=>$txs,'summary'=>$summary]]);
    }

    private function initPaymentGateway(string $gateway, \App\Models\WalletTopup $topup, \App\Models\User $user): string
    {
        return match($gateway) {
            'paystack'    => "https://paystack.com/pay/vezafud-topup-{$topup->id}",
            'flutterwave' => "https://checkout.flutterwave.com/v3/hosted/pay/vezafud-{$topup->id}",
            default       => "#",
        };
    }
}
