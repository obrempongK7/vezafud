<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserPrivacySetting;
use App\Models\Business;
use App\Models\BusinessCategory;
use App\Models\BusinessHour;
use App\Models\BusinessBranch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RecipeCategory;
use App\Models\Wallet;
use App\Models\RewardPoint;
use App\Models\PlatformSetting;
use App\Models\SpinWheelReward;
use App\Models\Coupon;
use App\Models\LoyaltyReward;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlatformSettingsSeeder::class,
            RolesSeeder::class,
            BusinessCategorySeeder::class,
            RecipeCategorySeeder::class,
            AdminUserSeeder::class,
            SampleDataSeeder::class,
            SpinWheelSeeder::class,
            CouponSeeder::class,
            LoyaltyRewardSeeder::class,
        ]);
    }
}

class PlatformSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'site_name',              'value' => 'Vezafud',          'group' => 'general',  'label' => 'Platform Name',          'is_public' => true],
            ['key' => 'site_tagline',           'value' => 'Eat. Discover. Connect.', 'group' => 'general', 'label' => 'Tagline',          'is_public' => true],
            ['key' => 'currency',               'value' => 'GHS',              'group' => 'financial', 'label' => 'Currency Code',         'is_public' => true],
            ['key' => 'currency_symbol',        'value' => 'GH₵',             'group' => 'financial', 'label' => 'Currency Symbol',       'is_public' => true],
            ['key' => 'commission_rate',        'value' => '10',               'group' => 'financial', 'label' => 'Order Commission %',    'is_public' => false],
            ['key' => 'delivery_commission',    'value' => '15',               'group' => 'financial', 'label' => 'Delivery Commission %', 'is_public' => false],
            ['key' => 'cashback_rate',          'value' => '2',                'group' => 'financial', 'label' => 'Cashback Rate %',       'is_public' => true],
            ['key' => 'referrer_bonus',         'value' => '10.00',            'group' => 'financial', 'label' => 'Referrer Bonus',        'is_public' => true],
            ['key' => 'referred_bonus',         'value' => '5.00',             'group' => 'financial', 'label' => 'Referred User Bonus',   'is_public' => true],
            ['key' => 'min_order_amount',       'value' => '5.00',             'group' => 'orders',    'label' => 'Minimum Order Amount',  'is_public' => true],
            ['key' => 'default_delivery_fee',   'value' => '8.00',             'group' => 'delivery',  'label' => 'Default Delivery Fee',  'is_public' => true],
            ['key' => 'free_delivery_threshold','value' => '100.00',           'group' => 'delivery',  'label' => 'Free Delivery Threshold','is_public' => true],
            ['key' => 'tax_rate',               'value' => '0',                'group' => 'financial', 'label' => 'Tax Rate %',            'is_public' => false],
            ['key' => 'max_delivery_radius',    'value' => '25',               'group' => 'delivery',  'label' => 'Max Delivery Radius km', 'is_public' => true],
            ['key' => 'otp_expiry_minutes',     'value' => '10',               'group' => 'security',  'label' => 'OTP Expiry Minutes',    'is_public' => false],
            ['key' => 'max_file_upload_mb',     'value' => '50',               'group' => 'media',     'label' => 'Max File Upload MB',    'is_public' => true],
            ['key' => 'story_duration_hours',   'value' => '24',               'group' => 'social',    'label' => 'Story Duration (hours)','is_public' => true],
            ['key' => 'feature_reels',          'value' => 'true',             'group' => 'features',  'label' => 'Enable Reels',          'is_public' => true],
            ['key' => 'feature_stories',        'value' => 'true',             'group' => 'features',  'label' => 'Enable Stories',        'is_public' => true],
            ['key' => 'feature_map',            'value' => 'true',             'group' => 'features',  'label' => 'Enable Map',            'is_public' => true],
            ['key' => 'feature_wallet',         'value' => 'true',             'group' => 'features',  'label' => 'Enable Wallet',         'is_public' => true],
            ['key' => 'maintenance_mode',       'value' => 'false',            'group' => 'system',    'label' => 'Maintenance Mode',      'is_public' => false],
            ['key' => 'timezone',               'value' => 'Africa/Accra',     'group' => 'general',   'label' => 'Platform Timezone',     'is_public' => true],
            ['key' => 'country',                'value' => 'GH',               'group' => 'general',   'label' => 'Default Country',       'is_public' => true],
            ['key' => 'support_email',          'value' => 'support@vezafud.com','group' => 'contact', 'label' => 'Support Email',         'is_public' => true],
            ['key' => 'support_phone',          'value' => '+233 XX XXX XXXX', 'group' => 'contact',   'label' => 'Support Phone',         'is_public' => true],
        ];

        PlatformSetting::upsert($settings, ['key'], ['value', 'group', 'label', 'is_public']);
    }
}

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        if (class_exists(\Spatie\Permission\Models\Role::class)) {
            $roles = ['super_admin', 'admin', 'accountant', 'delivery_manager', 'restaurant_manager', 'moderator', 'business_owner', 'food_creator', 'delivery_rider', 'customer'];
            foreach ($roles as $role) {
                \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'api']);
            }
        }
    }
}

class BusinessCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Restaurants',    'slug' => 'restaurants',    'icon' => '🍽️'],
            ['name' => 'Fast Food',      'slug' => 'fast-food',       'icon' => '🍔'],
            ['name' => 'Bars & Lounges', 'slug' => 'bars-lounges',    'icon' => '🍸'],
            ['name' => 'Bakeries',       'slug' => 'bakeries',        'icon' => '🥐'],
            ['name' => 'Chefs',          'slug' => 'chefs',           'icon' => '👨‍🍳'],
            ['name' => 'Caterers',       'slug' => 'caterers',        'icon' => '🍱'],
            ['name' => 'Food Trucks',    'slug' => 'food-trucks',     'icon' => '🚚'],
            ['name' => 'Coffee Shops',   'slug' => 'coffee-shops',    'icon' => '☕'],
            ['name' => 'Juice Bars',     'slug' => 'juice-bars',      'icon' => '🥤'],
            ['name' => 'Street Food',    'slug' => 'street-food',     'icon' => '🌮'],
            ['name' => 'Seafood',        'slug' => 'seafood',         'icon' => '🦞'],
            ['name' => 'Vegan & Healthy','slug' => 'vegan-healthy',   'icon' => '🥗'],
        ];
        foreach ($categories as $i => $cat) {
            BusinessCategory::updateOrCreate(['slug' => $cat['slug']], array_merge($cat, ['sort_order' => $i, 'is_active' => true]));
        }
    }
}

class RecipeCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'African',       'slug' => 'african',       'icon' => '🌍'],
            ['name' => 'Ghanaian',      'slug' => 'ghanaian',      'icon' => '🇬🇭'],
            ['name' => 'Asian',         'slug' => 'asian',         'icon' => '🍜'],
            ['name' => 'European',      'slug' => 'european',      'icon' => '🍝'],
            ['name' => 'Street Food',   'slug' => 'street-food',   'icon' => '🌮'],
            ['name' => 'Vegetarian',    'slug' => 'vegetarian',    'icon' => '🥦'],
            ['name' => 'Vegan',         'slug' => 'vegan',         'icon' => '🥗'],
            ['name' => 'Seafood',       'slug' => 'seafood',       'icon' => '🦞'],
            ['name' => 'Bakery',        'slug' => 'bakery',        'icon' => '🥐'],
            ['name' => 'Desserts',      'slug' => 'desserts',      'icon' => '🍰'],
            ['name' => 'Cocktails',     'slug' => 'cocktails',     'icon' => '🍹'],
            ['name' => 'BBQ',           'slug' => 'bbq',           'icon' => '🔥'],
            ['name' => 'Breakfast',     'slug' => 'breakfast',     'icon' => '🍳'],
            ['name' => 'Lunch',         'slug' => 'lunch',         'icon' => '🍱'],
            ['name' => 'Dinner',        'slug' => 'dinner',        'icon' => '🍽️'],
            ['name' => 'Quick Meals',   'slug' => 'quick-meals',   'icon' => '⚡'],
        ];
        foreach ($categories as $i => $cat) {
            RecipeCategory::updateOrCreate(['slug' => $cat['slug']], array_merge($cat, ['sort_order' => $i]));
        }
    }
}

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admins = [
            ['username' => 'superadmin', 'email' => 'superadmin@vezafud.com', 'account_type' => 'super_admin',   'name' => 'Super Admin'],
            ['username' => 'admin',      'email' => 'admin@vezafud.com',      'account_type' => 'admin',          'name' => 'Platform Admin'],
            ['username' => 'moderator',  'email' => 'mod@vezafud.com',        'account_type' => 'moderator',      'name' => 'Content Moderator'],
            ['username' => 'accountant', 'email' => 'finance@vezafud.com',    'account_type' => 'accountant',     'name' => 'Finance Manager'],
        ];

        foreach ($admins as $admin) {
            $user = User::updateOrCreate(['email' => $admin['email']], [
                'username'          => $admin['username'],
                'password_hash'     => Hash::make('Vezafud@Admin2024!'),
                'account_type'      => $admin['account_type'],
                'is_verified'       => true,
                'is_email_verified' => true,
                'status'            => 'active',
            ]);
            UserProfile::updateOrCreate(['user_id' => $user->id], ['full_name' => $admin['name'], 'country' => 'GH']);
            UserPrivacySetting::firstOrCreate(['user_id' => $user->id]);
            Wallet::firstOrCreate(['user_id' => $user->id]);
            RewardPoint::firstOrCreate(['user_id' => $user->id]);
        }
    }
}

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // Sample businesses
        $businesses = [
            ['name' => 'Osu Grill House',       'type' => 'restaurant', 'cuisine' => ['Ghanaian','Grill','American'],    'price_range' => 'moderate'],
            ['name' => "Mama Akua's Kitchen",   'type' => 'restaurant', 'cuisine' => ['Ghanaian','Traditional'],         'price_range' => 'budget'],
            ['name' => 'Tokyo Ramen Accra',     'type' => 'restaurant', 'cuisine' => ['Japanese','Asian','Ramen'],       'price_range' => 'moderate'],
            ['name' => 'La Terrasse Lounge',    'type' => 'lounge',     'cuisine' => ['Continental','Bar'],              'price_range' => 'expensive'],
            ['name' => 'Sweet Vibes Bakery',    'type' => 'bakery',     'cuisine' => ['Cakes','Pastry','Desserts'],      'price_range' => 'moderate'],
            ['name' => 'Labadi Seafood',        'type' => 'restaurant', 'cuisine' => ['Seafood','Grilled'],              'price_range' => 'expensive'],
            ['name' => 'The Vegan Spot',        'type' => 'restaurant', 'cuisine' => ['Vegan','Healthy'],                'price_range' => 'moderate'],
            ['name' => 'BBQ Nation Accra',      'type' => 'restaurant', 'cuisine' => ['BBQ','Chicken','Ribs'],           'price_range' => 'moderate'],
        ];

        $category = BusinessCategory::where('slug', 'restaurants')->first();

        foreach ($businesses as $biz) {
            $owner = User::where('username', 'admin')->first();
            $business = Business::firstOrCreate(['slug' => Str::slug($biz['name']) . '-gh'], [
                'owner_id'           => $owner->id,
                'category_id'        => $category?->id,
                'business_type'      => $biz['type'],
                'name'               => $biz['name'],
                'description'        => "Experience the best of {$biz['name']} in Accra.",
                'verification_status'=> 'verified',
                'price_range'        => $biz['price_range'],
                'cuisine_types'      => $biz['cuisine'],
                'is_active'          => true,
                'delivery_available' => true,
                'pickup_available'   => true,
                'rating_avg'         => round(mt_rand(43, 50) / 10, 1),
                'review_count'       => mt_rand(50, 500),
            ]);

            // Branch
            BusinessBranch::firstOrCreate(['business_id' => $business->id, 'is_main_branch' => true], [
                'branch_name'      => 'Main Branch',
                'address'          => 'Accra, Ghana',
                'city'             => 'Accra',
                'latitude'         => 5.5502 + (mt_rand(-100, 100) / 10000),
                'longitude'        => -0.2174 + (mt_rand(-100, 100) / 10000),
                'delivery_fee'     => 8.00,
                'delivery_radius'  => 10,
                'is_active'        => true,
            ]);

            // Hours
            for ($d = 0; $d <= 6; $d++) {
                BusinessHour::firstOrCreate(['business_id' => $business->id, 'day_of_week' => $d], [
                    'opens_at'  => '08:00:00',
                    'closes_at' => '22:00:00',
                    'is_closed' => false,
                ]);
            }

            // Menu
            $menu = Menu::firstOrCreate(['business_id' => $business->id, 'name' => 'Main Menu'], ['is_active' => true, 'menu_type' => 'main']);
            $cat = MenuCategory::firstOrCreate(['menu_id' => $menu->id, 'name' => 'Popular'], ['is_active' => true, 'sort_order' => 1]);

            $items = match ($biz['type']) {
                'bakery'  => [['name' => 'Birthday Cake (Custom)', 'price' => 150], ['name' => 'Assorted Cupcakes', 'price' => 5], ['name' => 'Croissant', 'price' => 8]],
                'lounge'  => [['name' => 'Premium Cocktail', 'price' => 45], ['name' => 'Bottle Service', 'price' => 250], ['name' => 'Finger Foods Platter', 'price' => 80]],
                default   => [['name' => 'Jollof Rice & Chicken', 'price' => 35], ['name' => 'Fried Rice & Beef', 'price' => 38], ['name' => 'Waakye Special', 'price' => 30], ['name' => 'Grilled Tilapia', 'price' => 55]],
            };

            foreach ($items as $item) {
                MenuItem::firstOrCreate(['menu_id' => $menu->id, 'name' => $item['name']], array_merge($item, [
                    'category_id'      => $cat->id,
                    'availability'     => 'available',
                    'preparation_time' => 20,
                    'order_count'      => mt_rand(10, 300),
                ]));
            }
        }
    }
}

