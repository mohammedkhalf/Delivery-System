<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Models\Item;
use App\Models\User;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Store;
use App\Models\Review;
use App\Models\Wishlist;
use App\Scopes\ZoneScope;
use App\Models\DeliveryMan;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\OrderTransaction;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Modules\RideShare\Entities\UserManagement\Rider;

class DashboardController extends Controller
{

    public function __construct()
    {
        DB::statement("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");
    }

    private function buildParams(Request $request): array
    {
        return [
            'zone_id' => $request['zone_id'] ?? 'all',
            'module_id' => Config::get('module.current_module_id'),
            'statistics_type' => $request['statistics_type'] ?? 'overall',
            'user_overview' => $request['user_overview'] ?? 'overall',
            'commission_overview' => $request['commission_overview'] ?? 'this_year',
            'business_overview' => $request['business_overview'] ?? 'overall',
        ];
    }

    private function updateDashParam(string $key, $value): array
    {
        $params = session('dash_params');
        $params[$key] = $value;
        session()->put('dash_params', $params);
        return $params;
    }

    private function statNewDateCase(string $column, $module_id): array
    {
        $params = session('dash_params');
        $type = ($module_id && in_array($params['statistics_type'], ['today', 'this_year', 'this_month', 'this_week']))
            ? $params['statistics_type'] : 'overall';

        return match ($type) {
            'today' => ["DATE($column) = ?", [Carbon::now()->format('Y-m-d')]],
            'this_year' => ["YEAR($column) = ?", [now()->format('Y')]],
            'this_month' => ["MONTH($column) = ? AND YEAR($column) = ?", [now()->format('m'), now()->format('Y')]],
            'this_week' => ["$column BETWEEN ? AND ?", [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]],
            default => ["DATE($column) >= ?", [now()->subDays(30)->format('Y-m-d')]],
        };
    }

