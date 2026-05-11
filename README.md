# Vezafud Platform — Complete Technical Documentation

## Overview

**Vezafud** is a full-stack social food & hospitality platform built on **Laravel 12 / PHP 8.3**.

> **Slogan:** Eat. Discover. Connect.

---

## Architecture

```
Modular Monolith → Service-Oriented → Microservices (future)
```

### Stack
| Layer | Technology |
|---|---|
| Backend Framework | Laravel 12 / PHP 8.3 |
| Database | MySQL 8+ (InnoDB) |
| Cache / Queues | Redis |
| Real-time | Laravel Reverb (WebSockets) |
| Admin Panel | FilamentPHP v3 |
| Search | Elasticsearch / Typesense |
| Storage | Local → Cloudflare R2 / AWS S3 |
| Payments | Paystack · Flutterwave · Stripe · Mobile Money |
| SMS/OTP | Twilio |
| Maps | Google Maps API |
| Push | Firebase FCM |

---

## Project Structure

```
vezafud/
├── app/
│   ├── Console/Kernel.php               ← Cron schedule
│   ├── Events/                          ← Broadcast events
│   ├── Filament/
│   │   ├── Resources/                   ← Admin panel resources
│   │   └── Widgets/                     ← Dashboard widgets
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/                     ← API controllers
│   │   │   └── Admin/                   ← Admin REST controllers
│   │   └── Middleware/
│   ├── Jobs/                            ← Queue jobs
│   ├── Listeners/                       ← Event listeners
│   ├── Models/                          ← Eloquent models (60+)
│   ├── Providers/
│   └── Services/                        ← Business logic layer
├── database/
│   ├── migrations/                      ← 9 migration files
│   └── seeders/                         ← Full seed data
├── modules/                             ← Feature modules
│   ├── Auth/
│   ├── Users/
│   ├── Social/
│   ├── Recipes/
│   ├── Businesses/
│   ├── Orders/
│   ├── Delivery/
│   ├── Wallet/
│   ├── Messaging/
│   ├── Notifications/
│   ├── Admin/
│   └── Analytics/
├── routes/api.php                       ← All API routes
├── install.php                          ← Web installer
├── nginx.conf                           ← Production Nginx config
├── supervisor.conf                      ← Queue + Reverb processes
├── .env.example
└── composer.json
```

---

## Database Modules (9 migration files → 80+ tables)

| Module | Key Tables |
|---|---|
| Identity & Auth | users, user_profiles, user_privacy_settings, followers, blocked_users, sessions, devices, otp_verifications, social_accounts, login_attempts |
| Social Network | posts, post_media, post_likes, post_comments, comment_replies, post_shares, saved_posts, hashtags, stories, story_views, notifications, activities |
| Businesses | businesses, business_categories, business_branches, business_staff, business_hours, business_gallery, business_reviews, business_followers, business_verification |
| Menu System | menus, menu_categories, menu_items, menu_item_variants, menu_item_addons, menu_item_media, inventory_status, special_offers |
| Ordering | carts, cart_items, orders, order_items, order_status_logs, coupons, coupon_usage, refund_requests, transactions |
| Delivery | delivery_profiles, delivery_vehicles, delivery_zones, deliveries, delivery_tracking, delivery_status_logs, driver_earnings, driver_reviews |
| Wallet & Rewards | wallets, wallet_transactions, reward_points, reward_point_transactions, loyalty_rewards, loyalty_redemptions, cashback_logs, referral_rewards, wallet_topups, withdrawal_requests |
| Recipes | recipes, recipe_ingredients, recipe_steps, recipe_media, recipe_categories, recipe_likes, recipe_comments, recipe_ratings, saved_recipes |
| Bookings | reservations, event_bookings, chef_bookings, catering_requests |
| Messaging | conversations, conversation_participants, messages, message_reads |
| Analytics | user_analytics, business_analytics, order_analytics, engagement_analytics |
| Moderation | reports, moderation_logs, audit_logs, fraud_flags, banned_accounts, login_attempts |
| Platform | platform_settings, spin_wheel_rewards, spin_wheel_attempts |

---

## API Reference (Base: `/api/v1`)

### Authentication
```
POST /auth/register          Register new user
POST /auth/login             Login (email/phone/username)
POST /auth/social-login      Google/Facebook OAuth
POST /auth/send-otp          Send OTP
POST /auth/verify-otp        Verify OTP
POST /auth/forgot-password   Forgot password
POST /auth/reset-password    Reset password
POST /auth/logout            Logout [auth]
GET  /auth/me                Current user [auth]
```

