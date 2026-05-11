<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\RecipeController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Admin\AdminController;

/*
|──────────────────────────────────────────────────────────────────────────────
| Vezafud Platform — API Routes
| Base: /api/v1
|──────────────────────────────────────────────────────────────────────────────
*/

Route::prefix('v1')->group(function () {

    // ─── AUTH ────────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('register',        [AuthController::class, 'register']);
        Route::post('login',           [AuthController::class, 'login']);
        Route::post('social-login',    [AuthController::class, 'socialLogin']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password',  [AuthController::class, 'resetPassword']);
        Route::post('send-otp',        [AuthController::class, 'sendOtp']);
        Route::post('verify-otp',      [AuthController::class, 'verifyOtp']);

        Route::middleware('auth:api')->group(function () {
            Route::post('logout',          [AuthController::class, 'logout']);
            Route::post('refresh',         [AuthController::class, 'refresh']);
            Route::get('me',               [AuthController::class, 'me']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
        });
    });

    // ─── WEBHOOKS (no auth) ──────────────────────────────────────────────
    Route::prefix('webhooks')->group(function () {
        Route::post('paystack',    [PaymentController::class, 'paystackWebhook']);
        Route::post('flutterwave', [PaymentController::class, 'flutterwaveWebhook']);
        Route::post('wallet/paystack', [WalletController::class, 'webhookPaystack']);
    });

    // ─── PUBLIC ENDPOINTS ────────────────────────────────────────────────
    Route::get('businesses',              [BusinessController::class, 'index']);
    Route::get('businesses/nearby',       [BusinessController::class, 'nearby']);
    Route::get('businesses/{slug}',       [BusinessController::class, 'show']);
    Route::get('businesses/{slug}/menu',  [BusinessController::class, 'menu']);
    Route::get('businesses/{slug}/reviews',[BusinessController::class, 'reviews']);
    Route::get('recipes',                 [RecipeController::class, 'index']);
    Route::get('recipes/categories',      [RecipeController::class, 'categories']);
    Route::get('recipes/{uuid}',          [RecipeController::class, 'show']);
    Route::get('users/{username}/profile',[UserController::class, 'profile']);
    Route::get('search',                  [UserController::class, 'search']);

    // ─── AUTHENTICATED ROUTES ────────────────────────────────────────────
    Route::middleware('auth:api')->group(function () {

        // FEED & SOCIAL
        Route::prefix('feed')->group(function () {
            Route::get('/',         [PostController::class, 'feed']);
            Route::get('explore',   [PostController::class, 'explore']);
            Route::get('reels',     [PostController::class, 'reels']);
            Route::get('stories',   [PostController::class, 'stories']);
            Route::get('trending',  fn() => response()->json(['success' => true, 'data' => app(\App\Services\FeedService::class)->getTrendingHashtags()]));
            Route::get('suggested', fn() => response()->json(['success' => true, 'data' => app(\App\Services\FeedService::class)->getSuggestedUsers(auth()->user())]));
        });

        // POSTS
        Route::prefix('posts')->group(function () {
            Route::post('/',                   [PostController::class, 'store']);
            Route::get('{uuid}',               [PostController::class, 'show']);
            Route::delete('{post}',            [PostController::class, 'destroy']);
            Route::post('{post}/like',         [PostController::class, 'like']);
            Route::post('{post}/comment',      [PostController::class, 'comment']);
            Route::post('{post}/save',         [PostController::class, 'save']);
            Route::get('{post}/comments',      fn(\App\Models\Post $post) => response()->json(['success' => true, 'data' => $post->comments()->with('user.profile', 'replies.user.profile')->paginate(20)]));
        });

        // STORIES
        Route::prefix('stories')->group(function () {
            Route::post('/', function (\Illuminate\Http\Request $req) {
                $req->validate(['media' => 'required|file|mimes:jpg,jpeg,png,gif,mp4|max:51200','visibility' => 'nullable|in:public,followers,close_friends']);
                $path = $req->file('media')->store('stories/' . auth()->id(), 'public');
                $story = auth()->user()->stories()->create(['media_type' => str_contains($req->file('media')->getMimeType(), 'video') ? 'video' : 'image', 'media_url' => $path, 'visibility' => $req->visibility ?? 'followers', 'expires_at' => now()->addHours(24)]);
                return response()->json(['success' => true, 'data' => $story], 201);
            });
            Route::post('{story}/view', function (\App\Models\Story $story) {
                $story->views()->firstOrCreate(['user_id' => auth()->id()]);
                $story->increment('view_count');
                return response()->json(['success' => true]);
            });
        });

        // RECIPES
        Route::prefix('recipes')->group(function () {
            Route::post('/',            [RecipeController::class, 'store']);
            Route::post('{recipe}/like',[RecipeController::class, 'like']);
            Route::post('{recipe}/save',[RecipeController::class, 'save']);
            Route::post('{recipe}/comment', function (\Illuminate\Http\Request $req, \App\Models\Recipe $recipe) {
                $req->validate(['body' => 'required|string|max:1000']);
                $comment = $recipe->comments()->create(['user_id' => auth()->id(), 'body' => $req->body]);
                $recipe->increment('comment_count');
                return response()->json(['success' => true, 'data' => $comment->load('user.profile')], 201);
            });
            Route::post('{recipe}/rate', function (\Illuminate\Http\Request $req, \App\Models\Recipe $recipe) {
                $req->validate(['rating' => 'required|integer|min:1|max:5', 'review' => 'nullable|string']);
                \App\Models\RecipeRating::updateOrCreate(['recipe_id' => $recipe->id, 'user_id' => auth()->id()], ['rating' => $req->rating, 'review' => $req->review]);
                $recipe->update(['rating_avg' => $recipe->ratings()->avg('rating'), 'rating_count' => $recipe->ratings()->count()]);
                return response()->json(['success' => true]);
            });
        });

        // BUSINESSES
        Route::prefix('businesses')->group(function () {
            Route::post('/',                          [BusinessController::class, 'store']);
            Route::put('{business}',                  [BusinessController::class, 'update']);
            Route::post('{slug}/follow',              [BusinessController::class, 'follow']);
            Route::post('{business}/review',          function (\Illuminate\Http\Request $req, \App\Models\Business $business) {
                $req->validate(['rating' => 'required|integer|min:1|max:5', 'title' => 'nullable|string', 'body' => 'nullable|string', 'order_id' => 'nullable|exists:orders,id']);
                $review = $business->reviews()->updateOrCreate(['user_id' => auth()->id(), 'order_id' => $req->order_id], array_merge($req->only(['rating','title','body','food_rating','service_rating','delivery_rating']), ['is_verified_purchase' => (bool)$req->order_id]));
                $business->update(['rating_avg' => $business->reviews()->where('status','active')->avg('rating'), 'review_count' => $business->reviews()->where('status','active')->count()]);
                app(\App\Services\WalletService::class)->awardPoints(auth()->user(), 3, 'review', 'Posted a review');
                return response()->json(['success' => true, 'data' => $review], 201);
            });
            Route::post('{business}/reservation', function (\Illuminate\Http\Request $req, \App\Models\Business $business) {
                $req->validate(['guest_count' => 'required|integer|min:1', 'reservation_date' => 'required|date|after:today', 'reservation_time' => 'required|date_format:H:i']);
                $reservation = \App\Models\Reservation::create(array_merge($req->only(['guest_count','reservation_date','reservation_time','special_requests']), ['business_id' => $business->id, 'user_id' => auth()->id()]));
                return response()->json(['success' => true, 'data' => $reservation], 201);
            });
        });

        // MENU MANAGEMENT (business owners)
        Route::prefix('menus')->group(function () {
            Route::post('/', function (\Illuminate\Http\Request $req) {
                $req->validate(['business_id' => 'required|exists:businesses,id', 'name' => 'required|string']);
                $business = \App\Models\Business::findOrFail($req->business_id);
                if ($business->owner_id !== auth()->id()) abort(403);
                $menu = \App\Models\Menu::create($req->only(['business_id','branch_id','name','description','menu_type']));
                return response()->json(['success' => true, 'data' => $menu], 201);
            });
            Route::post('{menu}/items', function (\Illuminate\Http\Request $req, \App\Models\Menu $menu) {
                if ($menu->business->owner_id !== auth()->id()) abort(403);
                $req->validate(['name' => 'required|string', 'price' => 'required|numeric|min:0']);
                $item = $menu->items()->create($req->only(['category_id','name','description','price','discount_price','preparation_time','is_featured','is_vegetarian','is_vegan','is_halal']));
                if ($req->hasFile('image')) {
                    $item->media()->create(['media_url' => $req->file('image')->store("menus/{$menu->id}",'public'), 'is_primary' => true]);
                }
                return response()->json(['success' => true, 'data' => $item], 201);
            });
            Route::put('items/{item}', function (\Illuminate\Http\Request $req, \App\Models\MenuItem $item) {
                if ($item->menu->business->owner_id !== auth()->id()) abort(403);
                $item->update($req->only(['name','description','price','discount_price','availability','preparation_time','is_featured']));
                return response()->json(['success' => true, 'data' => $item]);
            });
        });

        // ORDERS
        Route::prefix('orders')->group(function () {
            Route::get('/',                     [OrderController::class, 'myOrders']);
            Route::get('{uuid}',                [OrderController::class, 'show']);
            Route::get('{uuid}/track',          [OrderController::class, 'track']);
            Route::post('{order}/cancel',       [OrderController::class, 'cancel']);
            Route::get('cart',                  [OrderController::class, 'cart']);
            Route::post('cart/add',             [OrderController::class, 'addToCart']);
            Route::put('cart/item/{item}',      [OrderController::class, 'updateCartItem']);
            Route::post('cart/coupon',          [OrderController::class, 'applyCoupon']);
            Route::post('place',                [OrderController::class, 'placeOrder']);
            // Business order management
            Route::get('business/{business}',   [OrderController::class, 'businessOrders']);
            Route::post('{order}/accept',       [OrderController::class, 'accept']);
            Route::post('{order}/reject',       [OrderController::class, 'reject']);
            Route::post('{order}/preparing',    [OrderController::class, 'markPreparing']);
            Route::post('{order}/ready',        [OrderController::class, 'markReady']);
        });

        // PAYMENTS
        Route::prefix('payments')->group(function () {
            Route::post('orders/{uuid}/initialize', [PaymentController::class, 'initializeOrderPayment']);
        });

        // DELIVERY
        Route::prefix('delivery')->group(function () {
            Route::get('profile',              [DeliveryController::class, 'profile']);
            Route::post('location',            [DeliveryController::class, 'updateLocation']);
            Route::post('availability',        [DeliveryController::class, 'toggleAvailability']);
            Route::get('active',               [DeliveryController::class, 'activeDelivery']);
            Route::post('{delivery}/accept',   [DeliveryController::class, 'accept']);
            Route::post('{delivery}/pickup',   [DeliveryController::class, 'confirmPickup']);
            Route::post('{delivery}/deliver',  [DeliveryController::class, 'confirmDelivery']);
            Route::get('history',              [DeliveryController::class, 'history']);
            Route::get('earnings',             [DeliveryController::class, 'earnings']);
        });

        // WALLET
        Route::prefix('wallet')->group(function () {
            Route::get('/',                    [WalletController::class, 'index']);
            Route::get('transactions',         [WalletController::class, 'transactions']);
            Route::post('topup',               [WalletController::class, 'initTopup']);
            Route::post('withdraw',            [WalletController::class, 'requestWithdrawal']);
            Route::post('pin',                 [WalletController::class, 'setPin']);
            Route::get('rewards',              [WalletController::class, 'loyaltyRewards']);
            Route::post('rewards/redeem',      [WalletController::class, 'redeemPoints']);
            Route::get('points-history',       [WalletController::class, 'pointsHistory']);
        });

        // MESSAGES
        Route::prefix('messages')->group(function () {
            Route::get('/',                    [MessageController::class, 'conversations']);
            Route::post('start',               [MessageController::class, 'startConversation']);
            Route::get('{uuid}',               [MessageController::class, 'messages']);
            Route::post('{uuid}/send',         [MessageController::class, 'send']);
            Route::delete('messages/{message}',[MessageController::class, 'deleteMessage']);
        });

        // USER PROFILE
        Route::prefix('users')->group(function () {
            Route::put('profile',                  [UserController::class, 'updateProfile']);
            Route::post('avatar',                  [UserController::class, 'uploadAvatar']);
            Route::put('privacy',                  [UserController::class, 'updatePrivacy']);
            Route::post('{username}/follow',       [UserController::class, 'follow']);
            Route::get('{username}/followers',     [UserController::class, 'followers']);
            Route::get('{username}/following',     [UserController::class, 'following']);
            Route::get('{username}/posts',         [UserController::class, 'posts']);
            Route::get('me/saved',                 [UserController::class, 'savedPosts']);
            Route::get('me/notifications',         [UserController::class, 'notifications']);
            Route::post('me/notifications/read',   [UserController::class, 'markNotificationsRead']);
        });

        // SPIN WHEEL
        Route::post('spin-wheel', function () {
            $user = auth()->user();
            $lastSpin = \App\Models\SpinWheelAttempt::where('user_id', $user->id)->latest()->first();
            if ($lastSpin && $lastSpin->created_at->isToday()) {
                return response()->json(['success' => false, 'message' => 'You can only spin once per day'], 429);
            }
            $rewards = \App\Models\SpinWheelReward::where('is_active', true)->get();
            $totalWeight = $rewards->sum('probability_weight');
            $rand = rand(1, $totalWeight);
            $cumulative = 0;
            $won = null;
            foreach ($rewards as $reward) {
                $cumulative += $reward->probability_weight;
                if ($rand <= $cumulative) { $won = $reward; break; }
            }
            $attempt = \App\Models\SpinWheelAttempt::create(['user_id' => $user->id, 'reward_id' => $won?->id, 'won' => $won && $won->reward_type !== 'no_win']);
            if ($won && $won->reward_type !== 'no_win') {
                match ($won->reward_type) {
                    'points'   => app(\App\Services\WalletService::class)->awardPoints($user, $won->points_value ?? 10, 'campaign', 'Spin-to-win reward!'),
                    'cashback' => $user->getOrCreateWallet()->credit($won->reward_value ?? 2, 'promotional_bonus', 'Spin-to-win cashback!'),
                    default    => null,
                };
            }
            return response()->json(['success' => true, 'data' => ['reward' => $won, 'won' => $attempt->won]]);
        });
    });

    // ─── ADMIN ROUTES ────────────────────────────────────────────────────
    Route::prefix('admin')->middleware(['auth:api', 'admin'])->group(function () {
        Route::get('dashboard',              [AdminController::class, 'dashboard']);
        Route::get('analytics',              [AdminController::class, 'analytics']);
        Route::get('users',                  [AdminController::class, 'users']);
        Route::post('users/{user}/ban',      [AdminController::class, 'banUser']);
        Route::post('users/{user}/unban',    [AdminController::class, 'unbanUser']);
        Route::post('users/{user}/suspend',  [AdminController::class, 'suspendUser']);
        Route::get('businesses',             [AdminController::class, 'businesses']);
        Route::post('businesses/{business}/verify', [AdminController::class, 'verifyBusiness']);
        Route::post('businesses/{business}/feature', [AdminController::class, 'featureBusiness']);
        Route::get('drivers',                [AdminController::class, 'drivers']);
        Route::post('drivers/{driver}/approve', [AdminController::class, 'approveDriver']);
        Route::get('finance',                [AdminController::class, 'financialOverview']);
        Route::post('finance/withdrawals/{withdrawal}', [AdminController::class, 'processWithdrawal']);
        Route::get('reports',                [AdminController::class, 'reports']);
        Route::post('reports/{report}/resolve', [AdminController::class, 'resolveReport']);
        Route::get('settings',               [AdminController::class, 'settings']);
        Route::put('settings',               [AdminController::class, 'updateSettings']);
    });
});
