<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Tests\Fixtures;

/**
 * Captures audit_log() calls during tests so the package's audit hooks can be
 * asserted without the boilerplate's real helper present.
 */
final class AuditSpy
{
    /** @var array<int, array{event: string, auditable: mixed, payload: array}> */
    public static array $events = [];

    public static function reset(): void
    {
        self::$events = [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function record(string $event, mixed $auditable, array $payload): void
    {
        self::$events[] = ['event' => $event, 'auditable' => $auditable, 'payload' => $payload];
    }

    /**
     * @return array<int, array{event: string, auditable: mixed, payload: array}>
     */
    public static function for(string $event): array
    {
        return array_values(array_filter(self::$events, fn ($e) => $e['event'] === $event));
    }

    public static function has(string $event): bool
    {
        return self::for($event) !== [];
    }
}