class SpinWheelSeeder extends Seeder
{
    public function run(): void
    {
        $rewards = [
            ['name' => '50 Reward Points',    'reward_type' => 'points',        'points_value' => 50,  'probability_weight' => 20],
            ['name' => '100 Reward Points',   'reward_type' => 'points',        'points_value' => 100, 'probability_weight' => 15],
            ['name' => 'GH₵2 Cashback',      'reward_type' => 'cashback',      'reward_value' => 2,   'probability_weight' => 15],
            ['name' => 'GH₵5 Cashback',      'reward_type' => 'cashback',      'reward_value' => 5,   'probability_weight' => 10],
            ['name' => '10% Off Next Order',  'reward_type' => 'discount',      'reward_value' => 10,  'probability_weight' => 12],
            ['name' => 'Free Delivery',       'reward_type' => 'free_delivery', 'reward_value' => 0,   'probability_weight' => 8],
            ['name' => 'Try Again!',          'reward_type' => 'no_win',        'reward_value' => 0,   'probability_weight' => 20],
        ];

        foreach ($rewards as $reward) {
            SpinWheelReward::updateOrCreate(['name' => $reward['name']], array_merge($reward, ['is_active' => true]));
        }
    }
}

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $coupons = [
            ['code' => 'WELCOME10',    'title' => 'Welcome Discount',        'discount_type' => 'percentage', 'discount_value' => 10, 'minimum_order' => 30,  'per_user_limit' => 1],
            ['code' => 'FREESHIP',     'title' => 'Free Delivery',           'discount_type' => 'free_delivery', 'discount_value' => 0, 'minimum_order' => 50, 'per_user_limit' => 3],
            ['code' => 'SAVE5',        'title' => 'GH₵5 Off',              'discount_type' => 'fixed',      'discount_value' => 5,  'minimum_order' => 40,  'per_user_limit' => 2],
            ['code' => 'VEZAFUD20',    'title' => '20% Off — Limited Time', 'discount_type' => 'percentage', 'discount_value' => 20, 'minimum_order' => 60,  'per_user_limit' => 1, 'usage_limit' => 500],
        ];

        foreach ($coupons as $coupon) {
            Coupon::updateOrCreate(['code' => $coupon['code']], array_merge($coupon, ['is_active' => true, 'source' => 'platform', 'expires_at' => now()->addMonths(6)]));
        }
    }
}

class LoyaltyRewardSeeder extends Seeder
{
    public function run(): void
    {
        $rewards = [
            ['name' => '10% Discount Voucher', 'reward_type' => 'discount',       'points_required' => 200,  'reward_value' => 10],
            ['name' => 'Free Delivery x3',     'reward_type' => 'free_delivery',  'points_required' => 300,  'reward_value' => 0],
            ['name' => 'GH₵20 Cashback',      'reward_type' => 'cashback',       'points_required' => 500,  'reward_value' => 20],
            ['name' => 'Free Meal Voucher',    'reward_type' => 'free_item',      'points_required' => 1000, 'reward_value' => 50],
            ['name' => 'Premium Membership',   'reward_type' => 'premium',        'points_required' => 2000, 'reward_value' => 0],
        ];

        foreach ($rewards as $reward) {
            LoyaltyReward::updateOrCreate(['name' => $reward['name']], array_merge($reward, ['is_active' => true]));
        }
    }
}
