<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Tests\Unit;

use Mrsuner\ApiKeys\ApiKeyServiceProvider;
use Mrsuner\ApiKeys\Tests\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use ReflectionMethod;

class ApiKeyServiceProviderTest extends TestCase
{
    public function test_standalone_admin_stack_requires_admin_ability(): void
    {
        config()->set('api-keys.admin_route.middleware', null);

        $method = new ReflectionMethod(ApiKeyServiceProvider::class, 'resolveAdminMiddleware');
        $method->setAccessible(true);

        $middleware = $method->invoke(new ApiKeyServiceProvider(app()));

        $this->assertSame(['auth:sanctum', 'ability:admin'], $middleware);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_boilerplate_admin_stack_includes_live_admin_access_middleware(): void
    {
        eval('namespace App\\Http\\Middleware { class InternalIpWhitelist {} class EnsureAdminAccess {} }');

        config()->set('api-keys.admin_route.middleware', null);

        $method = new ReflectionMethod(ApiKeyServiceProvider::class, 'resolveAdminMiddleware');
        $method->setAccessible(true);

        $middleware = $method->invoke(new ApiKeyServiceProvider(app()));

        $this->assertSame([
            'throttle:60,1',
            'App\\Http\\Middleware\\InternalIpWhitelist',
            'auth:sanctum',
            'ability:admin',
            'App\\Http\\Middleware\\EnsureAdminAccess',
        ], $middleware);
    }
}
