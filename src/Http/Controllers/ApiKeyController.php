<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Mrsuner\ApiKeys\Http\Requests\CreateApiKeyRequest;
use Mrsuner\ApiKeys\Http\Requests\RotateApiKeyRequest;
use Mrsuner\ApiKeys\Http\Requests\UpdateApiKeyRequest;
use Mrsuner\ApiKeys\Http\Resources\ApiKeyResource;
use Mrsuner\ApiKeys\Http\Resources\ApiKeyUsageLogResource;
use Mrsuner\ApiKeys\Models\ApiKey;
use Mrsuner\ApiKeys\Models\ApiKeyUsageLog;
use Mrsuner\ApiKeys\Services\ApiKeyService;
use Mrsuner\ApiKeys\ValueObjects\CreatedKey;

/**
 * User-facing endpoints — a user manages their OWN keys under auth:sanctum.
 */
class ApiKeyController extends Controller
{
    public function __construct(private readonly ApiKeyService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $keys = ApiKey::query()
            ->where('user_id', $request->user()->getKey())
            ->latest()
            ->paginate($this->resolvePerPage($request));

        return ApiKeyResource::collection($keys);
    }

    public function store(CreateApiKeyRequest $request): JsonResponse
    {
        $user = $request->user();

        $max = config('api-keys.max_keys_per_user');

        if ($max !== null) {
            $active = ApiKey::query()
                ->where('user_id', $user->getKey())
                ->where('is_active', true)
                ->count();

            if ($active >= (int) $max) {
                return $this->respondError(422, "Maximum of {$max} active API keys reached.");
            }
        }

        $created = $this->service->create($user, $request->validated());

        return $this->createdKeyResponse($request, $created);
    }

    public function show(Request $request, ApiKey $key): JsonResponse
    {
        $this->authorizeOwnership($request, $key);

        return $this->respondOk((new ApiKeyResource($key))->resolve($request));
    }

    public function update(UpdateApiKeyRequest $request, ApiKey $key): JsonResponse
    {
        $this->authorizeOwnership($request, $key);

        // environment is immutable; only these attributes can change.
        $key->fill($request->only(['name', 'abilities', 'rate_limit', 'expires_at']))->save();

        $this->audit('api_key.updated', $key->user, [
            'metadata' => ['key_prefix' => $key->key_prefix],
        ]);

        return $this->respondOk((new ApiKeyResource($key->refresh()))->resolve($request));
    }

    public function rotate(RotateApiKeyRequest $request, ApiKey $key): JsonResponse
    {
        $this->authorizeOwnership($request, $key);

        if (! $key->isUsable()) {
            return $this->respondError(422, 'Only active API keys can be rotated.');
        }

        $overlapMinutes = (int) $request->integer('overlap_minutes', 0);
        $overlapUntil = $overlapMinutes > 0 ? now()->addMinutes($overlapMinutes) : null;

        $created = $this->service->rotate($key, $overlapUntil);

        return $this->createdKeyResponse($request, $created);
    }

    public function destroy(Request $request, ApiKey $key): JsonResponse
    {
        $this->authorizeOwnership($request, $key);

        $this->service->revoke($key);

        return $this->respondNoContent();
    }

    public function usage(Request $request, ApiKey $key): JsonResponse
    {
        $this->authorizeOwnership($request, $key);

        $topEndpoints = ApiKeyUsageLog::query()
            ->where('api_key_id', $key->id)
            ->select('endpoint', DB::raw('count(*) as count'))
            ->groupBy('endpoint')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['endpoint' => $row->endpoint, 'count' => (int) $row->count]);

        $recentLogs = ApiKeyUsageLog::query()
            ->where('api_key_id', $key->id)
            ->latest('created_at')
            ->limit(20)
            ->get();

        return $this->respondOk([
            'request_count' => $key->request_count,
            'last_used_at'  => $key->last_used_at?->toIso8601String(),
            'last_used_ip'  => $key->last_used_ip,
            'top_endpoints' => $topEndpoints,
            'recent_logs'   => ApiKeyUsageLogResource::collection($recentLogs)->resolve($request),
        ]);
    }

    private function createdKeyResponse(Request $request, CreatedKey $created): JsonResponse
    {
        return $this->respondCreated([
            'key'     => $created->plaintext,
            'message' => 'Store this key securely. It will not be shown again.',
            'api_key' => (new ApiKeyResource($created->model))->resolve($request),
        ]);
    }

    private function authorizeOwnership(Request $request, ApiKey $key): void
    {
        abort_if($key->user_id !== $request->user()->getKey(), 403, 'This API key does not belong to you.');
    }
}