    public function user_dashboard(Request $request)
    {
        $params = $this->buildParams($request);

        session()->put('dash_params', $params);
        $data = self::dashboard_data($request);
        $total_sell = $data['total_sell'];
        $commission = $data['commission'];
        $delivery_commission = $data['delivery_commission'];
        $customers = User::zone($params['zone_id'])->take(2)->get();

        $delivery_man = DeliveryMan::with('last_location')->when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->where('zone_id', $params['zone_id']);
        })
            ->Zonewise()
            ->limit(2)->get('image');

        $last30 = now()->subDays(30)->format('Y-m-d');
        $dmStats = DeliveryMan::when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->where('zone_id', $params['zone_id']);
        })
            ->Zonewise()
            ->selectRaw("
                SUM(CASE WHEN active = 1 AND application_status = 'approved' THEN 1 ELSE 0 END) as active_deliveryman,
                SUM(CASE WHEN application_status = 'approved' AND active = 0 THEN 1 ELSE 0 END) as inactive_deliveryman,
                SUM(CASE WHEN application_status = 'approved' AND status = 0 THEN 1 ELSE 0 END) as blocked_deliveryman,
                SUM(CASE WHEN application_status = 'approved' AND DATE(created_at) >= ? THEN 1 ELSE 0 END) as newly_joined_deliveryman
            ", [$last30])
            ->first();

        $active_deliveryman = (int) $dmStats->active_deliveryman;
        $inactive_deliveryman = (int) $dmStats->inactive_deliveryman;
        $blocked_deliveryman = (int) $dmStats->blocked_deliveryman;
        $newly_joined_deliveryman = (int) $dmStats->newly_joined_deliveryman;

        $reviewStats = Review::when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->whereHas('item.store', function ($query) use ($params) {
                return $query->where('zone_id', $params['zone_id']);
            });
        })->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN rating IN (4, 5) THEN 1 ELSE 0 END) as positive,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as good,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as neutral,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as negative
        ")->first();

        $reviews = (int) $reviewStats->total;
        $positive_reviews = (int) $reviewStats->positive;
        $good_reviews = (int) $reviewStats->good;
        $neutral_reviews = (int) $reviewStats->neutral;
        $negative_reviews = (int) $reviewStats->negative;

        $number = 12;

        $users = User::zone($params['zone_id'])
            ->select(
                DB::raw('(count(id)) as total'),
                DB::raw('YEAR(created_at) year, MONTH(created_at) month')
            )
            ->whereBetween('created_at', [Carbon::parse(now())->startOfYear(), Carbon::parse(now())->endOfYear()])
            ->groupBy('year', 'month')->get()->toArray();

        for ($inc = 1; $inc <= $number; $inc++) {
            $user_data[$inc] = 0;
            foreach ($users as $match) {
                if ($match['month'] == $inc) {
                    $user_data[$inc] = $match['total'];
                }
            }
        }

        $customerStats = User::zone($params['zone_id'])->selectRaw("
            SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_customers,
            SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as blocked_customers,
            SUM(CASE WHEN DATE(created_at) >= ? THEN 1 ELSE 0 END) as newly_joined,
            SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 ELSE 0 END) as this_month,
            SUM(CASE WHEN MONTH(created_at) = 12 AND YEAR(created_at) = ? THEN 1 ELSE 0 END) as last_year_users
        ", [$last30, now()->format('m'), now()->format('Y'), now()->format('Y') - 1])->first();

        $active_customers = (int) $customerStats->active_customers;
        $blocked_customers = (int) $customerStats->blocked_customers;
        $newly_joined = (int) $customerStats->newly_joined;
        $this_month = (int) $customerStats->this_month;
        $last_year_users = (int) $customerStats->last_year_users;

        $employees = Admin::zone()->with(['role'])->where('role_id', '!=', '1')
            ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->get();

        $deliveryMen = DeliveryMan::with('last_location')->when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->where('zone_id', $params['zone_id']);
        })->zonewise()->available()->active()->get();

        $deliveryMen = Helpers::deliverymen_list_formatting($deliveryMen);

        $module_type = Config::get('module.current_module_type');

        if(addon_published_status('RideShare')) {
            $rider_data = self::get_rider_data($params);
        } else {
            $rider_data = [];
        }

        return view("admin-views.dashboard-{$module_type}", compact('data', 'reviews', 'this_month', 'user_data', 'neutral_reviews', 'good_reviews', 'negative_reviews', 'positive_reviews', 'employees', 'active_deliveryman', 'deliveryMen', 'inactive_deliveryman', 'newly_joined_deliveryman', 'delivery_man', 'total_sell', 'commission', 'delivery_commission', 'params', 'module_type', 'customers', 'active_customers', 'blocked_customers', 'newly_joined', 'last_year_users', 'blocked_deliveryman', 'rider_data'));
    }

    private function get_rider_data($params) {

        $data['rider_images'] = Rider::when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->limit(2)
            ->get('image');

        $riderStats = Rider::when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->selectRaw("
                SUM(CASE WHEN active = 1 AND application_status = 'approved' THEN 1 ELSE 0 END) as active_rider,
                SUM(CASE WHEN application_status = 'approved' AND active = 0 THEN 1 ELSE 0 END) as inactive_rider,
                SUM(CASE WHEN application_status = 'approved' AND status = 0 THEN 1 ELSE 0 END) as blocked_rider,
                SUM(CASE WHEN application_status = 'approved' AND DATE(created_at) >= ? THEN 1 ELSE 0 END) as newly_joined_rider
            ", [now()->subDays(30)->format('Y-m-d')])
            ->first();

        $data['active_rider'] = (int) $riderStats->active_rider;
        $data['inactive_rider'] = (int) $riderStats->inactive_rider;
        $data['blocked_rider'] = (int) $riderStats->blocked_rider;
        $data['newly_joined_rider'] = (int) $riderStats->newly_joined_rider;

        $data['top_riders'] = Rider::withCount('driverTrips')->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->having("driver_trips_count", '>', 0)
            ->orderBy("driver_trips_count", 'desc')
            ->take(6)
            ->get();

        $riders = Rider::with('last_location')->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->available()
            ->active()
            ->get();

        $data['map_riders'] = Helpers::deliverymen_list_formatting($riders);

        $data['total_riders'] = $data['active_rider'] + $data['inactive_rider'] + $data['blocked_rider'];

        return $data;

    }

    public function transaction_dashboard(Request $request)
    {
        $module_type = Config::get('module.current_module_type');
        return view("admin-views.dashboard-{$module_type}");
    }

    public function dispatch_dashboard(Request $request)
    {
        $params = $this->buildParams($request);

        session()->put('dash_params', $params);
        $data = self::dashboard_data($request);

        $maxOrder = config('dm_maximum_orders') ?? 1;

        $deliveryman_stats = DeliveryMan::when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->Zonewise()
            ->selectRaw("
                COUNT(*) as total,

                SUM(CASE
                    WHEN active = 1
                    THEN 1 ELSE 0 END
                ) as active_deliveryman,

                SUM(CASE
                    WHEN application_status = 'approved'
                        AND active = 0
                    THEN 1 ELSE 0 END
                ) as inactive_deliveryman,

                SUM(CASE
                    WHEN application_status = 'approved'
                        AND status = 0
                    THEN 1 ELSE 0 END
                ) as suspend_deliveryman,

                SUM(CASE
                    WHEN active = 1
                        AND current_orders > {$maxOrder}
                    THEN 1 ELSE 0 END
                ) as unavailable_deliveryman,

                SUM(CASE
                    WHEN active = 1
                        AND current_orders < {$maxOrder}
                    THEN 1 ELSE 0 END
                ) as available_deliveryman
            ")
            ->first();

        $active_deliveryman      = $deliveryman_stats->active_deliveryman;
        $inactive_deliveryman    = $deliveryman_stats->inactive_deliveryman;
        $suspend_deliveryman     = $deliveryman_stats->suspend_deliveryman;
        $unavailable_deliveryman = $deliveryman_stats->unavailable_deliveryman;
        $available_deliveryman   = $deliveryman_stats->available_deliveryman;


        $deliveryMen = DeliveryMan::when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->where('zone_id', $params['zone_id']);
        })->zonewise()->available()->active()->get();

        $deliveryMen = Helpers::deliverymen_list_formatting($deliveryMen);

        $module_type = Config::get('module.current_module_type');
        return view("admin-views.dashboard-{$module_type}", compact('data', 'active_deliveryman', 'deliveryMen', 'unavailable_deliveryman', 'available_deliveryman', 'inactive_deliveryman', 'module_type', 'suspend_deliveryman'));
    }

    public function dashboard(Request $request)
    {
        $admin = auth('admin')->user();
        if ($admin && $admin->role_id != 1 && !Helpers::module_permission_check('dashboard')) {
            $landing = Helpers::admin_landing_url();
            if ($landing) {
                return redirect($landing);
            }
        }

        $module_type = Config::get('module.current_module_type');
        $redirect = match ($module_type) {
            'settings' => redirect()->route('admin.business-settings.business-setup'),
            'ride-share' => addon_published_status('RideShare') == 1
                ? redirect()->route('admin.ride-share.dashboard')
                : view('errors.404'),
            'rental' => addon_published_status('Rental') == 1
                ? redirect()->route('admin.rental.dashboard')
                : view('errors.404'),
            'service' => addon_published_status('Service') == 1
                ? redirect()->route('admin.service.dashboard')
                : view('errors.404'),
            default => null,
        };
        if ($redirect) {
            return $redirect;
        }

        $params = $this->buildParams($request);
        session()->put('dash_params', $params);
        $data = self::dashboard_data($request);
        $total_sell = $data['total_sell'];
        $commission = $data['commission'];
        $delivery_commission = $data['delivery_commission'];
        $label = $data['label'];

        return view("admin-views.dashboard-{$module_type}", compact('data', 'total_sell', 'commission', 'delivery_commission', 'label', 'params', 'module_type'));

    }

    public function order(Request $request)
    {
        $params = $this->updateDashParam('statistics_type', $request['statistics_type']);

        if ($params['zone_id'] != 'all') {
            $store_ids = Store::where(['module_id' => $params['module_id']])->where(['zone_id' => $params['zone_id']])->pluck('id')->toArray();
        } else {
            $store_ids = Store::where(['module_id' => $params['module_id']])->pluck('id')->toArray();
        }
        $data = self::order_stats_calc($params['zone_id'], $params['module_id']);
        $module_type = Config::get('module.current_module_type');
        if ($module_type == 'parcel') {
            return response()->json([
                'view' => view('admin-views.partials._dashboard-order-stats-parcel', compact('data'))->render()
            ], 200);
        } elseif ($module_type == 'food') {
            return response()->json([
                'view' => view('admin-views.partials._dashboard-order-stats-food', compact('data'))->render()
            ], 200);
        }
        return response()->json([
            'view' => view('admin-views.partials._dashboard-order-stats', compact('data'))->render()
        ], 200);
    }

    public function zone(Request $request)
    {
        $params = $this->updateDashParam('zone_id', $request['zone_id']);

        $data = self::dashboard_data($request);
        $total_sell = $data['total_sell'];
        $commission = $data['commission'];
        $popular = $data['popular'];
        $top_deliveryman = $data['top_deliveryman'];
        $top_rated_foods = $data['top_rated_foods'];
        $top_restaurants = $data['top_restaurants'];
        $top_customers = $data['top_customers'];
        $top_sell = $data['top_sell'];
        $delivery_commission = $data['delivery_commission'];
        $module_type = Config::get('module.current_module_type');
        $label = $data['label'];

        return response()->json([
            'popular_restaurants' => view('admin-views.partials._popular-restaurants', compact('popular'))->render(),
            'top_deliveryman' => view('admin-views.partials._top-deliveryman', compact('top_deliveryman'))->render(),
            'top_rated_foods' => view('admin-views.partials._top-rated-foods', compact('top_rated_foods'))->render(),
            'top_restaurants' => view('admin-views.partials._top-restaurants', compact('top_restaurants'))->render(),
            'top_customers' => view('admin-views.partials._top-customer', compact('top_customers'))->render(),
            'top_selling_foods' => view('admin-views.partials._top-selling-foods', compact('top_sell'))->render(),


            'user_overview' => view('admin-views.partials._user-overview-chart', compact('data'))->render(),
            'monthly_graph' => view('admin-views.partials._monthly-earning-graph', compact('total_sell', 'commission', 'delivery_commission', 'label'))->render(),
            'stat_zone' => view('admin-views.partials._zone-change', compact('data'))->render(),
            'order_stats' => $module_type == 'parcel' ? view('admin-views.partials._dashboard-order-stats-parcel', compact('data'))->render() :
                ($module_type == 'food' ? view('admin-views.partials._dashboard-order-stats-food', compact('data'))->render() :
                    view('admin-views.partials._dashboard-order-stats', compact('data'))->render()),
        ], 200);
    }

    public function user_overview(Request $request)
    {
        $params = $this->updateDashParam('user_overview', $request['user_overview']);

        $data = self::user_overview_calc($params['zone_id'], $params['module_id']);
        $module_type = Config::get('module.current_module_type');
        if ($module_type == 'parcel') {
            return response()->json([
                'view' => view('admin-views.partials._user-overview-chart-parcel', compact('data'))->render()
            ], 200);
        }

        return response()->json([
            'view' => view('admin-views.partials._user-overview-chart', compact('data'))->render()
        ], 200);
    }

    public function commission_overview(Request $request)
    {
        $params = $this->updateDashParam('commission_overview', $request['commission_overview']);

        $data = self::commission_chart_calc();

        return response()->json([
            'view' => view('admin-views.partials._commission-overview-chart', compact('data'))->render(),
            'gross_sale' => view('admin-views.partials._gross_sale', compact('data'))->render()
        ], 200);
    }

    public function order_stats_calc($zone_id, $module_id)
    {
        $params = session('dash_params');
        $module_type = Config::get('module.current_module_type');

        if ($module_id && $params['statistics_type'] == 'today') {
            $today = Carbon::now();
            $searching_for_dm = Order::SearchingForDeliveryman()->where('module_id', $module_id)->whereDate('created_at', $today);
            $accepted_by_dm = Order::AccepteByDeliveryman()->where('module_id', $module_id)->whereDate('accepted', $today);
            $preparing_in_rs = Order::Preparing()->where('module_id', $module_id)->whereDate('processing', $today);
            $picked_up = Order::ItemOnTheWay()->where('module_id', $module_id)->whereDate('picked_up', $today);
            $delivered = Order::Delivered()->where('module_id', $module_id)->whereDate('delivered', $today);
            $canceled = Order::where('module_id', $module_id)->where(['order_status' => 'canceled'])->whereDate('canceled', $today);
            $refund_requested = Order::where('module_id', $module_id)->where(['order_status' => 'refund_requested'])->whereDate('refund_requested', $today);
            $refunded = Order::where('module_id', $module_id)->where(['order_status' => 'refunded'])->whereDate('refunded', $today);
            $new_orders = Order::where('module_id', $module_id)->whereDate('schedule_at', $today);
            if ($module_type == 'parcel') {
                $total_orders = Order::where('module_id', $module_id)->whereDate('created_at', $today);
            } else {
                $total_orders = Order::where('module_id', $module_id);
            }
        } elseif ($module_id && $params['statistics_type'] == 'this_year') {
            $year = now()->format('Y');
            $searching_for_dm = Order::SearchingForDeliveryman()->where('module_id', $module_id)->whereYear('created_at', $year);
            $accepted_by_dm = Order::AccepteByDeliveryman()->where('module_id', $module_id)->whereYear('accepted', $year);
            $preparing_in_rs = Order::Preparing()->where('module_id', $module_id)->whereYear('processing', $year);
            $picked_up = Order::ItemOnTheWay()->where('module_id', $module_id)->whereYear('picked_up', $year);
            $delivered = Order::Delivered()->where('module_id', $module_id)->whereYear('delivered', $year);
            $canceled = Order::where('module_id', $module_id)->where(['order_status' => 'canceled'])->whereYear('canceled', $year);
            $refund_requested = Order::where('module_id', $module_id)->where(['order_status' => 'refund_requested'])->whereYear('refund_requested', $year);
            $refunded = Order::where('module_id', $module_id)->where(['order_status' => 'refunded'])->whereYear('refunded', $year);
            $new_orders = Order::where('module_id', $module_id)->whereYear('schedule_at', $year);
            $total_orders = Order::where('module_id', $module_id);
        } elseif ($module_id && $params['statistics_type'] == 'this_month') {
            $month = now()->format('m');
            $year = now()->format('Y');
            $searching_for_dm = Order::SearchingForDeliveryman()->where('module_id', $module_id)->whereMonth('created_at', $month)->whereYear('created_at', $year);
            $accepted_by_dm = Order::AccepteByDeliveryman()->where('module_id', $module_id)->whereMonth('accepted', $month)->whereYear('accepted', $year);
            $preparing_in_rs = Order::Preparing()->where('module_id', $module_id)->whereMonth('processing', $month)->whereYear('processing', $year);
            $picked_up = Order::ItemOnTheWay()->where('module_id', $module_id)->whereMonth('picked_up', $month)->whereYear('picked_up', $year);
            $delivered = Order::Delivered()->where('module_id', $module_id)->whereMonth('delivered', $month)->whereYear('delivered', $year);
            $canceled = Order::where('module_id', $module_id)->where(['order_status' => 'canceled'])->whereMonth('canceled', $month)->whereYear('canceled', $year);
            $refund_requested = Order::where('module_id', $module_id)->where(['order_status' => 'refund_requested'])->whereMonth('refund_requested', $month)->whereYear('refund_requested', $year);
            $refunded = Order::where('module_id', $module_id)->where(['order_status' => 'refunded'])->whereMonth('refunded', $month)->whereYear('refunded', $year);
            $new_orders = Order::where('module_id', $module_id)->whereMonth('schedule_at', $month)->whereYear('schedule_at', $year);
            $total_orders = Order::where('module_id', $module_id);
        } elseif ($module_id && $params['statistics_type'] == 'this_week') {
            $weekStart = now()->startOfWeek()->format('Y-m-d H:i:s');
            $weekEnd = now()->endOfWeek()->format('Y-m-d H:i:s');
            $searching_for_dm = Order::SearchingForDeliveryman()->where('module_id', $module_id)->whereBetween('created_at', [$weekStart, $weekEnd]);
            $accepted_by_dm = Order::AccepteByDeliveryman()->where('module_id', $module_id)->whereBetween('accepted', [$weekStart, $weekEnd]);
            $preparing_in_rs = Order::Preparing()->where('module_id', $module_id)->whereBetween('processing', [$weekStart, $weekEnd]);
            $picked_up = Order::ItemOnTheWay()->where('module_id', $module_id)->whereBetween('picked_up', [$weekStart, $weekEnd]);
            $delivered = Order::Delivered()->where('module_id', $module_id)->whereBetween('delivered', [$weekStart, $weekEnd]);
            $canceled = Order::where('module_id', $module_id)->where(['order_status' => 'canceled'])->whereBetween('canceled', [$weekStart, $weekEnd]);
            $refund_requested = Order::where('module_id', $module_id)->where(['order_status' => 'refund_requested'])->whereBetween('refund_requested', [$weekStart, $weekEnd]);
            $refunded = Order::where('module_id', $module_id)->where(['order_status' => 'refunded'])->whereBetween('refunded', [$weekStart, $weekEnd]);
            $new_orders = Order::where('module_id', $module_id)->whereBetween('schedule_at', [$weekStart, $weekEnd]);
            $total_orders = Order::where('module_id', $module_id);
        } elseif ($module_id) {
            $last30 = now()->subDays(30)->format('Y-m-d');
            $searching_for_dm = Order::SearchingForDeliveryman()->where('module_id', $module_id);
            $accepted_by_dm = Order::AccepteByDeliveryman()->where('module_id', $module_id);
            $preparing_in_rs = Order::Preparing()->where('module_id', $module_id);
            $picked_up = Order::ItemOnTheWay()->where('module_id', $module_id);
            $delivered = Order::Delivered()->where('module_id', $module_id);
            $canceled = Order::Canceled()->where('module_id', $module_id);
            $refund_requested = Order::failed()->where('module_id', $module_id);
            $refunded = Order::Refunded()->where('module_id', $module_id);
            $new_orders = Order::where('module_id', $module_id)->whereDate('schedule_at', '>=', $last30);
            $total_orders = Order::where('module_id', $module_id);
        } else {
            $last30 = now()->subDays(30)->format('Y-m-d');
            $searching_for_dm = Order::SearchingForDeliveryman();
            $accepted_by_dm = Order::AccepteByDeliveryman();
            $preparing_in_rs = Order::Preparing();
            $picked_up = Order::ItemOnTheWay();
            $delivered = Order::Delivered();
            $canceled = Order::Canceled();
            $refund_requested = Order::failed();
            $refunded = Order::Refunded();
            $new_orders = Order::whereDate('schedule_at', '>=', $last30);
            $total_orders = Order::query();
        }

        $isParcel = $module_id && $module_type == 'parcel';
        $orderScope = $isParcel ? 'ParcelOrder' : 'StoreOrder';
        $zoneOnOrders = is_numeric($zone_id) && $module_id;
        $zoneOnStores = is_numeric($zone_id) && $module_id;
        $zoneOnCustomers = is_numeric($zone_id) && $module_id && $isParcel;
        [$newCase, $newBind] = $this->statNewDateCase('created_at', $module_id);

        $applyOrderType = function ($q, $withType) use ($orderScope, $zoneOnOrders, $zone_id) {
            if ($withType) {
                $q = $q->{$orderScope}();
            }
            if ($zoneOnOrders) {
                $q = $q->where('zone_id', $zone_id);
            }
            return $q;
        };

        $sfd = $searching_for_dm->{$orderScope}()->OrderScheduledIn(30);
        if ($zoneOnOrders) {
            $sfd = $sfd->where('zone_id', $zone_id);
        }
        $searching_for_dm = $sfd->count();

        $accepted_by_dm = $applyOrderType($accepted_by_dm, true)->count();
        $preparing_in_rs = $applyOrderType($preparing_in_rs, true)->count();
        $picked_up = $applyOrderType($picked_up, true)->count();
        $delivered = $applyOrderType($delivered, true)->count();
        $canceled = $applyOrderType($canceled, true)->count();
        $refund_requested = $applyOrderType($refund_requested, true)->count();
        $refunded = $applyOrderType($refunded, true)->count();
        $new_orders = $applyOrderType($new_orders, (bool) $module_id)->count();
        $total_orders = $applyOrderType($total_orders, (bool) $module_id)->count();

        $itemRow = Item::where('is_approved', 1)
            ->when($module_id, fn($q) => $q->where('module_id', $module_id))
            ->toBase()
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN {$newCase} THEN 1 ELSE 0 END) as new", $newBind)
            ->first();
        $total_items = (int) $itemRow->total;
        $new_items = (int) $itemRow->new;

        $storeRow = Store::whereHas('vendor', fn($q) => $q->where('status', 1))
            ->when($module_id, fn($q) => $q->where('module_id', $module_id))
            ->when($zoneOnStores, fn($q) => $q->where('zone_id', $zone_id))
            ->toBase()
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN {$newCase} THEN 1 ELSE 0 END) as new", $newBind)
            ->first();
        $total_stores = (int) $storeRow->total;
        $new_stores = (int) $storeRow->new;

        $customerRow = User::when($zoneOnCustomers, fn($q) => $q->where('zone_id', $zone_id))
            ->toBase()
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN {$newCase} THEN 1 ELSE 0 END) as new", $newBind)
            ->first();
        $total_customers = (int) $customerRow->total;
        $new_customers = (int) $customerRow->new;
        $data = [
            'searching_for_dm' => $searching_for_dm,
            'accepted_by_dm' => $accepted_by_dm,
            'preparing_in_rs' => $preparing_in_rs,
            'picked_up' => $picked_up,
            'delivered' => $delivered,
            'canceled' => $canceled,
            'refund_requested' => $refund_requested,
            'refunded' => $refunded,
            'total_orders' => $total_orders,
            'total_items' => $total_items,
            'total_stores' => $total_stores,
            'total_customers' => $total_customers,
            'new_orders' => $new_orders,
            'new_items' => $new_items,
            'new_stores' => $new_stores,
            'new_customers' => $new_customers,
        ];

        return $data;
    }

    public function user_overview_calc($zone_id, $module_id)
    {
        $params = session('dash_params');
        //zone
        if (is_numeric($zone_id)) {
            $customer = User::where('zone_id', $zone_id);
            $stores = Store::whereHas('vendor', fn($query) => $query->where('status', 1))->where('module_id', $module_id)->where(['zone_id' => $zone_id]);
            $delivery_man = DeliveryMan::where('application_status', 'approved')->where('zone_id', $zone_id)->Zonewise();
        } else {
            $customer = User::whereNotNull('id');
            $stores = Store::whereHas('vendor', fn($query) => $query->where('status', 1))->where('module_id', $module_id)->whereNotNull('id');
            $delivery_man = DeliveryMan::where('application_status', 'approved')->Zonewise();
        }
        //user overview
        $applyOverview = match ($params['user_overview']) {
            'overall' => fn($q) => $q,
            'this_month' => fn($q) => $q->whereMonth('created_at', date('m'))->whereYear('created_at', date('Y')),
            'this_year' => fn($q) => $q->whereYear('created_at', date('Y')),
            default => fn($q) => $q->whereDate('created_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]),
        };

        $data = [
            'customer' => $applyOverview($customer)->count(),
            'stores' => $applyOverview($stores)->count(),
            'delivery_man' => $applyOverview($delivery_man)->count(),
        ];
        return $data;
    }


    public function dashboard_data($request)
    {
        $params = session('dash_params');
        if (!url()->current() == $request->is('admin/users')) {
            $data_os = self::order_stats_calc($params['zone_id'], $params['module_id']);
            if(Route::currentRouteName() == 'admin.dispatch.dashboard'){
                return $data_os;
            }
            $data_uo = self::user_overview_calc($params['zone_id'], $params['module_id']);
        }

        $popular = Wishlist::with(['store' => fn($q) => $q->select('id', 'name', 'logo'), 'store.storage'])
            ->whereHas('store')
            ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                return $q->whereHas('store', function ($query) use ($params) {
                    return $query->where('module_id', $params['module_id']);
                });
            })
            ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->whereHas('store', function ($query) use ($params) {
                    return $query->where('zone_id', $params['zone_id']);
                });
            })
            ->select('store_id', DB::raw('COUNT(store_id) as count'))->groupBy('store_id')
            ->having("count", '>', 0)
            ->orderBy('count', 'DESC')
            ->limit(6)->get();
        $top_sell = Item::withoutGlobalScope(ZoneScope::class)
            ->select('id', 'name', 'image', 'order_count')
            ->with('storage')
            ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                return $q->whereHas('store', function ($query) use ($params) {
                    return $query->where('module_id', $params['module_id']);
                });
            })
            ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->whereHas('store', function ($query) use ($params) {
                    return $query->where('module_id', $params['module_id'])->where('zone_id', $params['zone_id']);
                });
            })
            ->having("order_count", '>', 0)
            ->orderBy("order_count", 'desc')
            ->take(6)
            ->get();
        $top_rated_foods = Item::withoutGlobalScope(ZoneScope::class)
            ->select('id', 'name', 'image', 'rating_count')
            ->with('storage')
            ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                return $q->whereHas('store', function ($query) use ($params) {
                    return $query->where('module_id', $params['module_id']);
                });
            })
            ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->whereHas('store', function ($query) use ($params) {
                    return $query->where('zone_id', $params['zone_id']);
                });
            })
            ->having("rating_count", '>', 0)
            ->orderBy('rating_count', 'desc')
            ->orderBy('id')
            ->take(6)
            ->get();

        $top_deliveryman = DeliveryMan::select('id', 'f_name', 'phone', 'image')->with('storage')->withCount('orders')->when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->where('zone_id', $params['zone_id']);
        })
            ->Zonewise()
            ->having("orders_count", '>', 0)
            ->orderBy("orders_count", 'desc')
            ->take(6)
            ->get();

        $top_customers = User::select('id', 'f_name', 'phone', 'image')->with('storage')->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->withCount([
                'orders as order_count' => fn($query) => $query->where('order_status', 'delivered')
            ])
            ->having('order_count', '>', 0)
            ->orderByDesc('order_count')
            ->take(6)
            ->get();

        $top_restaurants = Store::select('id', 'name', 'logo', 'order_count')->with('storage')->whereHas('vendor', fn($query) => $query->where('status', 1))->when(is_numeric($params['module_id']), function ($q) use ($params) {
            return $q->where('module_id', $params['module_id']);
        })
            ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->having("order_count", '>', 0)
            ->orderBy("order_count", 'desc')
            ->take(6)
            ->get();


        if (!url()->current() == $request->is('admin/users')) {
            $dash_data = array_merge($data_os, $data_uo);
        }

        $dash_data['popular'] = $popular;
        $dash_data['top_sell'] = $top_sell;
        $dash_data['top_rated_foods'] = $top_rated_foods;
        $dash_data['top_deliveryman'] = $top_deliveryman;
        $dash_data['top_restaurants'] = $top_restaurants;
        $dash_data['top_customers'] = $top_customers;

        return array_merge($dash_data, self::commission_chart_calc());
    }

    public function commission_chart_calc(): array
    {
        $params = session('dash_params');
        $months = array(
            '"' . translate('Jan') . '"',
            '"' . translate('Feb') . '"',
            '"' . translate('Mar') . '"',
            '"' . translate('Apr') . '"',
            '"' . translate('May') . '"',
            '"' . translate('Jun') . '"',
            '"' . translate('Jul') . '"',
            '"' . translate('Aug') . '"',
            '"' . translate('Sep') . '"',
            '"' . translate('Oct') . '"',
            '"' . translate('Nov') . '"',
            '"' . translate('Dec') . '"'
        );
        $days = array(
            '"' . translate('Mon') . '"',
            '"' . translate('Tue') . '"',
            '"' . translate('Wed') . '"',
            '"' . translate('Thu') . '"',
            '"' . translate('Fri') . '"',
            '"' . translate('Sat') . '"',
            '"' . translate('Sun') . '"',
        );
        $total_sell = [];
        $commission = [];
        $delivery_commission = [];
        $label = [];
        $currentYear = now()->format('Y');
        $commissionSelect = [
            DB::raw('SUM(order_amount) as total_sell'),
            DB::raw("SUM(admin_commission + admin_expense - delivery_fee_comission + COALESCE((SELECT CASE WHEN orders.delivery_type = 'express' THEN orders.delivery_type_charge ELSE 0 END FROM orders WHERE orders.id = order_transactions.order_id), 0)) as commission"),
            DB::raw('SUM(delivery_fee_comission) as delivery_commission'),
        ];
        $applyFilters = function ($q) use ($params) {
            return $q->when(is_numeric($params['module_id']), function ($q) use ($params) {
                return $q->where('module_id', $params['module_id']);
            })
                ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                    return $q->where('zone_id', $params['zone_id']);
                });
        };
        switch ($params['commission_overview']) {
            case "this_week":
                $weekStartDate = now()->startOfWeek();
                $rows = $applyFilters(OrderTransaction::NotRefunded())
                    ->whereBetween('created_at', [$weekStartDate->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')])
                    ->select(array_merge([DB::raw('DATE(created_at) as period')], $commissionSelect))
                    ->groupBy('period')
                    ->get()->keyBy('period');

                for ($i = 0; $i < 7; $i++) {
                    $row = $rows->get($weekStartDate->copy()->addDays($i)->format('Y-m-d'));
                    $total_sell[$i] = $row?->total_sell ?? 0;
                    $commission[$i] = $row?->commission ?? 0;
                    $delivery_commission[$i] = $row?->delivery_commission ?? 0;
                }

                $label = $days;
                break;

            case "this_month":
                $start = now()->startOfMonth();
                $total_days = now()->daysInMonth;
                $weeks = array(
                    '"Day 1-7"',
                    '"Day 8-14"',
                    '"Day 15-21"',
                    '"Day 22-' . $total_days . '"',
                );

                $ranges = [];
                for ($i = 1; $i <= 4; $i++) {
                    $end = $start->copy()->addDays(6);
                    if ($i == 4) {
                        $end = now()->endOfMonth();
                    }
                    $ranges[$i] = ["{$start->format('Y-m-d')} 00:00:00", "{$end->format('Y-m-d')} 23:59:59"];
                    $start = $end->copy()->addDay();
                }

                $caseSql = 'CASE';
                foreach ($ranges as $idx => $range) {
                    $caseSql .= " WHEN created_at BETWEEN '{$range[0]}' AND '{$range[1]}' THEN {$idx}";
                }
                $caseSql .= ' END';

                $rows = $applyFilters(OrderTransaction::NotRefunded())
                    ->whereBetween('created_at', [$ranges[1][0], $ranges[4][1]])
                    ->select(array_merge([DB::raw("{$caseSql} as period")], $commissionSelect))
                    ->groupBy('period')
                    ->get()->keyBy('period');

                for ($i = 1; $i <= 4; $i++) {
                    $row = $rows->get($i);
                    $total_sell[$i] = $row?->total_sell ?? 0;
                    $commission[$i] = $row?->commission ?? 0;
                    $delivery_commission[$i] = $row?->delivery_commission ?? 0;
                }

                $label = $weeks;
                break;

            case "this_year":
            default:
                $rows = $applyFilters(OrderTransaction::NotRefunded())
                    ->whereYear('created_at', $currentYear)
                    ->select(array_merge([DB::raw('MONTH(created_at) as period')], $commissionSelect))
                    ->groupBy('period')
                    ->get()->keyBy('period');

                for ($i = 1; $i <= 12; $i++) {
                    $row = $rows->get($i);
                    $total_sell[$i] = $row?->total_sell ?? 0;
                    $commission[$i] = $row?->commission ?? 0;
                    $delivery_commission[$i] = $row?->delivery_commission ?? 0;
                }
                $label = $months;
        }

        return [
            'total_sell' => $total_sell,
            'commission' => $commission,
            'delivery_commission' => $delivery_commission,
            'label' => $label,
        ];
    }
}
