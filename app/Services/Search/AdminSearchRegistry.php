<?php

namespace App\Services\Search;

use App\Models\AddOn;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\Advertisement;
use App\Models\Attribute;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\CashBack;
use App\Models\Category;
use App\Models\CommonCondition;
use App\Models\Contact;
use App\Models\Coupon;
use App\Models\DeliveryMan;
use App\Models\Disbursement;
use App\Models\DMVehicle;
use App\Models\FlashSale;
use App\Models\Item;
use App\Models\ItemCampaign;
use App\Models\LoyaltyPointTransaction;
use App\Models\Module;
use App\Models\Newsletter;
use App\Models\Notification;
use App\Models\Order;
use App\Models\ParcelCategory;
use App\Models\ProCustomerSubscription;
use App\Models\Store;
use App\Models\StoreCategory;
use App\Models\SubscriptionPackage;
use App\Models\TempProduct;
use App\Models\Unit;
use App\Models\User;
use App\Models\WalletBonus;
use App\Models\WalletTransaction;
use App\Models\WithdrawalMethod;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Builder;
use Modules\Rental\Entities\Trips;
use Modules\Rental\Entities\Vehicle;
use Modules\Rental\Entities\VehicleBrand;
use Modules\Rental\Entities\VehicleCategory;
use Modules\Rental\Entities\VehicleReview;
use Modules\RideShare\Entities\TripManagement\RideRequest;
use Modules\RideShare\Entities\VehicleManagement\RiderVehicle;
use Modules\RideShare\Entities\VehicleManagement\RiderVehicleBrand;
use Modules\RideShare\Entities\VehicleManagement\RiderVehicleModel;
use Modules\RideShare\Entities\VehicleManagement\RiderVehicleCategory;

class AdminSearchRegistry
{
    public static function all(): array
    {
        return array_merge(
            self::catalog(),
            self::people(),
            self::marketing(),
            self::finance(),
            self::settings(),
            self::rental(),
            self::rideShare(),
        );
    }

    private static function byModule(): callable
    {
        return function (Builder $query, SearchContext $context) {
            $query->when($context->hasModuleId(), function (Builder $query) use ($context) {
                $query->where('module_id', $context->moduleId);
            });
        };
    }

    private static function notRentalOrRide(): callable
    {
        return function (SearchContext $context) {
            return $context->moduleTypeIsNot('rental', 'ride-share');
        };
    }

