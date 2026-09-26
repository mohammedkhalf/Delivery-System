<?php

namespace App\Services\Search;

use App\Models\AccountTransaction;
use App\Models\AddOn;
use App\Models\Advertisement;
use App\Models\Banner;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\DeliveryMan;
use App\Models\DisbursementDetails;
use App\Models\DisbursementWithdrawalMethod;
use App\Models\EmployeeRole;
use App\Models\Expense;
use App\Models\FlashSaleItem;
use App\Models\Item;
use App\Models\ItemCampaign;
use App\Models\Order;
use App\Models\Review;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\SubscriptionBillingAndRefundHistory;
use App\Models\SubscriptionTransaction;
use App\Models\TempProduct;
use App\Models\VendorEmployee;
use App\Models\WithdrawRequest;
use Illuminate\Database\Eloquent\Builder;
use Modules\Rental\Entities\Trips;
use Modules\Rental\Entities\Vehicle;
use Modules\Rental\Entities\VehicleBrand;
use Modules\Rental\Entities\VehicleCategory;
use Modules\Rental\Entities\VehicleDriver;
use Modules\Rental\Entities\VehicleReview;

class VendorSearchRegistry
{
    public static function all(object $storeData, string $userType): array
    {
        return array_merge(
            self::store($storeData),
            self::catalog(),
            self::marketing($storeData->module_type),
            self::finance($storeData),
            self::team($userType, $storeData),
            self::rental(),
        );
    }

    private static function byStore(): callable
    {
        return function (Builder $query, SearchContext $context) {
            $query->where('store_id', $context->storeId);
        };
    }

    private static function notRental(): callable
    {
        return fn (SearchContext $context) => $context->moduleTypeIsNot('rental');
    }

    private static function store(object $storeData): array
    {
        return [
            SearchEntity::make('store.settings')
                ->model(Store::class)
                ->columns(['name', 'meta_title', 'meta_description'])
                ->type('settings')
                ->query(fn (Builder $q, SearchContext $c) => $q->where('id', $c->storeId))
                ->withoutIdSearch()
                ->routes(fn (string $uri) => str_contains($uri, 'business-settings/store-setup')),

            SearchEntity::make('store.info')
                ->model(Store::class)
                ->prefix('My Store')
                ->type('info')
                ->columns(['name', 'phone', 'email', 'address', 'announcement_message'])
                ->relation('vendor', ['f_name', 'l_name', 'email', 'phone'], true)
                ->query(fn (Builder $q, SearchContext $c) => $q->where('id', $c->storeId))
                ->withoutIdSearch()
                ->routes(fn (string $uri) => str_contains($uri, 'vendor-panel/store/view')),
        ];
    }

