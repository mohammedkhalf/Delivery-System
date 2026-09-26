<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use App\Models\CustomerAddress;
use App\Models\Zone;
use App\Services\ZoneService;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\Builder\Contracts\LocationProvider as LocationProviderContract;
use Modules\Builder\Services\StorefrontContext;
use Modules\Builder\ValueObjects\Storefront\AddressDTO;
use Modules\Builder\ValueObjects\StorefrontScope;

class LocationProvider implements LocationProviderContract
{
    private const SESSION_KEY = 'builder.selected_location';

    public function __construct(
        private StorefrontContext $context,
        private ZoneService $zoneService,
    ) {
    }

    public function current(): ?array
    {
        $stored = Session::get(self::SESSION_KEY);
        $zoneId = $this->context->getZoneId();

        // Validate the stored selection still belongs in this storefront's
        // zone — covers the case where the user navigated to a different
        // storefront with a stale session entry.
        if ($stored && (int) ($stored['zone_id'] ?? 0) === (int) $zoneId) {
            return $stored;
        }

        // No valid stored selection — try to auto-resolve from the
        // logged-in customer's addresses (first one in the current zone).
        return $this->autoResolveFromSavedAddresses();
    }

    public function setCurrent(float $lat, float $lng, string $address, ?int $addressId = null): array
    {
        $zoneId = $this->context->getZoneId();

        if ($addressId) {
            // Saved-address path: the row IS the source of truth — load
            // its lat/lng/address rather than trusting whatever the client
            // sent. Verify the row still belongs to the active store's
            // zone (covers the case where the polygon was redrawn after
            // the row was saved).
            $row = $this->ownedAddress($addressId);
            if ((int) $row->zone_id !== (int) $zoneId) {
                throw ValidationException::withMessages([
                    'coordinates' => __('messages.service_not_available_in_this_area'),
                ]);
            }
            $payload = $this->savedSelectionPayload($row, $zoneId);
        } else {
            // Ad-hoc path (geolocation / map pick): validate the point
            // actually lies inside the polygon before persisting.
            $this->assertPointInStorefrontZone($lat, $lng, $zoneId);
            $payload = [
                'id'      => null,
                'lat'     => $lat,
                'lng'     => $lng,
                'address' => $address,
                'zone_id' => $zoneId,
                'label'   => null,
                'source'  => 'map',
            ];
        }

        Session::put(self::SESSION_KEY, $payload);
        return $payload;
    }