    private static function catalog(): array
    {
        return [
            SearchEntity::make('store')
                ->model(Store::class)
                ->prefix('Store')
                ->type('store')
                ->with(['vendor'])
                ->columns(['name', 'phone', 'email', 'address', 'meta_title', 'meta_description'])
                ->relation('vendor', ['f_name', 'l_name', 'email', 'phone'], true)
                ->query(self::byModule())
                ->moduleScoped()
                ->when(fn (SearchContext $c) => $c->moduleTypeIsNot('rental'))
                ->name(fn ($store) => $store->name)
                ->variant(function ($store) {
                    return match ($store->vendor?->status) {
                        0 => 'denied',
                        null => 'pending',
                        default => 'active',
                    };
                })
                ->routes(function (string $uri, string $name, string $variant) {
                    if (str_contains($uri, 'withdraw-view') || str_contains($uri, 'transactions')) {
                        return false;
                    }
                    if (! str_contains($uri, 'store')) {
                        return false;
                    }
                    $extra = match ($variant) {
                        'denied' => 'deny-requests',
                        'pending' => 'pending-requests',
                        default => null,
                    };

                    return str_contains($uri, 'edit')
                        || str_contains($uri, 'view')
                        || basename(parse_url($uri, PHP_URL_PATH)) === 'recommended-store'
                        || ($extra !== null && str_contains($uri, $extra));
                })
                ->url(function ($store, array $resolved) {
                    if ($store->vendor?->status === null && $resolved['routeName'] === 'View') {
                        return ['url' => $resolved['url'] . '/pending-list'];
                    }

                    return null;
                }),

            SearchEntity::make('order')
                ->model(Order::class)
                ->prefix('Order')
                ->type('order')
                ->query(self::byModule())
                ->moduleScoped()
                ->when(self::notRentalOrRide())
                ->routes(fn (string $uri) => str_contains($uri, 'admin/order/details')),

            SearchEntity::make('order.related')
                ->model(Order::class)
                ->prefix('Order')
                ->type('order')
                ->with(['customer', 'store'])
                ->query(self::byModule())
                ->moduleScoped()
                ->when(self::notRentalOrRide())
                ->idFilter(function (Builder $query, string $id) {
                    $query->where(function (Builder $query) use ($id) {
                        $query->whereHas('customer', fn (Builder $q) => $q->where('id', $id))
                            ->orWhereHas('store', fn (Builder $q) => $q->where('id', $id));
                    });
                })
                ->routes(fn (string $uri) => str_contains($uri, 'admin/order/details')),

            SearchEntity::make('category')
                ->model(Category::class)
                ->prefix('Category')
                ->type('category')
                ->columns(['name'])
                ->query(self::byModule())
                ->moduleScoped()
                ->when(self::notRentalOrRide())
                ->name(fn ($category) => $category->name)
                ->routes(fn (string $uri) => str_contains($uri, 'category')
                    && str_contains($uri, 'update')
                    && ! str_contains($uri, 'category/update-priority')),

            SearchEntity::make('store-category')
                ->model(StoreCategory::class)
                ->prefix('Store Category')
                ->columns(['name'])
                ->query(self::byModule())
                ->moduleScoped()
                ->when(self::notRentalOrRide())
                ->name(fn ($category) => $category->name)
                ->routes(fn (string $uri) => str_contains($uri, 'store-category') && str_contains($uri, 'edit')),

            SearchEntity::make('addon')
                ->model(AddOn::class)
                ->prefix('Addon')
                ->columns(['name'])
                ->query(function (Builder $query, SearchContext $context) {
                    $query->when($context->hasModuleId(), function (Builder $query) use ($context) {
                        $query->whereHas('store', fn (Builder $q) => $q->where('module_id', $context->moduleId));
                    });
                })
                ->moduleScoped()
                ->when(self::notRentalOrRide())
                ->name(fn ($addon) => $addon->name)
                ->routes(fn (string $uri) => str_contains($uri, 'addon') && str_contains($uri, 'edit')),

            SearchEntity::make('item')
                ->model(Item::class)
                ->prefix('Item')
                ->type('Item')
                ->columns(['name', 'description'])
                ->query(self::byModule())
                ->moduleScoped()
                ->when(self::notRentalOrRide())
                ->name(fn ($item) => $item->name)
                ->routes(fn (string $uri) => str_contains($uri, 'admin/item/edit/') || str_contains($uri, 'admin/item/view/')),

            SearchEntity::make('campaign.basic')
                ->model(Campaign::class)
                ->prefix('Basic Campaign')
                ->type('basic-campaign')
                ->columns(['title', 'description'])
                ->query(self::byModule())
                ->moduleScoped()
                ->when(self::notRentalOrRide())
                ->name(fn ($campaign) => $campaign->title)
                ->routes(fn (string $uri) => str_contains($uri, 'campaign')
                    && (str_contains($uri, 'edit') || str_contains($uri, 'view')))
                ->url(function ($campaign, array $resolved) {
                    $action = $resolved['routeName'] === 'Edit' ? 'edit' : 'view';
                    $uri = "admin/campaign/basic/{$action}/{$campaign->id}";

                    return ['uri' => $uri, 'url' => url('/') . '/' . $uri];
                }),

            SearchEntity::make('campaign.item')
                ->model(ItemCampaign::class)
                ->prefix('Item Campaign')
                ->type('item-campaign')
                ->columns(['title', 'description'])
                ->query(self::byModule())
                ->moduleScoped()
                ->when(self::notRentalOrRide())
                ->name(fn ($campaign) => $campaign->title)
                ->routes(fn (string $uri) => str_contains($uri, 'campaign')
                    && (str_contains($uri, 'edit') || str_contains($uri, 'view')))
                ->url(function ($campaign, array $resolved) {
                    $action = $resolved['routeName'] === 'Edit' ? 'edit' : 'view';
                    $uri = "admin/campaign/item/{$action}/{$campaign->id}";

                    return ['uri' => $uri, 'url' => url('/') . '/' . $uri];
                }),

            SearchEntity::make('temp-product')
                ->model(TempProduct::class)
                ->prefix('New Product')
                ->columns(['name', 'description'])
                ->query(self::byModule())
                ->moduleScoped()
                ->when(self::notRentalOrRide())
                ->name(fn ($product) => $product->name)
                ->routes(fn (string $uri) => str_contains($uri, 'admin/item/new/item/list')
                    || str_contains($uri, 'admin/item/requested/item/view/')),

            SearchEntity::make('advertisement')
                ->model(Advertisement::class)
                ->prefix('Advertisement')
                ->columns(['title', 'description', 'add_type'])
                ->query(self::byModule())
                ->moduleScoped()
                ->when(self::notRentalOrRide())
                ->name(fn ($ad) => $ad->title)
                ->routes(fn (string $uri) => str_contains($uri, 'advertisement')
                    && (str_contains($uri, 'edit') || str_contains($uri, 'detail'))),

            SearchEntity::make('unit')
                ->model(Unit::class)
                ->prefix('Unit')
                ->columns(['unit'])
                ->when(fn (SearchContext $c) => $c->moduleTypeIs('grocery', 'pharmacy', 'ecommerce'))
                ->name(fn ($unit) => $unit->unit)
                ->routes(fn (string $uri) => str_contains($uri, 'unit') && ! str_contains($uri, 'unit/export')),

            SearchEntity::make('attribute')
                ->model(Attribute::class)
                ->prefix('Attribute')
                ->columns(['name'])
                ->when(fn (SearchContext $c) => $c->moduleTypeIs('grocery', 'pharmacy', 'ecommerce'))
                ->name(fn ($attribute) => $attribute->name)
                ->routes(fn (string $uri) => str_contains($uri, 'attribute') && ! str_contains($uri, 'attribute/export')),

            SearchEntity::make('brand')
                ->model(Brand::class)
                ->prefix('Brand')
                ->columns(['name'])
                ->query(function (Builder $query, SearchContext $context) {
                    $query->when($context->hasModuleId(), function (Builder $query) use ($context) {
                        $query->where(function (Builder $query) use ($context) {
                            $query->where('module_id', $context->moduleId)->orWhereNull('module_id');
                        });
                    });
                })
                ->when(fn (SearchContext $c) => $c->moduleTypeIs('grocery', 'ecommerce'))
                ->name(fn ($brand) => $brand->name)
                ->routes(fn (string $uri) => str_contains($uri, 'brand')
                    && ! str_contains($uri, 'brand/export')
                    && ! str_contains($uri, 'brand/status')),

            SearchEntity::make('flash-sale')
                ->model(FlashSale::class)
                ->prefix('Flash Sale')
                ->columns(['title'])
                ->query(self::byModule())
                ->when(fn (SearchContext $c) => $c->moduleTypeIs('grocery', 'ecommerce'))
                ->name(fn ($sale) => $sale->title)
                ->routes(fn (string $uri) => str_contains($uri, 'flash-sale/') && ! str_contains($uri, 'publish')),

            SearchEntity::make('parcel-category')
                ->model(ParcelCategory::class)
                ->prefix('Parcel Category')
                ->columns(['name', 'description'])
                ->query(self::byModule())
                ->when(fn (SearchContext $c) => $c->moduleTypeIs('parcel'))
                ->name(fn ($category) => $category->name)
                ->routes(fn (string $uri) => str_contains($uri, 'admin/parcel/category/') && str_contains($uri, 'edit')),

            SearchEntity::make('common-condition')
                ->model(CommonCondition::class)
                ->prefix('Common Condition')
                ->columns(['name'])
                ->when(fn (SearchContext $c) => $c->moduleTypeIs('pharmacy'))
                ->name(fn ($condition) => $condition->name)
                ->routes(fn (string $uri) => str_contains($uri, 'admin/common-condition')),
        ];
    }

