<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        if ($request->user() && !$request->is('api/v1/delivery/location')) {
            $request->user()->updateQuietly(['last_active_at' => now()]);
        }
        return $response;
    }
}
