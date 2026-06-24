<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Mrsuner\ApiKeys\Jobs\TrackApiKeyUsage;
use Mrsuner\ApiKeys\Models\ApiKey;
use Mrsuner\ApiKeys\Services\ApiKeyService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves a developer API key into the request's authenticated user, leaving
 * Sanctum's own token handling untouched.
 *
 * Sits alongside (not replacing) auth:sanctum. A bearer token that does not
 * match the configured key prefix falls straight through to the next
 * middleware, so the same route can accept both a Sanctum token and an API key.
 */
class AuthenticateWithApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        // Let non-API-key tokens (e.g. Sanctum's) fall through untouched.
        if (! $bearer || ! $this->looksLikeApiKey($bearer)) {
            return $next($request);
        }

        $key = app(ApiKeyService::class)->resolve($bearer);

        if (! $key || ! $key->isUsable()) {
            return response()->json(['message' => 'Invalid or revoked API key.'], 401);
        }

        if (($retryAfter = $this->rateLimitRetryAfter($key)) !== null) {
            return response()->json(['message' => 'Rate limit exceeded.'], 429)
                ->header('Retry-After', (string) $retryAfter);
        }

        // Make auth()->user() resolve to the key owner downstream.
        Auth::setUser($key->user);

        // Expose the key so controllers / the ability middleware can inspect it.
        $request->attributes->set('api_key', $key);
        $request->attributes->set('api_key_started_at', microtime(true));

        return $next($request);
    }

    /**
     * Dispatch usage tracking after the response is sent — never on the request
     * path. Runs in terminate() so the final status code and timing are known.
     */
    public function terminate(Request $request, Response $response): void
    {
        $key = $request->attributes->get('api_key');

        if (! $key instanceof ApiKey) {
            return;
        }

        $startedAt = $request->attributes->get('api_key_started_at');
        $responseTimeMs = is_float($startedAt)
            ? (int) round((microtime(true) - $startedAt) * 1000)
            : null;

        // terminate() already runs after the response is sent, so this never
        // adds latency to the request — no afterResponse() wrapper needed.
        TrackApiKeyUsage::dispatch(
            $key->id,
            $request->method(),
            $this->normalizeEndpoint($request),
            (string) $request->ip(),
            $response->getStatusCode(),
            $responseTimeMs,
        );
    }

    private function looksLikeApiKey(string $token): bool
    {
        $prefix = (string) config('api-keys.key_prefix', 'sk');

        return str_starts_with($token, $prefix.'_');
    }

    /**
     * Hit the per-key bucket. Returns the seconds-to-wait when the key is over
     * its limit, or null when the request is allowed.
     */
    private function rateLimitRetryAfter(ApiKey $key): ?int
    {
        $limit    = $key->effectiveRateLimit();
        $cacheKey = 'api_key_rl:'.$key->id;

        if (RateLimiter::tooManyAttempts($cacheKey, $limit['max_attempts'])) {
            return RateLimiter::availableIn($cacheKey);
        }

        RateLimiter::hit($cacheKey, $limit['decay_seconds']);

        return null;
    }

    /**
     * Normalise the path so usage logs don't explode in cardinality:
     * /v1/orders/42 -> /v1/orders/{id}
     */
    private function normalizeEndpoint(Request $request): string
    {
        return '/'.preg_replace('#/\d+#', '/{id}', $request->path());
    }
}