    private static function people(): array
    {
        return [
            SearchEntity::make('dm-vehicle')
                ->model(DMVehicle::class)
                ->prefix('Vehicle')
                ->type('vehicle')
                ->columns(['type'])
                ->when(fn (SearchContext $c) => $c->moduleTypeIsNot('rental'))
                ->name(fn ($vehicle) => $vehicle->type)
                ->routes(fn (string $uri) => str_contains($uri, 'delivery-man/vehicle/edit')),

            SearchEntity::make('delivery-man')
                ->model(DeliveryMan::class)
                ->prefix('Delivery Man')
                ->type('deliveryMan')
                ->columns(['f_name', 'l_name', 'email', 'phone', 'identity_type'])
                ->fullName('f_name', 'l_name')
                ->when(fn (SearchContext $c) => $c->moduleTypeIsNot('rental'))
                ->name(fn ($man) => trim($man->f_name . ' ' . $man->l_name))
                ->routes(fn (string $uri) => str_contains($uri, 'delivery-man/edit')
                    || str_contains($uri, 'delivery-man/preview')),

            SearchEntity::make('customer')
                ->model(User::class)
                ->prefix('Customer')
                ->type('customer')
                ->columns(['f_name', 'l_name', 'email', 'phone'])
                ->fullName('f_name', 'l_name')
                ->name(fn ($user) => trim($user->f_name . ' ' . $user->l_name))
                ->routes(fn (string $uri) => str_contains($uri, 'users/customer/view')),

            SearchEntity::make('pro-customer')
                ->model(ProCustomerSubscription::class)
                ->prefix('Pro Customer')
                ->with(['user'])
                ->relation('user', ['f_name', 'l_name', 'email', 'phone'], true)
                ->name(fn ($sub) => trim($sub->user?->f_name . ' ' . $sub->user?->l_name))
                ->searchParam(fn ($sub) => $sub->user?->f_name)
                ->routes(fn (string $uri, string $name) => str_contains($name, 'admin.pro-customer.list')),

            SearchEntity::make('employee-role')
                ->model(AdminRole::class)
                ->prefix('Employee Role')
                ->columns(['name'])
                ->name(fn ($role) => $role->name)
                ->routes(fn (string $uri) => str_contains($uri, 'custom-role') && str_contains($uri, 'edit')),

            SearchEntity::make('employee')
                ->model(Admin::class)
                ->prefix('Employee')
                ->columns(['f_name', 'l_name', 'email', 'phone'])
                ->fullName('f_name', 'l_name')
                ->query(function (Builder $query) {
                    $query->whereNotIn('id', array_filter([1, auth('admin')->id()]));
                })
                ->name(fn ($employee) => trim($employee->f_name . ' ' . $employee->l_name))
                ->routes(fn (string $uri) => str_contains($uri, 'employee') && str_contains($uri, 'edit')),

            SearchEntity::make('newsletter')
                ->model(Newsletter::class)
                ->prefix('Customers')
                ->columns(['email'])
                ->name(fn ($subscriber) => $subscriber->email)
                ->routes(fn (string $uri) => str_contains($uri, 'admin/users/customer/subscribed')),
        ];
    }