    private static function catalog(): array
    {
        return [
            SearchEntity::make('order')
                ->model(Order::class)
                ->prefix('Order')
                ->type('order')
                ->with(['customer'])
                ->columns(['order_status', 'payment_method'])
                ->relation('customer', ['f_name', 'l_name', 'email', 'phone'], true)
                ->extraText(function (Builder $query, string $like) {
                    $query->orWhereRaw("JSON_SEARCH(delivery_address, 'one', ?) IS NOT NULL", ['%' . $like . '%']);
                })
                ->query(fn (Builder $q, SearchContext $c) => $q->where('store_id', $c->storeId)->Notpos()->NotDigitalOrder())
                ->when(self::notRental())
                ->name(fn ($order) => $order->id)
                ->searchParam(fn ($order) => $order->id)
                ->routes(fn (string $uri) => str_contains($uri, 'order/details')
                    || (str_contains($uri, 'expense-report') && ! str_contains($uri, 'pos'))),

            SearchEntity::make('category')
                ->model(Category::class)
                ->prefix('Category')
                ->type('category')
                ->columns(['name'])
                ->query(fn (Builder $q, SearchContext $c) => $q->where(['module_id' => $c->moduleId, 'position' => 0]))
                ->when(self::notRental())
                ->name(fn ($category) => $category->name)
                ->searchParam(fn ($category) => $category->name)
                ->routes(fn (string $uri) => ! str_contains($uri, '-category/list')
                    && str_contains($uri, 'category/list')
                    && ! str_contains($uri, 'category/sub-category-list')),

            SearchEntity::make('category.sub')
                ->model(Category::class)
                ->prefix('Sub Category')
                ->type('category')
                ->columns(['name'])
                ->query(fn (Builder $q, SearchContext $c) => $q->where(['module_id' => $c->moduleId, 'position' => 1]))
                ->when(self::notRental())
                ->name(fn ($category) => $category->name)
                ->searchParam(fn ($category) => $category->name)
                ->routes(fn (string $uri) => str_contains($uri, 'category/sub-category-list')),

            SearchEntity::make('addon')
                ->model(AddOn::class)
                ->prefix('Addon')
                ->columns(['name'])
                ->query(self::byStore())
                ->when(fn (SearchContext $c) => $c->moduleTypeIs('food'))
                ->name(fn ($addon) => $addon->name)
                ->routes(fn (string $uri) => str_contains($uri, 'addon/edit')),

            SearchEntity::make('item')
                ->model(Item::class)
                ->prefix('Item')
                ->type('Item')
                ->columns(['name', 'description'])
                ->query(self::byStore())
                ->when(self::notRental())
                ->name(fn ($item) => $item->name)
                ->routes(fn (string $uri) => (str_contains($uri, 'item/view') || str_contains($uri, 'item/edit'))
                    && ! str_contains($uri, 'item/requested')),

            SearchEntity::make('temp-product')
                ->model(TempProduct::class)
                ->type('tempitem')
                ->columns(['name', 'description'])
                ->query(self::byStore())
                ->when(self::notRental())
                ->name(fn ($item) => $item->name)
                ->routes(fn (string $uri) => str_contains($uri, 'item/requested/item/view')),

            SearchEntity::make('campaign')
                ->model(Campaign::class)
                ->prefix('Campaign')
                ->type('campaign')
                ->columns(['title', 'description'])
                ->query(fn (Builder $q, SearchContext $c) => $q->running()->active()->module($c->moduleId))
                ->when(self::notRental())
                ->name(fn ($campaign) => $campaign->title)
                ->searchParam(fn ($campaign) => $campaign->title)
                ->routes(fn (string $uri) => str_contains($uri, 'campaign/list')),

            SearchEntity::make('campaign.item')
                ->model(ItemCampaign::class)
                ->prefix('Campaign')
                ->type('item-campaign')
                ->columns(['title', 'description'])
                ->query(self::byStore())
                ->when(self::notRental())
                ->name(fn ($campaign) => $campaign->title)
                ->searchParam(fn ($campaign) => $campaign->title)
                ->routes(fn (string $uri) => str_contains($uri, 'campaign/item/list')),

            SearchEntity::make('review')
                ->model(Review::class)
                ->prefix('reviews')
                ->with(['item'])
                ->columns(['comment', 'reply'])
                ->query(function (Builder $query, SearchContext $context) {
                    $query->whereHas('item', fn (Builder $q) => $q->where('store_id', $context->storeId));
                })
                ->when(self::notRental())
                ->searchParam(fn ($review) => $review->item?->name)
                ->routes(fn (string $uri) => str_contains($uri, 'vendor-panel/review')
                    && ! str_contains($uri, 'rental')
                    && ! str_contains($uri, 'export')),

            SearchEntity::make('flash-sale-item')
                ->model(FlashSaleItem::class)
                ->prefix('FlashSale')
                ->type('Flash Sale Item')
                ->with(['item'])
                ->query(function (Builder $query, SearchContext $context) {
                    $query->whereHas('item', fn (Builder $q) => $q->where('store_id', $context->storeId));
                })
                ->extraText(function (Builder $query, string $like) {
                    $query->orWhereHas('item', function (Builder $q) use ($like) {
                        $q->where(function (Builder $q) use ($like) {
                            $q->where('name', 'LIKE', '%' . $like . '%')
                                ->orWhere('description', 'LIKE', '%' . $like . '%');
                        });
                    });
                })
                ->when(fn (SearchContext $c) => $c->moduleTypeIs('grocery', 'ecommerce'))
                ->name(fn ($row) => $row->item?->name)
                ->searchParam(fn ($row) => $row->item?->name)
                ->routes(fn (string $uri) => str_contains($uri, 'item/flash-sale')),
        ];
    }

