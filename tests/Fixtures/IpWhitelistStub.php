<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Test-only stand-in for the boilerplate's App\Http\Middleware\InternalIpWhitelist.
 *
 * Reads the same config keys at request time so the package's admin IP
 * whitelist behaviour can be exercised without the host application.
 */
class IpWhitelistStub
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('boilerplate.admin.ip_whitelist.enabled', false)) {
            return $next($request);
        }

        $cidrs = (array) config('boilerplate.admin.ip_whitelist.cidrs', []);

        if (IpUtils::checkIp((string) $request->ip(), $cidrs)) {
            return $next($request);
        }

        return response()->json(['message' => 'Forbidden.'], 403);
    }
}