    private static function marketing(): array
    {
        return [
            SearchEntity::make('coupon')
                ->model(Coupon::class)
                ->prefix('Coupon')
                ->columns(['title', 'code', 'discount_type', 'coupon_type'])
                ->query(function (Builder $query, SearchContext $context) {
                    $query->where('created_by', 'admin')
                        ->when($context->hasModuleId(), fn (Builder $q) => $q->where('module_id', $context->moduleId));
                })
                ->moduleScoped()
                ->name(fn ($coupon) => $coupon->title)
                ->searchParam(fn ($coupon) => $coupon->title)
                ->routes(function (string $uri) {
                    $moduleType = config('module.current_module_type');
                    if ($moduleType === 'rental') {
                        return str_contains($uri, 'rental/coupon')
                            && ! str_contains($uri, 'status') && ! str_contains($uri, 'export');
                    }
                    if ($moduleType === 'ride-share') {
                        return str_contains($uri, 'ride-share/promotion/coupon-setup')
                            && ! str_contains($uri, 'status') && ! str_contains($uri, 'export');
                    }

                    return str_contains($uri, 'coupon/edit');
                }),

            SearchEntity::make('cashback')
                ->model(CashBack::class)
                ->prefix('Cashback')
                ->columns(['title', 'cashback_type'])
                ->name(fn ($cashback) => $cashback->title)
                ->searchParam(fn ($cashback) => $cashback->title)
                ->routes(function (string $uri) {
                    if (config('module.current_module_type') === 'rental') {
                        return str_contains($uri, 'rental/cashback') && ! str_contains($uri, 'status');
                    }

                    return str_contains($uri, 'cashback') && ! str_contains($uri, 'status');
                }),

            SearchEntity::make('banner')
                ->model(Banner::class)
                ->prefix('Banner')
                ->columns(['title', 'type'])
                ->query(function (Builder $query, SearchContext $context) {
                    $query->where('created_by', 'admin')
                        ->when($context->hasModuleId(), fn (Builder $q) => $q->where('module_id', $context->moduleId));
                })
                ->moduleScoped()
                ->name(fn ($banner) => $banner->title)
                ->routes(function (string $uri) {
                    if (str_contains($uri, 'status') || str_contains($uri, 'export')) {
                        return false;
                    }
                    $moduleType = config('module.current_module_type');
                    if ($moduleType === 'rental') {
                        return str_contains($uri, 'admin/rental/banner');
                    }
                    if ($moduleType === 'ride-share') {
                        return str_contains($uri, 'admin/ride-share/promotion/banner-setup');
                    }

                    return str_contains($uri, 'admin/banner');
                }),

            SearchEntity::make('notification')
                ->model(Notification::class)
                ->prefix('Notification')
                ->columns(['title', 'description'])
                ->name(fn ($notification) => $notification->title)
                ->searchParam(fn ($notification) => $notification->title)
                ->routes(function (string $uri, string $name) {
                    $target = config('module.current_module_type') === 'rental'
                        ? 'admin.rental.notification.list'
                        : 'admin.notification.add-new';

                    return str_contains($name, $target);
                }),

            SearchEntity::make('contact')
                ->model(Contact::class)
                ->prefix('Contact')
                ->columns(['name', 'email', 'subject', 'message', 'reply'])
                ->name(fn ($contact) => $contact->name)
                ->routes(fn (string $uri) => str_contains($uri, 'contact') && str_contains($uri, 'view')),
        ];
    }

