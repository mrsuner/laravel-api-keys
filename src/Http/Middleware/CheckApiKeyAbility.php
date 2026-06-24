<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mrsuner\ApiKeys\Models\ApiKey;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces API key abilities, mirroring Sanctum's abilities middleware.
 *
 * If the request was not authenticated by an API key (no api_key attribute),
 * the check is skipped so the same route can also serve Sanctum tokens.
 */
class CheckApiKeyAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $key = $request->attributes->get('api_key');

        if (! $key instanceof ApiKey) {
            return $next($request);
        }

        foreach ($abilities as $ability) {
            if (! $key->can($ability)) {
                return response()->json(['message' => 'Insufficient API key permissions.'], 403);
            }
        }

        return $next($request);
    }
}
