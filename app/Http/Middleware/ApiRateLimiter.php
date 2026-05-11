<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter as RateLimiterFacade;

class ApiRateLimiter
{
    public function handle(Request $request, Closure $next, string $key = 'api', int $maxAttempts = 60, int $decayMinutes = 1)
    {
        $identifier = $request->user()?->id ?? $request->ip();
        $rateLimitKey = "{$key}:{$identifier}";

        if (RateLimiterFacade::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            $seconds = RateLimiterFacade::availableIn($rateLimitKey);
            return response()->json([
                'success' => false,
                'message' => "Too many requests. Please wait {$seconds} seconds.",
                'retry_after' => $seconds,
            ], 429);
        }

        RateLimiterFacade::hit($rateLimitKey, $decayMinutes * 60);

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', max(0, $maxAttempts - RateLimiterFacade::attempts($rateLimitKey)));
        return $response;
    }
}
