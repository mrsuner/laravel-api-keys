<?php

declare(strict_types=1);

namespace Mrsuner\ApiKeys\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Mrsuner\ApiKeys\Http\Resources\ApiKeyResource;
use Mrsuner\ApiKeys\Http\Resources\ApiKeyUsageLogResource;
use Mrsuner\ApiKeys\Models\ApiKey;
use Mrsuner\ApiKeys\Models\ApiKeyUsageLog;
use Mrsuner\ApiKeys\Services\ApiKeyService;

/**
 * Admin endpoints — operate across all users' keys. Never expose the plaintext
 * key or key_hash; only key_prefix, metadata and usage stats.
 */
class AdminApiKeyController extends Controller
{
    public function __construct(private readonly ApiKeyService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $keys = ApiKey::query()
            ->latest()
            ->paginate($this->resolvePerPage($request));

        return ApiKeyResource::collection($keys);
    }

    public function show(Request $request, ApiKey $key): JsonResponse
    {
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
            'api_key' => (new ApiKeyResource($key))->resolve($request),
            'usage'   => [
                'request_count' => $key->request_count,
                'last_used_at'  => $key->last_used_at?->toIso8601String(),
                'last_used_ip'  => $key->last_used_ip,
                'top_endpoints' => $topEndpoints,
                'recent_logs'   => ApiKeyUsageLogResource::collection($recentLogs)->resolve($request),
            ],
        ]);
    }

    public function destroy(Request $request, ApiKey $key): JsonResponse
    {
        $this->service->revoke($key, 'admin_revoked');

        $this->audit('api_key.admin_revoked', $key->user, [
            'metadata' => [
                'key_prefix'      => $key->key_prefix,
                'acting_admin_id' => $request->user()?->getKey(),
            ],
        ]);

        return $this->respondNoContent();
    }

    public function forUser(Request $request, string $user): AnonymousResourceCollection
    {
        $userModel = config('api-keys.user_model', \App\Models\User::class);

        /** @var Model $owner */
        $owner = $userModel::query()->findOrFail($user);

        $keys = ApiKey::query()
            ->where('user_id', $owner->getKey())
            ->latest()
            ->paginate($this->resolvePerPage($request));

        return ApiKeyResource::collection($keys);
    }
}