    private static function finance(): array
    {
        return [
            SearchEntity::make('wallet-bonus')
                ->model(WalletBonus::class)
                ->prefix('Bonus')
                ->type('bonus')
                ->columns(['title', 'description', 'bonus_type'])
                ->name(fn ($bonus) => $bonus->title)
                ->routes(fn (string $uri) => str_contains($uri, 'wallet/bonus')),

            SearchEntity::make('store-disbursement')
                ->model(Disbursement::class)
                ->prefix('Store Disbursement')
                ->columns(['title', 'description'])
                ->query(fn (Builder $query) => $query->where('created_for', 'store'))
                ->routes(fn (string $uri) => str_contains($uri, 'store-disbursement') && str_contains($uri, 'details')),

            SearchEntity::make('dm-disbursement')
                ->model(Disbursement::class)
                ->prefix('Delivery Man Disbursement')
                ->columns(['title', 'description'])
                ->query(fn (Builder $query) => $query->where('created_for', 'delivery_man'))
                ->routes(fn (string $uri) => str_contains($uri, 'dm-disbursement') && str_contains($uri, 'details')),

            SearchEntity::make('withdraw-method')
                ->model(WithdrawalMethod::class)
                ->prefix('Withdraw Method')
                ->columns(['method_name'])
                ->extraText(function (Builder $query, string $like) {
                    $query->orWhereRaw("JSON_SEARCH(method_fields, 'one', ?) IS NOT NULL", ['%' . $like . '%']);
                })
                ->name(fn ($method) => $method->method_name)
                ->routes(fn (string $uri) => str_contains($uri, 'withdraw-method') && str_contains($uri, 'edit')),

            SearchEntity::make('wallet-transaction')
                ->model(WalletTransaction::class)
                ->prefix('Wallet Transaction')
                ->with(['user'])
                ->columns(['transaction_id', 'transaction_type', 'reference'])
                ->relation('user', ['f_name', 'l_name', 'phone', 'email'])
                ->name(fn ($tx) => trim($tx->user?->f_name . ' ' . $tx->user?->l_name))
                ->routes(fn (string $uri) => str_contains($uri, 'users/customer/wallet/report')),

            SearchEntity::make('loyalty-point')
                ->model(LoyaltyPointTransaction::class)
                ->prefix('Customer Loyalty Point')
                ->with(['user'])
                ->columns(['transaction_id', 'transaction_type', 'reference'])
                ->relation('user', ['f_name', 'l_name', 'phone', 'email'])
                ->name(fn ($tx) => trim($tx->user?->f_name . ' ' . $tx->user?->l_name))
                ->routes(fn (string $uri) => str_contains($uri, 'users/customer/loyalty-point/report')),

            SearchEntity::make('subscription-package')
                ->model(SubscriptionPackage::class)
                ->prefix('Subscription')
                ->columns(['package_name', 'colour', 'text'])
                ->name(fn ($package) => $package->package_name)
                ->routes(fn (string $uri) => str_contains($uri, 'subscription/subscriptionackage')),
        ];
    }