    private static function marketing(?string $moduleType): array
    {
        $isRental = $moduleType === 'rental';

        return [
            SearchEntity::make('coupon')
                ->model(Coupon::class)
                ->prefix('Coupon')
                ->columns(['title', 'code', 'discount_type', 'coupon_type'])
                ->query(fn (Builder $q, SearchContext $c) => $q->where('created_by', 'vendor')->where('store_id', $c->storeId))
                ->name(fn ($coupon) => $coupon->title)
                ->searchParam(fn ($coupon) => $coupon->title)
                ->routes(function (string $uri) use ($isRental) {
                    if (str_contains($uri, 'status') || str_contains($uri, 'export')) {
                        return false;
                    }
                    if ($isRental) {
                        return str_contains($uri, 'rental-coupon');
                    }

                    return (str_contains($uri, 'coupon/add-new') || str_contains($uri, 'coupon/update'))
                        && ! str_contains($uri, 'rental');
                }),

            SearchEntity::make('banner')
                ->model(Banner::class)
                ->prefix('banner')
                ->columns(['title', 'type'])
                ->query(fn (Builder $q, SearchContext $c) => $q->where('created_by', 'store')->where('data', $c->storeId))
                ->name(fn ($banner) => $banner->title)
                ->searchParam(fn ($banner) => $banner->title)
                ->routes(function (string $uri) use ($isRental) {
                    if (str_contains($uri, 'status') || str_contains($uri, 'export')) {
                        return false;
                    }
                    if ($isRental) {
                        return (str_contains($uri, 'rental-banner') || str_contains($uri, 'banner/edit'))
                            && str_contains($uri, 'rental');
                    }

                    return (str_contains($uri, 'banner/list') || str_contains($uri, 'banner/edit'))
                        && ! str_contains($uri, 'rental');
                }),

            SearchEntity::make('advertisement')
                ->model(Advertisement::class)
                ->prefix('Advertisement')
                ->columns(['title', 'description', 'add_type'])
                ->query(self::byStore())
                ->routes(fn (string $uri) => str_contains($uri, 'advertisement')
                    && (str_contains($uri, 'edit') || str_contains($uri, 'detail'))),
        ];
    }

