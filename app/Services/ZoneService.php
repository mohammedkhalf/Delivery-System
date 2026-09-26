<?php

namespace App\Services;

use App\Models\Module;
use App\Models\ModuleZoneDeliveryOption;
use MatanYadaev\EloquentSpatial\Objects\LineString;
use MatanYadaev\EloquentSpatial\Objects\Point;
use MatanYadaev\EloquentSpatial\Objects\Polygon;

class ZoneService
{

    public function getAddData(Object $request, int|string $zoneId): array
    {
        $value = $request['coordinates'];


        foreach(explode('),(',trim($value,'()')) as $index=>$single_array){
            if($index == 0)
            {
                $lastCord = explode(',',$single_array);
            }
            $coords = explode(',',$single_array);

            $polygon[] = new Point($coords[0], $coords[1]);
        }
        $polygon[] = new Point($lastCord[0], $lastCord[1]);
        return [
            'name' => $request->name[array_search('default', $request->lang)],
            'display_name' => $request->display_name[array_search('default', $request->lang)],
            'coordinates' => new Polygon([new LineString($polygon)]),
            'store_wise_topic' => 'zone_'.$zoneId.'_store',
            'customer_wise_topic' => 'zone_'.$zoneId.'_customer',
            'deliveryman_wise_topic' => 'zone_'.$zoneId.'_delivery_man',
            'rider_wise_topic' => 'zone_'.$zoneId.'_rider',
            'cash_on_delivery' => $request->cash_on_delivery?1:0,
            'digital_payment' => $request->digital_payment?1:0,
        ];
    }

    public function getUpdateData(Object $request, int|string $zoneId): array
    {
        $value = $request['coordinates'];

        foreach(explode('),(',trim($value,'()')) as $index=>$single_array){
            if($index == 0)
            {
                $lastCord = explode(',',$single_array);
            }
            $coords = explode(',',$single_array);

            $polygon[] = new Point($coords[0], $coords[1]);
        }
        $polygon[] = new Point($lastCord[0], $lastCord[1]);
        return [
            'name' => $request->name[array_search('default', $request->lang)],
            'display_name' => $request->display_name[array_search('default', $request->lang)],
            'store_wise_topic' => 'zone_'.$zoneId.'_store',
            'customer_wise_topic' => 'zone_'.$zoneId.'_customer',
            'deliveryman_wise_topic' => 'zone_'.$zoneId.'_delivery_man',
            'rider_wise_topic' => 'zone_'.$zoneId.'_rider',
            'coordinates' => new Polygon([new LineString($polygon)]),
        ];
    }
    public function getZoneModuleSetupData(Object $request): array
    {
        return [
            'cash_on_delivery' => $request->cash_on_delivery?1:0,
            'digital_payment' => $request->digital_payment?1:0,
            'offline_payment' => $request->offline_payment?1:0,
            'increased_delivery_fee' => $request->increased_delivery_fee ?? 0,
            'increased_delivery_fee_status' => $request->increased_delivery_fee_status ?? 0,
            'increase_delivery_charge_message' => $request->increase_delivery_charge_message ?? null,
        ];
    }

    public function formatCoordinates(array $coordinates): array
    {
        $data = [];
        foreach ($coordinates as $coordinate) {
            $data[] = (object)['lat' => $coordinate[1], 'lng' => $coordinate[0]];
        }
        return $data;
    }