    private static function settings(): array
    {
        return [
            SearchEntity::make('zone')
                ->model(Zone::class)
                ->prefix('Zone')
                ->type('zone')
                ->columns(['name', 'display_name'])
                ->name(fn ($zone) => $zone->name)
                ->routes(fn (string $uri) => str_contains($uri, 'business-settings/zone')
                    && (str_contains($uri, 'edit') || str_contains($uri, 'zone/module-setup/'))),

            SearchEntity::make('module')
                ->model(Module::class)
                ->prefix('Module')
                ->columns(['module_name', 'module_type'])
                ->name(fn ($module) => $module->module_name)
                ->routes(fn (string $uri) => str_contains($uri, 'business-settings/module') && ! str_contains($uri, 'export')),
        ];
    }

    private static function rental(): array
    {
        if (! addon_published_status('Rental')) {
            return [];
        }

        $onlyRental = fn (SearchContext $c) => $c->moduleTypeIs('rental');

        return [
            SearchEntity::make('rental.provider')
                ->model(Store::class)
                ->prefix('Store')
                ->type('store')
                ->with(['vendor'])
                ->columns(['name', 'phone', 'email', 'address', 'meta_title', 'meta_description'])
                ->relation('vendor', ['f_name', 'l_name', 'email', 'phone'], true)
                ->query(self::byModule())
                ->when($onlyRental)
                ->name(fn ($store) => $store->name)
                ->variant(function ($store) {
                    return match ($store->vendor?->status) {
                        0 => 'denied',
                        null => 'pending',
                        default => 'active',
                    };
                })
                ->routes(function (string $uri, string $name, string $variant) {
                    if (! str_contains($uri, 'rental/provider')) {
                        return false;
                    }
                    if (str_contains($uri, 'withdraw-view') || str_contains($uri, 'transactions')) {
                        return false;
                    }
                    $extra = match ($variant) {
                        'denied' => 'deny-requests',
                        'pending' => 'pending-requests',
                        default => null,
                    };

                    return str_contains($uri, 'edit')
                        || ($variant !== 'active' && str_contains($uri, 'details'))
                        || ($extra !== null && str_contains($uri, $extra));
                }),

            SearchEntity::make('rental.trip')
                ->model(Trips::class)
                ->prefix('Trip')
                ->type('trip')
                ->with(['customer'])
                ->query(self::byModule())
                ->relation('customer', ['f_name', 'l_name', 'email', 'phone'], true)
                ->relation('provider', ['name', 'phone', 'email', 'address', 'meta_title', 'meta_description'])
                ->when($onlyRental)
                ->name(fn ($trip) => $trip->id)
                ->searchParam(fn ($trip) => $trip->id)
                ->routes(fn (string $uri) => str_contains($uri, 'rental/trip/details')
                    && ! str_contains($uri, 'transactions/rental/trip/details/')
                    && ! str_contains($uri, 'expense-report')
                    && ! str_contains($uri, 'export')),

            SearchEntity::make('rental.vehicle')
                ->model(Vehicle::class)
                ->prefix('Vehicle')
                ->type('Vehicle')
                ->columns(['name', 'description'])
                ->query(function (Builder $query, SearchContext $context) {
                    $query->when($context->hasModuleId(), function (Builder $query) use ($context) {
                        $query->whereHas('provider', fn (Builder $q) => $q->where('module_id', $context->moduleId));
                    });
                })
                ->when($onlyRental)
                ->name(fn ($vehicle) => $vehicle->name)
                ->routes(fn (string $uri) => str_contains($uri, 'vehicle/details') || str_contains($uri, 'vehicle/update')),

            SearchEntity::make('rental.category')
                ->model(VehicleCategory::class)
                ->prefix('Vehicle Category')
                ->type('VehicleCategory')
                ->columns(['name'])
                ->when($onlyRental)
                ->name(fn ($category) => $category->name)
                ->searchParam(fn ($category) => $category->name)
                ->routes(fn (string $uri) => str_contains($uri, 'rental/category/edit/')),

            SearchEntity::make('rental.brand')
                ->model(VehicleBrand::class)
                ->prefix('Vehicle Brand')
                ->type('VehicleBrand')
                ->columns(['name'])
                ->when($onlyRental)
                ->name(fn ($brand) => $brand->name)
                ->searchParam(fn ($brand) => $brand->name)
                ->routes(fn (string $uri) => str_contains($uri, 'rental/brand/edit')),

            SearchEntity::make('rental.review')
                ->model(VehicleReview::class)
                ->prefix('Vehicle Review')
                ->type('Vehicle_Review')
                ->with(['vehicle'])
                ->columns(['comment', 'reply'])
                ->idColumn('review_id')
                ->when($onlyRental)
                ->name(fn ($review) => $review->vehicle?->name)
                ->searchParam(fn ($review) => $review->vehicle?->name)
                ->routes(fn (string $uri) => str_contains($uri, 'vehicle/review-list')),
        ];
    }