    public function clear(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    public function savedAddresses(): array
    {
        $userId = $this->context->getUserId();
        $zoneId = $this->context->getZoneId();

        if (!$userId || !$zoneId) {
            return [];
        }

        // Cheap index lookup on (user_id, zone_id) — addresses outside
        // this storefront's zone never reach the frontend.
        return CustomerAddress::query()
            ->where('user_id', $userId)
            ->where('zone_id', $zoneId)
            ->latest()
            ->get()
            ->map(fn (CustomerAddress $a) => $this->formatAddress($a))
            ->all();
    }

    public function addAddress(array $payload): array
    {
        $userId = $this->requireAuthenticatedUser();
        $zoneId = $this->context->getZoneId();
        $lat    = (float) ($payload['latitude'] ?? 0);
        $lng    = (float) ($payload['longitude'] ?? 0);

        $this->assertPointInStorefrontZone($lat, $lng, $zoneId);

        $address = new CustomerAddress();
        $address->user_id              = $userId;
        $address->zone_id              = $zoneId;
        $address->address_type         = $payload['address_type'] ?? 'Other';
        $address->contact_person_name  = $payload['contact_person_name'] ?? null;
        $address->contact_person_number = $payload['contact_person_number'] ?? null;
        $address->address              = $payload['address'] ?? '';
        $address->latitude             = $lat;
        $address->longitude            = $lng;
        $address->floor                = $payload['floor'] ?? null;
        $address->road                 = $payload['road']  ?? null;
        $address->house                = $payload['house'] ?? null;
        $address->save();

        return $this->formatAddress($address);
    }

    public function updateAddress(int $addressId, array $payload): array
    {
        $address = $this->ownedAddress($addressId);
        $zoneId  = $this->context->getZoneId();

        $lat = (float) ($payload['latitude']  ?? $address->latitude);
        $lng = (float) ($payload['longitude'] ?? $address->longitude);
        $this->assertPointInStorefrontZone($lat, $lng, $zoneId);

        $address->address_type         = $payload['address_type']         ?? $address->address_type;
        $address->contact_person_name  = $payload['contact_person_name']  ?? $address->contact_person_name;
        $address->contact_person_number = $payload['contact_person_number'] ?? $address->contact_person_number;
        $address->address              = $payload['address'] ?? $address->address;
        $address->latitude             = $lat;
        $address->longitude            = $lng;
        $address->floor                = \array_key_exists('floor', $payload) ? $payload['floor'] : $address->floor;
        $address->road                 = \array_key_exists('road',  $payload) ? $payload['road']  : $address->road;
        $address->house                = \array_key_exists('house', $payload) ? $payload['house'] : $address->house;
        $address->zone_id              = $zoneId; // re-anchor to the current zone
        $address->save();

        return $this->formatAddress($address);
    }

    public function deleteAddress(int $addressId): void
    {
        $this->ownedAddress($addressId)->delete();

        // If the deleted row was the currently selected location, clear it.
        $stored = Session::get(self::SESSION_KEY);
        if ($stored && (int) ($stored['id'] ?? 0) === $addressId) {
            $this->clear();
        }
    }

    public function storefrontZonePolygon(?StorefrontScope $scope = null): ?array
    {
        $zoneId = $scope?->regionId ?? $this->context->getZoneId();
        if (!$zoneId) {
            return null;
        }

        $zone = Zone::query()->find($zoneId);
        if (!$zone || !$zone->coordinates) {
            return null;
        }

        // Polygon stores [LineString[Point...]]; the outer ring is at [0].
        // toJson() emits [[lng, lat], ...] (GeoJSON convention) — flip to
        // {lat, lng} for the frontend.
        $area = \json_decode($zone->coordinates[0]->toJson(), true);
        $points = $area['coordinates'] ?? [];

        return collect($points)
            ->map(fn ($pt) => ['lat' => (float) $pt[1], 'lng' => (float) $pt[0]])
            ->values()
            ->all();
    }

    /* ─── helpers ─────────────────────────────────────────────── */

    private function assertPointInStorefrontZone(float $lat, float $lng, ?int $zoneId): void
    {
        if (!$zoneId) {
            throw ValidationException::withMessages([
                'coordinates' => __('messages.service_not_available_in_this_area'),
            ]);
        }

        $hit = Zone::query()
            ->whereContains('coordinates', new Point($lat, $lng, POINT_SRID))
            ->where('id', $zoneId)
            ->where('status', 1)
            ->exists();

        if (!$hit) {
            throw ValidationException::withMessages([
                'coordinates' => __('messages.service_not_available_in_this_area'),
            ]);
        }
    }

    private function requireAuthenticatedUser(): int
    {
        $userId = $this->context->getUserId();
        if (!$userId) {
            throw ValidationException::withMessages([
                'auth' => __('messages.unauthenticated'),
            ]);
        }
        return $userId;
    }

    private function ownedAddress(int $addressId): CustomerAddress
    {
        $userId = $this->requireAuthenticatedUser();

        $address = CustomerAddress::query()
            ->where('id', $addressId)
            ->where('user_id', $userId)
            ->first();

        if (!$address) {
            throw ValidationException::withMessages([
                'address_id' => __('messages.not_found'),
            ]);
        }
        return $address;
    }

    private function autoResolveFromSavedAddresses(): ?array
    {
        $userId = $this->context->getUserId();
        $zoneId = $this->context->getZoneId();
        if (!$userId || !$zoneId) {
            return null;
        }

        $address = CustomerAddress::query()
            ->where('user_id', $userId)
            ->where('zone_id', $zoneId)
            ->latest()
            ->first();

        if (!$address) {
            return null;
        }

        // Persist so subsequent requests are cheap.
        $payload = $this->savedSelectionPayload($address, $zoneId);
        Session::put(self::SESSION_KEY, $payload);
        return $payload;
    }

    /**
     * Shape used as `selectedLocation` Inertia prop. Carries every field
     * the checkout's "Delivery Info" card renders — including the optional
     * second-line details (street/road, house, floor) and the per-address
     * contact (which may differ from the auth profile when the customer
     * saved this address with a different recipient).
     *
     * Keep this in sync with `formatAddress()` (the My Addresses list
     * shape) and with `CheckoutAddressDisplay.jsx`'s field reads.
     */
    private function savedSelectionPayload(CustomerAddress $row, int $zoneId): array
    {
        return [
            'id'                    => (int) $row->id,
            'lat'                   => (float) $row->latitude,
            'lng'                   => (float) $row->longitude,
            'address'               => (string) $row->address,
            'zone_id'               => $zoneId,
            'label'                 => $row->address_type ?? 'Other',
            'address_type'          => $row->address_type,
            'street'                => $row->road,
            'road'                  => $row->road,
            'house'                 => $row->house,
            'floor'                 => $row->floor,
            'contact_person_name'   => $row->contact_person_name,
            'contact_person_number' => $row->contact_person_number,
            'source'                => 'saved',
        ];
    }

    private function formatAddress(CustomerAddress $a): array
    {
        return AddressDTO::fromArray([
            'id'                    => (int) $a->id,
            'address_type'          => $a->address_type,
            'address'               => (string) $a->address,
            'latitude'              => (float) $a->latitude,
            'longitude'             => (float) $a->longitude,
            'contact_person_name'   => $a->contact_person_name,
            'contact_person_number' => $a->contact_person_number,
            'floor'                 => $a->floor,
            'road'                  => $a->road,
            'house'                 => $a->house,
            'zone_id'               => (int) $a->zone_id,
        ])->toArray();
    }
}
