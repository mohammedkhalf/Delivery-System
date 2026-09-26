<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Zone;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Illuminate\Support\Facades\Validator;
class ZoneController extends Controller
{
    public function get_zones()
    {
        $zones = Zone::where('status', 1)
            ->with([
                'modules' => function ($query) {
                    $query->withPivot([
                        'per_km_shipping_charge',
                        'minimum_shipping_charge',
                        'maximum_shipping_charge',
                        'maximum_cod_order_amount',
                        'delivery_charge_type',
                        'fixed_shipping_charge',
                        'additional_delivery_option_status',
                        'minimum_delivery_time',
                        'minimum_delivery_charge',
                    ]);
                },
                'moduleDeliveryOptions',
            ])
            ->get();

        foreach ($zones as $zone) {
            $area = json_decode($zone->coordinates[0]->toJson(), true);
            $zone['formated_coordinates'] = Helpers::format_coordiantes($area['coordinates']);

            $matrix = [];
            // Send zone related saver delivery type to the api
            foreach ($zone->moduleDeliveryOptions as $option) {
                $matrix[(int) $option->module_id][(string) $option->delivery_type] = [
                    'id'                   => (int) $option->id,
                    'delivery_type'        => (string) $option->delivery_type,
                    'extra_charge'         => (float) ($option->extra_charge ?? 0),
                    'reduce_charge'        => (float) ($option->reduce_charge ?? 0),
                    'add_delivery_time'    => (int) ($option->getRawOriginal('add_delivery_time') ?? 0),
                    'reduce_delivery_time' => (int) ($option->getRawOriginal('reduce_delivery_time') ?? 0),
                ];
            }
            $zone->unsetRelation('moduleDeliveryOptions');
            $zone->setAttribute('delivery_options_matrix', $matrix);
        }

        return response()->json($zones, 200);
    }

    public function zonesCheck(Request $request){
        $validator = Validator::make($request->all(), [
            'lat' => 'required',
            'lng' => 'required',
            'zone_id' => 'required',
        ]);

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $zone = Zone::where('id',$request->zone_id)->whereContains('coordinates', new Point($request->lat, $request->lng, POINT_SRID))->exists();

        return response()->json($zone, 200);

    }

}
