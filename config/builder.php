<?php

/*
|--------------------------------------------------------------------------
| Builder addon configuration (host-owned)
|--------------------------------------------------------------------------
|
| This file is owned by the HOST project, not the Builder module. The module
| stays identical across every project and only READS these keys via
| config('builder.*') (each read carries an inline default, so a missing key
| never fatals). Define / override the addon's behaviour for THIS project here.
|
| Laravel auto-loads every file in this directory, so no service-provider
| merge is needed — these values are authoritative.
|
*/

return [
    'cms_dashboard_route' => env('BUILDER_CMS_DASHBOARD_ROUTE', 'vendor.dashboard'),
    'default_platform_name'  => '6amMart',

    /*
     * Extra CKEditor plugins the vendor-panel rich-text editor must load from
     * public/assets/admin/ckeditor/plugins/. 6amMart's CKEditor build doesn't
     * compile Justify/Font/color buttons in, so they're loaded here; installs
     * whose build already includes them leave this empty. Merged with the
     * always-on 'uploadimage' plugin inside RichTextEditor.
     */
    'rich_text_editor' => [
        'extra_plugins' => 'justify,font,colorbutton,panelbutton',
    ],

    /*
     * Master switch for storefront wallet-family features: wallet payment,
     * partial payment, loyalty points, referral, and wallet cashback. When
     * false, the storefront hides all of those UI affordances and the
     * matching endpoints return 404. Host wallet logic is untouched —
     * balances stay in the database and re-appear if the flag is flipped
     * back on. Admin / vendor / mobile API V1 are unaffected.
     */
    'wallet_features_enabled' => false,

    /*
     * Storefront social login (Google / Facebook / Apple). Disabled for now:
     * the providers gate by registered origin / redirect-URI, which doesn't
     * work across arbitrary vendor sub-domains / custom domains without a
     * central auth broker. When false, the storefront hides all social buttons
     * and the `storefront.auth.social` endpoint 404s. Email/phone + OTP login
     * are unaffected. Flip back to true once the broker flow exists.
     */
    'social_login_enabled' => false,

    /*
     * Master switch for ALL outbound email triggered by a storefront request
     * (customer registration, email-verification OTP, password reset, order
     * placement / verification, wallet & refund notifications, …). When false,
     * the `SuppressStorefrontMail` middleware cancels every mail sent during a
     * storefront request (all 6amMart mailables are synchronous, so nothing
     * escapes to a queue worker) — so no storefront email goes out.
     *
     * Scope is the storefront ONLY: admin, vendor panel, and mobile API mail
     * are untouched (their routes don't carry this middleware). Flip to false
     * to run storefronts silently (e.g. white-label sites that handle their own
     * transactional email, or staging domains that shouldn't email real users).
     */
    'storefront_mail_enabled' => true,

    /*
     * Host capability manifest — the single place a host declares WHICH features
     * the storefront + builder should render and enforce, so the same addon
     * adapts per project. Read via Modules\Builder\Contracts\CapabilityProvider
     * (host adapter may also DERIVE data-driven flags). Every value here is the
     * 6amMart baseline = its current implicit behavior; other hosts override.
     *
     * Read in PHP:  app(CapabilityProvider)->capabilities($scope)->enabled('location.map')
     * Gate a route: ->middleware(RequireCapability::class.':features.wallet')
     * Read in JS:   useCapability('payment.cod')
     */
    'capabilities' => [
        'schemaVersion' => 1,

        // Storefront profile-edit field editability. Phone is the account's
        // login identity → locked; email is editable. Read on the client
        // (EditProfileModal disables the field) and enforced server-side in
        // CustomerAuthProvider::updateProfile.
        'profile' => [
            'phoneEditable' => false,
            'emailEditable' => true,
        ],

        // Business-model / item presentation.
        // itemPresentation: 'auto' (food→modal, else page) | 'modal' | 'page'.
        'modules' => ['mode' => 'multi', 'switcher' => true, 'itemPresentation' => 'auto'],

        // Currency. Only 'single' is implemented today; 'multi' is reserved.
        'currency' => ['mode' => 'single', 'switcher' => false],

        // Location / map / address book.
        'location' => [
            'enabled' => true, 'map' => true, 'currentLocation' => true,
            'zoneBased' => true, 'savedAddresses' => true,
            // Email input on every address is a 6Valley-only field; 6amMart
            // collects email only in guest checkout.
            'addressEmail' => false,
            // Extra address inputs rendered under name/phone in the address
            // form. 6amMart stores road/house/floor.
            'addressFields' => [
                ['key' => 'road',  'label' => 'address_form_street', 'half' => false],
                ['key' => 'house', 'label' => 'address_form_house',  'half' => true],
                ['key' => 'floor', 'label' => 'address_form_floor',  'half' => true],
            ],
        ],

        // Checkout surface.
        'checkout' => [
            'deliveryTypes' => ['home', 'takeaway', 'schedule'],
            'tips' => true, 'tipPresets' => [10, 15, 20, 40],
            'extraPackaging' => true, 'coupon' => true,
            'unavailableNote' => true, 'deliveryInstruction' => true,
            'orderNote' => false, 'savedAddress' => true,
        ],

        // Payment rails + flow. timing: 'after' (place→pay) is the only mode
        // implemented today; 'before' is reserved for a future pre-auth flow.
        'payment' => [
            'cod' => true, 'digital' => true, 'offline' => true,
            'wallet' => true, 'partial' => true,
            'timing' => 'after', 'retryReminder' => true,
        ],

        // Cross-cutting commerce features.
        'features' => [
            'wallet' => true, 'loyaltyPoint' => true, 'referral' => true,
            'reviews' => true, 'inbox' => true, 'pushNotif' => true,
            'guestCheckout' => true, 'reorder' => true, 'wishlist' => true, 'blog' => false,
            // Buy Now (instant checkout) button on the item modal / details page /
            // quick-view. Off for StackFood + 6amMart per product decision — the
            // storefront uses the add-to-cart flow only.
            'buyNow' => false,
            // Delivery-partner contact affordances on the order-details card.
            // 6amMart assigns a deliveryman with a reachable phone + storefront
            // inbox, so both the tap-to-call and chat actions are available.
            'deliveryManChat' => true, 'deliveryManCall' => true,
        ],

        // Auth methods (folds the existing social/login switches under one axis).
        'auth' => [
            'manual' => true, 'otp' => true, 'otpChannel' => 'sms',
            'social' => ['google' => true, 'facebook' => true, 'apple' => true],
            // Storefront forgot-password: `status` toggles the whole feature;
            // `modes` lists the allowed reset channels. 6amMart allows both.
            'forgotPassword' => ['status' => true, 'modes' => ['phone', 'email']],
        ],
    ],
];