    public function formatZoneCoordinates(object $zones): array
    {
        $data = [];
        foreach($zones as $zone)
        {
            $area = json_decode($zone->coordinates[0]->toJson(),true);
            $data[] = self::formatCoordinates(coordinates: $area['coordinates']);
        }
        return $data;
    }
    // Saver delivery time normalize version
    public function extractSaverOptionsAndNormalisePivot(array &$moduleData, array $selectedModules): array
    {
        $extracted = [];
        $allowedTypes = [ModuleZoneDeliveryOption::TYPE_EXPRESS, ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY];

        foreach ($moduleData as $moduleId => $entry) {
            if (!in_array($moduleId, $selectedModules)) {
                continue;
            }

            $deliveryTypes = $entry['delivery_types'] ?? [];
            unset($moduleData[$moduleId]['delivery_types']);

            $status = (int) ($entry['additional_delivery_option_status'] ?? 0) === 1;
            $moduleData[$moduleId]['additional_delivery_option_status'] = $status ? 1 : 0;

            if ($status) {
                $minutes = ModuleZoneDeliveryOption::pairToMinutes([
                    'value' => $entry['minimum_delivery_time']      ?? 0,
                    'unit'  => $entry['minimum_delivery_time_unit'] ?? 'min',
                ]);
                $moduleData[$moduleId]['minimum_delivery_time'] = $minutes;
                $moduleData[$moduleId]['minimum_delivery_charge'] = is_numeric($entry['minimum_delivery_charge'] ?? null)
                    ? (float) $entry['minimum_delivery_charge']
                    : null;
            } else {
                $moduleData[$moduleId]['minimum_delivery_time'] = null;
                $moduleData[$moduleId]['minimum_delivery_charge'] = null;
            }
            unset($moduleData[$moduleId]['minimum_delivery_time_unit']);

            $perType = [];
            foreach ($allowedTypes as $type) {
                $row = $deliveryTypes[$type] ?? [];
                $perType[$type] = [
                    'extra_charge'         => is_numeric($row['extra_charge'] ?? null) ? (float) $row['extra_charge'] : null,
                    'reduce_charge'        => is_numeric($row['reduce_charge'] ?? null) ? (float) $row['reduce_charge'] : null,
                    'add_delivery_time'    => ModuleZoneDeliveryOption::pairToMinutes([
                        'value' => $row['add_delivery_time']      ?? 0,
                        'unit'  => $row['add_delivery_time_unit'] ?? 'min',
                    ]),
                    'reduce_delivery_time' => ModuleZoneDeliveryOption::pairToMinutes([
                        'value' => $row['reduce_delivery_time']      ?? 0,
                        'unit'  => $row['reduce_delivery_time_unit'] ?? 'min',
                    ]),
                ];
            }

            $extracted[$moduleId] = [
                'enabled'                => $status,
                'minimum_delivery_time'  => $moduleData[$moduleId]['minimum_delivery_time'],
                'delivery_types'         => $perType,
                'delivery_charge_type'   => $entry['delivery_charge_type']    ?? null,
                'fixed_shipping_charge'  => is_numeric($entry['fixed_shipping_charge'] ?? null) ? (float) $entry['fixed_shipping_charge'] : null,
                'maximum_shipping_charge' => is_numeric($entry['maximum_shipping_charge'] ?? null) ? (float) $entry['maximum_shipping_charge'] : null,
            ];
        }

        return $extracted;
    }
    // Validate module wise saver delivery type settings
    public function validateModuleSaverSetup(array $saverData): array
    {
        $errors = [];
        foreach ($saverData as $moduleId => $payload) {
            if (!($payload['enabled'] ?? false)) {
                continue;
            }
            $express = $payload['delivery_types'][ModuleZoneDeliveryOption::TYPE_EXPRESS]        ?? [];
            $delayed = $payload['delivery_types'][ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY] ?? [];

            if ((float) ($express['extra_charge'] ?? 0) <= 0 || (int) ($express['reduce_delivery_time'] ?? 0) <= 0) {
                $errors[$moduleId] = 'express_required';
                continue;
            }
            if ((int) ($payload['minimum_delivery_time'] ?? 0) < (int) ($express['reduce_delivery_time'] ?? 0)) {
                $errors[$moduleId] = 'min_time_lt_reduce_time';
                continue;
            }
            if ((float) ($delayed['reduce_charge'] ?? 0) <= 0 || (int) ($delayed['add_delivery_time'] ?? 0) <= 0) {
                $errors[$moduleId] = 'slightly_delay_required';
                continue;
            }

            $reduceCharge = (float) ($delayed['reduce_charge'] ?? 0);
            $cap = $payload['delivery_charge_type'] === 'fixed'
                ? $payload['fixed_shipping_charge']
                : $payload['maximum_shipping_charge'];

            if ($cap !== null && $cap > 0 && $reduceCharge > $cap) {
                $errors[$moduleId] = 'reduce_charge_exceeds_max';
            }
        }
        return $errors;
    }
    // Update saver delivery type 
    public function upsertModuleSaverOptions(int $moduleId, int $zoneId, array $payload): void
    {
        $allowedTypes = [
            ModuleZoneDeliveryOption::TYPE_STANDARD,
            ModuleZoneDeliveryOption::TYPE_EXPRESS,
            ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY,
        ];

        if (!($payload['enabled'] ?? false)) {
            ModuleZoneDeliveryOption::for($moduleId, $zoneId)->delete();
            return;
        }

        foreach ($allowedTypes as $type) {
            $row = $payload['delivery_types'][$type] ?? [];
            ModuleZoneDeliveryOption::updateOrCreate(
                ['module_id' => $moduleId, 'zone_id' => $zoneId, 'delivery_type' => $type],
                [
                    'extra_charge'         => $row['extra_charge']         ?? null,
                    'reduce_charge'        => $row['reduce_charge']        ?? null,
                    'add_delivery_time'    => $row['add_delivery_time']    ?? null,
                    'reduce_delivery_time' => $row['reduce_delivery_time'] ?? null,
                ]
            );
        }
    }

    public function checkModuleDeliveryCharge(array $moduleData, array $selectedModules): array
    {
        $serviceModuleIds = Module::whereIn('id', $selectedModules)
            ->where('module_type', 'service')
            ->pluck('id')
            ->all();

        foreach ($moduleData as $moduleId => $data) {
            if (in_array($moduleId, $selectedModules) && !in_array((int) $moduleId, $serviceModuleIds)) {
                $type = $data['delivery_charge_type'] ?? null;
    
                if ($type === 'fixed') {
                    if (empty($data['fixed_shipping_charge'])) {
                        return ['flag' => 'fixed_required', 'module_id' => $moduleId];
                    }
                } elseif ($type === 'distance') {
                    if (empty($data['per_km_shipping_charge']) || empty($data['minimum_shipping_charge'])) {
                        return ['flag' => 'distance_required', 'module_id' => $moduleId];
                    }
    
                    if (
                        isset($data['maximum_shipping_charge']) &&
                        is_numeric($data['maximum_shipping_charge']) &&
                        is_numeric($data['minimum_shipping_charge']) &&
                        (float)$data['maximum_shipping_charge'] < (float)$data['minimum_shipping_charge']
                    ) {
                        return ['flag' => 'max_delivery_charge', 'module_id' => $moduleId];
                    }
                } else {
                    return ['flag' => 'unknown_type', 'module_id' => $moduleId];
                }
            }
        }
        return [];
    }

}
