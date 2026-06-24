<?php

declare(strict_types=1);

use Mrsuner\ApiKeys\Tests\Fixtures\AuditSpy;

/*
 * Test-only stand-in for the boilerplate's audit_log() helper. The package
 * guards every call with function_exists('audit_log'); defining it here lets
 * the standalone test suite assert that audit events are emitted.
 */
if (! function_exists('audit_log')) {
    function audit_log(string $event, $auditable = null, array $payload = [])
    {
        AuditSpy::record($event, $auditable, $payload);

        return null;
    }
}
