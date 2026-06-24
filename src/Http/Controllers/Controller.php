<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Http\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Minimal self-contained base controller.
 *
 * Mirrors the response envelope used by the boilerplate's base controller so
 * the package's endpoints stay visually consistent with the host API, while
 * remaining installable and testable in isolation.
 */
abstract class Controller
{
    protected function respondOk(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->envelope($data, $message, 200);
    }

    protected function respondCreated(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->envelope($data, $message, 201);
    }

    protected function respondNoContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    protected function respondError(int $code, ?string $message = null, ?array $errors = null): JsonResponse
    {
        $payload = ['message' => $message ?? 'Error.'];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $code);
    }

    private function envelope(mixed $data, ?string $message, int $code): JsonResponse
    {
        $payload = [];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload, $code);
    }

    /**
     * Resolve a bounded per-page value (default 20, max 100).
     */
    protected function resolvePerPage(Request $request): int
    {
        $perPage = (int) $request->integer('per_page', 20);

        return min($perPage < 1 ? 20 : $perPage, 100);
    }

    /**
     * Emit an audit log entry when the host application provides the helper.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function audit(string $event, mixed $auditable = null, array $payload = []): void
    {
        if (function_exists('audit_log')) {
            audit_log($event, $auditable, $payload);
        }
    }
}