    private static function rideShare(): array
    {
        if (! addon_published_status('RideShare')) {
            return [];
        }

        $rider = fn (string $suffix) => fn (string $uri) => str_contains($uri, 'admin/users/rider/' . $suffix);

        return [
            SearchEntity::make('ride.request')
                ->model(RideRequest::class)
                ->prefix('RideRequest')
                ->type('ride-request')
                ->moduleTypeTag('ride-share')
                ->with(['customer'])
                ->columns(['current_status', 'payment_method'])
                ->relation('customer', ['f_name', 'l_name', 'email', 'phone'], true)
                ->query(self::byModule())
                ->idColumn('ref_id')
                ->routeKey(fn ($ride) => $ride->ref_id)
                ->when(fn (SearchContext $c) => $c->moduleTypeIs('ride-share'))
                ->name(fn ($ride) => $ride->ref_id)
                ->searchParam(fn ($ride) => $ride->ref_id)
                ->routes(fn (string $uri) => str_contains($uri, 'ride-share/ride/details')
                    && ! str_contains($uri, 'expense-report')
                    && ! str_contains($uri, 'export')),

            SearchEntity::make('ride.rider')
                ->model(DeliveryMan::class)
                ->prefix('Rider')
                ->type('rider')
                ->moduleTypeTag('ride-share')
                ->columns(['f_name', 'l_name', 'email', 'phone', 'identity_type'])
                ->fullName('f_name', 'l_name')
                ->query(fn (Builder $query) => $query->rider())
                ->name(fn ($rider) => $rider->full_name)
                ->searchParam(fn ($rider) => $rider->id)
                ->routes($rider('preview')),

            SearchEntity::make('ride.vehicle-brand')
                ->model(RiderVehicleBrand::class)
                ->prefix('RiderVehicleBrand')
                ->type('rider-vehicle-brand')
                ->moduleTypeTag('ride-share')
                ->columns(['name'])
                ->name(fn ($brand) => $brand->name)
                ->searchParam(fn ($brand) => $brand->id)
                ->routes($rider('vehicle/brand/edit')),

            SearchEntity::make('ride.vehicle-model')
                ->model(RiderVehicleModel::class)
                ->prefix('RiderVehicleModel')
                ->type('rider-vehicle-model')
                ->moduleTypeTag('ride-share')
                ->columns(['name'])
                ->name(fn ($model) => $model->name)
                ->searchParam(fn ($model) => $model->id)
                ->routes($rider('vehicle/model/edit')),

            SearchEntity::make('ride.vehicle-category')
                ->model(RiderVehicleCategory::class)
                ->prefix('RiderVehicleCategory')
                ->type('rider-vehicle-category')
                ->moduleTypeTag('ride-share')
                ->columns(['name'])
                ->name(fn ($category) => $category->name)
                ->searchParam(fn ($category) => $category->id)
                ->routes($rider('vehicle/category/edit')),

            SearchEntity::make('ride.vehicle')
                ->model(RiderVehicle::class)
                ->prefix('RiderVehicle')
                ->type('rider-vehicle')
                ->moduleTypeTag('ride-share')
                ->columns(['name'])
                ->query(fn (Builder $query) => $query->whereNot('vehicle_request_status', 'pending'))
                ->name(fn ($vehicle) => $vehicle->name)
                ->searchParam(fn ($vehicle) => $vehicle->id)
                ->routes(fn (string $uri) => str_contains($uri, 'admin/users/rider/vehicle/show')
                    || str_contains($uri, 'admin/users/rider/vehicle/edit/')),
        ];
    }
}
