<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserPrivacySetting;
use App\Models\Wallet;
use App\Models\RewardPoint;
use App\Models\OtpVerification;
use App\Services\OtpService;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        protected OtpService $otpService,
        protected WalletService $walletService,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username'     => 'required|string|min:3|max:50|unique:users|regex:/^[a-zA-Z0-9_.]+$/',
            'email'        => 'nullable|email|unique:users',
            'phone'        => 'nullable|string|unique:users',
            'password'     => 'required|string|min:8|confirmed',
            'account_type' => 'required|in:customer,food_creator,restaurant,bar,lounge,bakery,chef,caterer,food_vendor,coffee_shop,juice_vendor,food_truck,delivery_rider,delivery_company',
            'full_name'    => 'nullable|string|max:100',
            'referral_code'=> 'nullable|string|exists:users,referral_code',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if (empty($request->email) && empty($request->phone)) {
            return response()->json(['success' => false, 'message' => 'Email or phone number is required'], 422);
        }

        DB::beginTransaction();
        try {
            $referredBy = null;
            if ($request->referral_code) {
                $referrer = User::where('referral_code', $request->referral_code)->first();
                $referredBy = $referrer?->id;
            }

            $user = User::create([
                'username'     => strtolower($request->username),
                'email'        => $request->email,
                'phone'        => $request->phone,
                'password_hash'=> Hash::make($request->password),
                'account_type' => $request->account_type,
                'referred_by'  => $referredBy,
            ]);

            UserProfile::create([
                'user_id'   => $user->id,
                'full_name' => $request->full_name ?? $request->username,
            ]);

            UserPrivacySetting::create(['user_id' => $user->id]);
            Wallet::create(['user_id' => $user->id]);
            RewardPoint::create(['user_id' => $user->id]);

            // Send OTP
            if ($request->email) {
                $this->otpService->sendEmailOtp($request->email, 'register');
            } elseif ($request->phone) {
                $this->otpService->sendSmsOtp($request->phone, 'register');
            }

            // Handle referral
            if ($referredBy) {
                $this->walletService->processReferral($referredBy, $user->id);
            }

            $token = JWTAuth::fromUser($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Account created successfully. Please verify your account.',
                'data'    => [
                    'user'  => $this->formatUser($user),
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => config('jwt.ttl') * 60,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Registration failed. Please try again.', 'error' => $e->getMessage()], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Find user by email, phone, or username
        $user = User::where('email', $request->login)
            ->orWhere('phone', $request->login)
            ->orWhere('username', $request->login)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            // Log failed attempt
            \App\Models\LoginAttempt::create([
                'identifier' => $request->login,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'successful' => false,
                'failure_reason' => 'Invalid credentials',
            ]);
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        if ($user->status === 'banned') {
            return response()->json(['success' => false, 'message' => 'This account has been banned.'], 403);
        }

        if ($user->status === 'suspended') {
            return response()->json(['success' => false, 'message' => 'This account is suspended.'], 403);
        }

        $token = JWTAuth::fromUser($user);
        $user->update(['last_active_at' => now()]);

        // Track device
        if ($request->device_token) {
            $user->devices()->updateOrCreate(
                ['device_id' => $request->device_id ?? $request->device_token],
                ['fcm_token' => $request->device_token, 'platform' => $request->platform, 'last_used_at' => now()]
            );
        }

        \App\Models\LoginAttempt::create([
            'identifier' => $request->login,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'successful' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data'    => [
                'user'  => $this->formatUser($user->load('profile')),
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl') * 60,
            ],
        ]);
    }

    public function logout(): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json(['success' => true, 'message' => 'Logged out successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Could not log out'], 500);
        }
    }

    public function refresh(): JsonResponse
    {
        try {
            $token = JWTAuth::refresh();
            return response()->json([
                'success' => true,
                'data' => ['token' => $token, 'token_type' => 'Bearer', 'expires_in' => config('jwt.ttl') * 60],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Could not refresh token'], 401);
        }
    }

    public function me(): JsonResponse
    {
        $user = JWTAuth::parseToken()->authenticate();
        $user->load(['profile', 'wallet', 'rewardPoints']);
        return response()->json(['success' => true, 'data' => $this->formatUser($user)]);
    }

    public function sendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identifier'      => 'required|string',
            'identifier_type' => 'required|in:email,phone',
            'purpose'         => 'required|in:login,register,password_reset,phone_verify,transaction',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            if ($request->identifier_type === 'email') {
                $this->otpService->sendEmailOtp($request->identifier, $request->purpose);
            } else {
                $this->otpService->sendSmsOtp($request->identifier, $request->purpose);
            }
            return response()->json(['success' => true, 'message' => 'OTP sent successfully']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 429);
        }
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'identifier'      => 'required|string',
            'identifier_type' => 'required|in:email,phone',
            'otp'             => 'required|string|size:6',
            'purpose'         => 'required|in:login,register,password_reset,phone_verify,transaction',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $verified = $this->otpService->verify(
            $request->identifier,
            $request->identifier_type,
            $request->otp,
            $request->purpose
        );

        if (!$verified) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP'], 400);
        }

        // Mark verified on user
        $field = $request->identifier_type === 'email' ? 'is_email_verified' : 'is_phone_verified';
        User::where($request->identifier_type, $request->identifier)->update([$field => true, 'is_verified' => true]);

        return response()->json(['success' => true, 'message' => 'OTP verified successfully']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email|exists:users,email']);
        $this->otpService->sendEmailOtp($request->email, 'password_reset');
        return response()->json(['success' => true, 'message' => 'Password reset OTP sent to your email']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email|exists:users,email',
            'otp'      => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!$this->otpService->verify($request->email, 'email', $request->otp, 'password_reset')) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired OTP'], 400);
        }

        User::where('email', $request->email)->update(['password_hash' => Hash::make($request->password)]);
        return response()->json(['success' => true, 'message' => 'Password reset successfully']);
    }

    public function socialLogin(Request $request): JsonResponse
    {
        $request->validate([
            'provider'    => 'required|in:google,facebook,apple',
            'token'       => 'required|string',
            'account_type'=> 'nullable|in:customer,food_creator',
        ]);

        try {
            $socialUser = \Laravel\Socialite\Facades\Socialite::driver($request->provider)->userFromToken($request->token);

            $social = \App\Models\SocialAccount::firstOrNew([
                'provider'    => $request->provider,
                'provider_id' => $socialUser->getId(),
            ]);

            if (!$social->exists || !$social->user_id) {
                // Create new user
                DB::beginTransaction();
                $user = User::firstOrCreate(['email' => $socialUser->getEmail()], [
                    'username'          => 'user_' . Str::random(8),
                    'account_type'      => $request->account_type ?? 'customer',
                    'is_email_verified' => true,
                    'is_verified'       => true,
                ]);
                UserProfile::firstOrCreate(['user_id' => $user->id], [
                    'full_name' => $socialUser->getName(),
                    'avatar'    => $socialUser->getAvatar(),
                ]);
                Wallet::firstOrCreate(['user_id' => $user->id]);
                RewardPoint::firstOrCreate(['user_id' => $user->id]);
                $social->fill(['user_id' => $user->id, 'provider_token' => $request->token])->save();
                DB::commit();
            } else {
                $user = $social->user;
            }

            $token = JWTAuth::fromUser($user);
            return response()->json([
                'success' => true,
                'data' => ['user' => $this->formatUser($user->load('profile')), 'token' => $token, 'token_type' => 'Bearer'],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Social login failed: ' . $e->getMessage()], 400);
        }
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = auth()->user();
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password_hash)) {
            return response()->json(['success' => false, 'message' => 'Current password is incorrect'], 400);
        }

        $user->update(['password_hash' => Hash::make($request->password)]);
        return response()->json(['success' => true, 'message' => 'Password changed successfully']);
    }

    private function formatUser(User $user): array
    {
        return [
            'id'           => $user->id,
            'uuid'         => $user->uuid,
            'username'     => $user->username,
            'email'        => $user->email,
            'phone'        => $user->phone,
            'account_type' => $user->account_type,
            'is_verified'  => $user->is_verified,
            'status'       => $user->status,
            'full_name'    => $user->profile?->full_name,
            'bio'          => $user->profile?->bio,
            'avatar'       => $user->profile?->avatar ? asset('storage/' . $user->profile->avatar) : null,
            'cover_photo'  => $user->profile?->cover_photo ? asset('storage/' . $user->profile->cover_photo) : null,
            'city'         => $user->profile?->city,
            'country'      => $user->profile?->country,
            'follower_count'  => $user->profile?->follower_count ?? 0,
            'following_count' => $user->profile?->following_count ?? 0,
            'post_count'      => $user->profile?->post_count ?? 0,
            'wallet_balance'  => $user->wallet?->balance ?? 0,
            'reward_points'   => $user->rewardPoints?->available_points ?? 0,
            'loyalty_tier'    => $user->rewardPoints?->tier ?? 'bronze',
            'referral_code'   => $user->referral_code,
            'created_at'      => $user->created_at,
        ];
    }
}