    private static function finance(object $storeData): array
    {
        $entities = [
            SearchEntity::make('disbursement')
                ->model(DisbursementDetails::class)
                ->query(self::byStore())
                ->idColumn('disbursement_id')
                ->extraText(function (Builder $query, string $like) {
                    $query->orWhereHas('withdraw_method', function (Builder $q) use ($like) {
                        $q->where('method_name', 'LIKE', '%' . $like . '%')
                            ->orWhereRaw("JSON_SEARCH(method_fields, 'one', ?) IS NOT NULL", ['%' . $like . '%']);
                    });
                })
                ->name(fn ($row) => $row->disbursement_id)
                ->routes(fn (string $uri) => (str_contains($uri, 'disbursement-report') || str_contains($uri, 'wallet/disbursement-list'))
                    && ! str_contains($uri, 'disbursement-report-export')),

            SearchEntity::make('withdraw-request')
                ->model(WithdrawRequest::class)
                ->prefix('Withdraw Request')
                ->columns(['type', 'withdrawal_method_fields->account_name', 'withdrawal_method_fields->account_number', 'withdrawal_method_fields->email'])
                ->query(fn (Builder $q, SearchContext $c) => $q->where('vendor_id', $c->vendorId))
                ->routes(fn (string $uri) => str_contains($uri, 'wallet')
                    && ! str_contains($uri, 'disbursement-list')
                    && ! str_contains($uri, 'wallet-payment-list')
                    && ! str_contains($uri, 'method-list')
                    && ! str_contains($uri, 'export')
                    && ! str_contains($uri, 'subscription')),

            SearchEntity::make('account-transaction')
                ->model(AccountTransaction::class)
                ->columns(['method', 'ref'])
                ->query(function (Builder $query, SearchContext $context) {
                    $query->where('type', 'collected')
                        ->where('created_by', 'store')
                        ->where('from_id', $context->vendorId)
                        ->where('from_type', 'store');
                })
                ->routes(fn (string $uri) => str_contains($uri, 'wallet-payment-list')),

            SearchEntity::make('withdraw-method')
                ->model(DisbursementWithdrawalMethod::class)
                ->prefix('Withdraw Method')
                ->columns(['method_name'])
                ->extraText(function (Builder $query, string $like) {
                    $query->orWhereRaw("JSON_SEARCH(method_fields, 'one', ?) IS NOT NULL", ['%' . $like . '%']);
                })
                ->query(self::byStore())
                ->routes(fn (string $uri) => str_contains($uri, 'withdraw-method') && ! str_contains($uri, 'default')),

            SearchEntity::make('expense')
                ->model(Expense::class)
                ->columns(['type', 'order_id', 'trip_id'])
                ->query(fn (Builder $q, SearchContext $c) => $q->where('store_id', $c->storeId)->where('created_by', 'vendor'))
                ->routes(fn (string $uri) => str_contains($uri, 'expense-report')),
        ];

        if (($storeData->store_business_model ?? null) === 'commission') {
            return $entities;
        }

        return array_merge($entities, [
            SearchEntity::make('subscription')
                ->model(StoreSubscription::class)
                ->prefix('Subscription')
                ->with(['package'])
                ->query(self::byStore())
                ->relation('package', ['package_name', 'text'])
                ->routes(fn (string $uri) => str_contains($uri, 'subscriber-detail')),

            SearchEntity::make('subscription.transaction')
                ->model(SubscriptionTransaction::class)
                ->type('subscriber-transactions')
                ->columns(['reference'])
                ->query(self::byStore())
                ->searchParam(fn ($tx) => $tx->reference)
                ->routes(fn (string $uri) => str_contains($uri, 'subscriber-transactions')),

            SearchEntity::make('subscription.history')
                ->model(SubscriptionBillingAndRefundHistory::class)
                ->prefix('Subscription')
                ->columns(['transaction_type', 'reference'])
                ->query(self::byStore())
                ->routes(fn (string $uri) => str_contains($uri, 'subscriber-wallet-transactions')),
        ]);
    }

    private static function team(string $userType, object $storeData): array
    {
        $deliveryMan = SearchEntity::make('delivery-man')
            ->model(DeliveryMan::class)
            ->prefix('Delivery Man')
            ->type('deliveryMan')
            ->columns(['f_name', 'l_name', 'email', 'phone', 'identity_type'])
            ->fullName('f_name', 'l_name')
            ->query(self::byStore())
            ->when(fn (SearchContext $c) => $c->moduleTypeIsNot('rental') && (bool) ($storeData->sub_self_delivery ?? false))
            ->name(fn ($man) => trim($man->f_name . ' ' . $man->l_name))
            ->routes(fn (string $uri) => (str_contains($uri, 'delivery-man') && str_contains($uri, 'edit'))
                || str_contains($uri, 'preview'));

        if ($userType !== 'vendor') {
            return [$deliveryMan];
        }

        return [
            $deliveryMan,

            SearchEntity::make('employee-role')
                ->model(EmployeeRole::class)
                ->prefix('Employee Role')
                ->columns(['name'])
                ->query(self::byStore())
                ->searchParam(fn ($role) => $role->name)
                ->routes(fn (string $uri) => str_contains($uri, 'custom-role/create') && ! str_contains($uri, 'edit')),

            SearchEntity::make('employee')
                ->model(VendorEmployee::class)
                ->prefix('Employee')
                ->columns(['f_name', 'l_name', 'phone', 'email'])
                ->query(self::byStore())
                ->searchParam(fn ($employee) => trim($employee->f_name . ' ' . $employee->l_name))
                ->routes(fn (string $uri) => str_contains($uri, 'employee/list')
                    && ! str_contains($uri, 'update')
                    && ! str_contains($uri, 'export')),
        ];
    }