### Social Feed
```
GET  /feed                   Home feed [auth]
GET  /feed/explore           Explore / discover
GET  /feed/reels             Food reels
GET  /feed/stories           Active stories [auth]
GET  /feed/trending          Trending hashtags
GET  /feed/suggested         Suggested users [auth]
```

### Posts
```
POST /posts                  Create post (with media) [auth]
GET  /posts/{uuid}           Get post
POST /posts/{post}/like      Toggle like [auth]
POST /posts/{post}/comment   Add comment [auth]
POST /posts/{post}/save      Toggle save [auth]
DELETE /posts/{post}         Delete post [auth]
```

### Businesses & Discovery
```
GET  /businesses             List/search businesses
GET  /businesses/nearby      Nearby businesses (lat, lng)
GET  /businesses/{slug}      Business profile
GET  /businesses/{slug}/menu Business menus
GET  /businesses/{slug}/reviews Business reviews
POST /businesses             Register business [auth]
POST /businesses/{slug}/follow Follow/unfollow [auth]
POST /businesses/{business}/review Post review [auth]
POST /businesses/{business}/reservation Make reservation [auth]
```

### Ordering
```
GET  /orders/cart            Get cart [auth]
POST /orders/cart/add        Add to cart [auth]
PUT  /orders/cart/item/{item} Update cart item [auth]
POST /orders/cart/coupon     Apply coupon [auth]
POST /orders/place           Place order [auth]
GET  /orders                 My orders [auth]
GET  /orders/{uuid}          Order details [auth]
GET  /orders/{uuid}/track    Live tracking [auth]
POST /orders/{order}/cancel  Cancel order [auth]
POST /orders/{order}/accept  Accept order (business) [auth]
POST /orders/{order}/reject  Reject order (business) [auth]
POST /orders/{order}/preparing Mark preparing [auth]
POST /orders/{order}/ready   Mark ready [auth]
```

### Delivery (Driver)
```
GET  /delivery/active        Active delivery [auth]
POST /delivery/location      Update location [auth]
POST /delivery/availability  Toggle status [auth]
POST /delivery/{d}/accept    Accept delivery [auth]
POST /delivery/{d}/pickup    Confirm pickup [auth]
POST /delivery/{d}/deliver   Confirm delivery [auth]
GET  /delivery/history       Delivery history [auth]
GET  /delivery/earnings      Driver earnings [auth]
```

### Wallet
```
GET  /wallet                 Wallet overview [auth]
GET  /wallet/transactions    Transaction history [auth]
POST /wallet/topup           Initialize top-up [auth]
POST /wallet/withdraw        Request withdrawal [auth]
POST /wallet/pin             Set transaction PIN [auth]
GET  /wallet/rewards         Loyalty rewards [auth]
POST /wallet/rewards/redeem  Redeem points [auth]
GET  /wallet/points-history  Points history [auth]
```

### Recipes
```
GET  /recipes                Browse recipes
GET  /recipes/categories     Recipe categories
GET  /recipes/{uuid}         Recipe details
POST /recipes                Create recipe [auth]
POST /recipes/{r}/like       Like/unlike [auth]
POST /recipes/{r}/save       Save/unsave [auth]
POST /recipes/{r}/comment    Add comment [auth]
POST /recipes/{r}/rate       Rate recipe [auth]
```

### Messages
```
GET  /messages               Conversations [auth]
POST /messages/start         Start conversation [auth]
GET  /messages/{uuid}        Get messages [auth]
POST /messages/{uuid}/send   Send message [auth]
```

### Payments (Webhooks)
```
POST /webhooks/paystack      Paystack webhook
POST /webhooks/flutterwave   Flutterwave webhook
POST /webhooks/wallet/paystack Wallet top-up webhook
```

### Admin (requires admin role)
```
GET  /admin/dashboard        Platform overview
GET  /admin/analytics        Full analytics
GET  /admin/users            Manage users
POST /admin/users/{u}/ban    Ban user
POST /admin/businesses/{b}/verify Verify business
GET  /admin/drivers          Manage drivers
GET  /admin/finance          Financial overview
GET  /admin/reports          Content reports
PUT  /admin/settings         Update platform settings
```

---

## Account Types

### Consumer
- `customer` — Regular food ordering user
- `food_creator` — Content creator with recipe/post publishing tools

### Business
- `restaurant` `bar` `lounge` `bakery` `chef` `caterer` `food_vendor` `coffee_shop` `juice_vendor` `food_truck`

### Logistics
- `delivery_rider` — Independent delivery driver
- `delivery_company` — Delivery fleet operator

