<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next, string $role = 'admin')
    {
        $user = $request->user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);

        $adminRoles = ['super_admin', 'admin', 'accountant', 'delivery_manager', 'restaurant_manager', 'moderator'];
        if (!in_array($user->account_type, $adminRoles)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Admin access required.'], 403);
        }

        if ($role === 'super_admin' && $user->account_type !== 'super_admin') {
            return response()->json(['success' => false, 'message' => 'Super admin access required.'], 403);
        }

        return $next($request);
    }
}