    private static function rental(): array
    {
        if (! addon_published_status('Rental')) {
            return [];
        }

        $onlyRental = fn (SearchContext $c) => $c->moduleTypeIs('rental');

        return [
            SearchEntity::make('rental.trip')
                ->model(Trips::class)
                ->prefix('Trip')
                ->type('trip')
                ->with(['customer'])
                ->relation('customer', ['f_name', 'l_name', 'email', 'phone'], true)
                ->query(fn (Builder $q, SearchContext $c) => $q->where('provider_id', $c->storeId))
                ->when($onlyRental)
                ->name(fn ($trip) => $trip->id)
                ->searchParam(fn ($trip) => $trip->id)
                ->routes(fn (string $uri) => (str_contains($uri, 'trip/details') || str_contains($uri, 'trip-report'))
                    && ! str_contains($uri, 'expense-report')
                    && ! str_contains($uri, 'export')),

            SearchEntity::make('rental.vehicle')
                ->model(Vehicle::class)
                ->prefix('Vehicle')
                ->type('Vehicle')
                ->columns(['name', 'description'])
                ->query(fn (Builder $q, SearchContext $c) => $q->where('provider_id', $c->storeId))
                ->when($onlyRental)
                ->name(fn ($vehicle) => $vehicle->name)
                ->routes(fn (string $uri) => str_contains($uri, 'vehicle/details') || str_contains($uri, 'vehicle/update')),

            SearchEntity::make('rental.category')
                ->model(VehicleCategory::class)
                ->prefix('VehicleCategory')
                ->type('VehicleCategory')
                ->columns(['name'])
                ->when($onlyRental)
                ->name(fn ($category) => $category->name)
                ->searchParam(fn ($category) => $category->name)
                ->routes(fn (string $uri) => str_contains($uri, 'vehicle-category/list')),

            SearchEntity::make('rental.brand')
                ->model(VehicleBrand::class)
                ->prefix('VehicleBrand')
                ->type('VehicleBrand')
                ->columns(['name'])
                ->when($onlyRental)
                ->name(fn ($brand) => $brand->name)
                ->searchParam(fn ($brand) => $brand->name)
                ->routes(fn (string $uri) => str_contains($uri, 'vehicle-brand/list')),

            SearchEntity::make('rental.driver')
                ->model(VehicleDriver::class)
                ->prefix('VehicleDriver')
                ->type('VehicleDriver')
                ->columns(['first_name'])
                ->query(fn (Builder $q, SearchContext $c) => $q->where('provider_id', $c->storeId))
                ->when($onlyRental)
                ->name(fn ($driver) => $driver->first_name)
                ->searchParam(fn ($driver) => $driver->first_name)
                ->routes(fn (string $uri) => str_contains($uri, 'driver/details') || str_contains($uri, 'driver/update')),

            SearchEntity::make('rental.review')
                ->model(VehicleReview::class)
                ->prefix('VehicleReview')
                ->type('Vehicle_Review')
                ->columns(['comment', 'reply'])
                ->idColumn('review_id')
                ->when($onlyRental)
                ->routes(fn (string $uri) => str_contains($uri, 'rental-reviews')),
        ];
    }
}