### Administrative
- `super_admin` → Full platform control
- `admin` → General management
- `accountant` → Financial operations
- `delivery_manager` → Delivery ecosystem
- `restaurant_manager` → Business approvals
- `moderator` → Content moderation

---

## Real-Time Channels (Laravel Reverb)

| Channel | Event | Description |
|---|---|---|
| `private-orders.{orderId}` | `OrderStatusChanged` | Order status updates |
| `delivery.{deliveryId}` | `DriverLocationUpdated` | Live driver location |
| `private-conversation.{convId}` | `MessageSent` | New chat messages |
| `private-notifications.{userId}` | `NotificationSent` | Push notifications |

---

## Reward Points System

| Action | Points Earned |
|---|---|
| Place an order | 10 + floor(total/10) |
| Create a post | 2 |
| Upload a recipe | 5 |
| Write a review | 3 |
| Daily login | 1 |
| Refer a friend | 50 |
| Complete profile | 20 |
| Restaurant check-in | 3 |

### Loyalty Tiers
| Tier | Lifetime Points Required |
|---|---|
| 🥉 Bronze | 0 |
| 🥈 Silver | 500 |
| 🥇 Gold | 2,000 |
| 💎 Platinum | 5,000 |
| 👑 Diamond | 15,000 |

---

## Server Requirements

| Component | Minimum | Recommended |
|---|---|---|
| PHP | 8.3 | 8.3+ |
| MySQL | 8.0 | 8.0+ |
| Redis | 6.0 | 7.0+ |
| RAM | 2 GB | 4–8 GB |
| Storage | 20 GB | 100 GB+ |
| CPU | 2 cores | 4+ cores |

---

## Installation

### Quick Start
```bash
# 1. Clone / upload files to server
git clone https://github.com/yourorg/vezafud.git /var/www/vezafud
cd /var/www/vezafud

# 2. Install dependencies
composer install --optimize-autoloader --no-dev

# 3. Use the web installer
# Visit: https://yourdomain.com/install.php

# OR manual setup:
cp .env.example .env
php artisan key:generate
# Edit .env with DB credentials
php artisan migrate --force --seed
php artisan storage:link
php artisan config:cache
php artisan route:cache
```

### Queue Workers
```bash
# Via Supervisor (recommended)
sudo cp supervisor.conf /etc/supervisor/conf.d/vezafud.conf
sudo supervisorctl reread && sudo supervisorctl update

# Manual (dev)
php artisan queue:work redis --queue=default,notifications,delivery,payments
php artisan reverb:start
```

### Nginx
```bash
sudo cp nginx.conf /etc/nginx/sites-available/vezafud
# Edit domain name
sudo ln -s /etc/nginx/sites-available/vezafud /etc/nginx/sites-enabled/
sudo certbot --nginx -d yourdomain.com
sudo nginx -t && sudo systemctl reload nginx
```

---

## Monetization Revenue Streams

1. **Order Commissions** — 10% per order (configurable)
2. **Delivery Commissions** — 15% of delivery fee
3. **Featured Listings** — Paid business promotion
4. **Premium Subscriptions** — Enhanced business tools
5. **Sponsored Content** — In-feed ads
6. **Reservation Fees** — Per booking
7. **Creator Partnerships** — Revenue sharing
8. **Event Promotion** — Lounge & nightlife
9. **Analytics Subscriptions** — Business intelligence
10. **Spin-to-Win Campaigns** — Sponsored rewards

---

## Security Features

- ✅ JWT Authentication (Tymon JWT)
- ✅ OAuth 2.0 (Google, Facebook, Apple)
- ✅ Phone OTP via Twilio
- ✅ Password hashing (Argon2ID)
- ✅ Rate limiting (60 req/min API, 10 req/min auth)
- ✅ CSRF protection
- ✅ SQL injection prevention (Eloquent ORM)
- ✅ XSS protection headers
- ✅ Role-based permissions (Spatie)
- ✅ Full audit logging
- ✅ Fraud flag monitoring
- ✅ Device tracking
- ✅ Transaction PIN (wallet)
- ✅ OTP for transactions

---

## Growth Roadmap

### Phase 1 — MVP (Current)
- Social feed, posts, stories, reels
- Restaurant discovery & ordering
- Delivery with live tracking
- Wallet & rewards
- Admin panel

### Phase 2 — Expansion
- Multi-city rollout
- AI food recommendations (OpenAI)
- Creator monetization
- Mobile apps (React Native)
- Advanced analytics

### Phase 3 — Scale
- Microservices migration
- ML-powered personalization
- Regional expansion
- Enterprise API
- Franchise partnerships

---

*Built with ❤️ by the Vezafud team. Powered by Laravel 12.*
