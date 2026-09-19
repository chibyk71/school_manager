<?php

namespace App\Http\Controllers;

use App\Http\Requests\Address\StoreAddressRequest;
use App\Http\Requests\Address\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Models\School;
use App\Traits\HasAddress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Single reusable Address HTTP controller (Phase 4).
 *
 * Owner resolution uses a controlled allowlist — never arbitrary class names from input.
 * Authorization is always against the owner (view for reads, update for mutations).
 * Address rows are always resolved through the owner's relationship (never Address::find alone).
 */
class AddressController extends Controller
{
    /**
     * Controlled owner alias → model class mapping.
     * Only these aliases are accepted from the client.
     *
     * @var array<string, class-string<Model>>
     */
    private const OWNER_MAP = [
        'school' => School::class,
        // Future owners (profile, staff, etc.) register here deliberately.
    ];

    /**
     * GET /addresses/{owner}/{ownerId}
     */
    public function index(Request $request, string $owner, string $ownerId): AnonymousResourceCollection
    {
        $ownerModel = $this->resolveOwner($owner, $ownerId);
        Gate::authorize('view', $ownerModel);

        $addresses = $ownerModel->addresses()
            ->with(['country', 'state', 'city'])
            ->orderByDesc('is_primary')
            ->orderBy('created_at')
            ->get();

        return AddressResource::collection($addresses);
    }

    /**
     * POST /addresses/{owner}/{ownerId}
     */
    public function store(StoreAddressRequest $request, string $owner, string $ownerId): JsonResponse
    {
        $ownerModel = $this->resolveOwner($owner, $ownerId);
        Gate::authorize('update', $ownerModel);

        $data = $request->validated();
        $isPrimary = (bool) ($data['is_primary'] ?? false);
        unset($data['is_primary']);

        $address = $ownerModel->addAddress($data, $isPrimary);
        $address->load(['country', 'state', 'city']);

        return (new AddressResource($address))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PATCH /addresses/{owner}/{ownerId}/{address}
     */
    public function update(
        UpdateAddressRequest $request,
        string $owner,
        string $ownerId,
        string $address
    ): AddressResource {
        $ownerModel = $this->resolveOwner($owner, $ownerId);
        Gate::authorize('update', $ownerModel);

        $updated = $ownerModel->updateAddress($address, $request->validated());
        $updated->load(['country', 'state', 'city']);

        return new AddressResource($updated);
    }

    /**
     * DELETE /addresses/{owner}/{ownerId}/{address}
     */
    public function destroy(string $owner, string $ownerId, string $address): JsonResponse
    {
        $ownerModel = $this->resolveOwner($owner, $ownerId);
        Gate::authorize('update', $ownerModel);

        $ownerModel->deleteAddress($address);

        return response()->json(null, 204);
    }

    /**
     * POST /addresses/{owner}/{ownerId}/{address}/primary
     */
    public function setPrimary(string $owner, string $ownerId, string $address): AddressResource
    {
        $ownerModel = $this->resolveOwner($owner, $ownerId);
        Gate::authorize('update', $ownerModel);

        $primary = $ownerModel->setPrimaryAddress($address);
        $primary->load(['country', 'state', 'city']);

        return new AddressResource($primary);
    }

    /**
     * DELETE /addresses/{owner}/{ownerId}/{address}/primary
     *
     * Unsets primary on the given address only when it is currently primary.
     * If the address is not primary, the zero-primary state is left unchanged.
     * Spec allows zero primaries; unsetting clears all primaries for the owner
     * when the target is (or was) primary — HasAddress::unsetPrimaryAddress clears all.
     */
    public function unsetPrimary(string $owner, string $ownerId, string $address): JsonResponse
    {
        $ownerModel = $this->resolveOwner($owner, $ownerId);
        Gate::authorize('update', $ownerModel);

        // Resolve through owner to enforce ownership isolation.
        $target = $ownerModel->addresses()->withoutGlobalScopes()->whereKey($address)->firstOrFail();

        if ($target->is_primary) {
            $ownerModel->unsetPrimaryAddress();
        }

        return response()->json(null, 204);
    }

    /**
     * Resolve a persisted owner from controlled alias + id.
     *
     * @throws NotFoundHttpException
     * @throws ValidationException
     */
    private function resolveOwner(string $ownerAlias, string $ownerId): Model
    {
        $alias = strtolower(trim($ownerAlias));

        if (! array_key_exists($alias, self::OWNER_MAP)) {
            throw ValidationException::withMessages([
                'owner' => ["Unsupported address owner alias [{$ownerAlias}]."],
            ]);
        }

        $class = self::OWNER_MAP[$alias];

        if (! in_array(HasAddress::class, class_uses_recursive($class), true)) {
            throw ValidationException::withMessages([
                'owner' => ["Owner [{$ownerAlias}] does not support addresses."],
            ]);
        }

        /** @var Model $model */
        $model = $class::query()->whereKey($ownerId)->firstOrFail();

        return $model;
    }
}
