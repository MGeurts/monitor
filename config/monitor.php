<?php

use App\Services\Stock\Clients\BolComClient;
use App\Services\Stock\Clients\OnlinefactClient;
use App\Services\Stock\Clients\WooCommerceClient;

return [

    /*
    |--------------------------------------------------------------------------
    | Master source
    |--------------------------------------------------------------------------
    |
    | The key of the source that holds the "real" stock level. All other
    | sources are compared against this one. Must match a "key" below.
    |
    */
    'master_source' => env('MONITOR_MASTER_SOURCE', 'onlinefact'),

    /*
    |--------------------------------------------------------------------------
    | Batch processing
    |--------------------------------------------------------------------------
    */
    'batch' => [
        // Queue that batch jobs are dispatched on.
        'queue' => env('MONITOR_BATCH_QUEUE', 'monitor'),

        // How many EANs may be checked concurrently is controlled by how many
        // `queue:work` workers you run against the queue above.
        'chunk' => 200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sources
    |--------------------------------------------------------------------------
    |
    | Every place a stock level for an EAN can come from. "driver" points to
    | the client class responsible for talking to that system. Each entry is
    | intentionally independent, so shops/accounts can be added or removed
    | without touching any code.
    |
    */
    'sources' => [

        'onlinefact' => [
            'label' => 'Onlinefact (ERP)',
            'group' => 'ERP',
            'type' => 'erp',
            'driver' => OnlinefactClient::class,
            'base_url' => env('ONLINEFACT_API_URL', 'https://api.onlinefact.be'),
            'api_key' => env('ONLINEFACT_API_KEY'),
            'api_secret' => env('ONLINEFACT_API_SECRET'),
        ],

        'dekookwinkel' => [
            'label' => 'De Kookwinkel',
            'group' => 'WooCommerce',
            'type' => 'woocommerce',
            'driver' => WooCommerceClient::class,
            'base_url' => env('WOOCOMMERCE_DEKOOKWINKEL_URL', 'https://dekookwinkel.be'),
            'consumer_key' => env('WOOCOMMERCE_DEKOOKWINKEL_KEY'),
            'consumer_secret' => env('WOOCOMMERCE_DEKOOKWINKEL_SECRET'),
        ],

        'koraly' => [
            'label' => 'Koraly BV',
            'group' => 'WooCommerce',
            'type' => 'woocommerce',
            'driver' => WooCommerceClient::class,
            'base_url' => env('WOOCOMMERCE_KORALY_URL', 'https://koralybv.com'),
            'consumer_key' => env('WOOCOMMERCE_KORALY_KEY'),
            'consumer_secret' => env('WOOCOMMERCE_KORALY_SECRET'),
        ],

        'outlet_elektro' => [
            'label' => 'Outlet Elektro',
            'group' => 'WooCommerce',
            'type' => 'woocommerce',
            'driver' => WooCommerceClient::class,
            'base_url' => env('WOOCOMMERCE_OUTLET_ELEKTRO_URL', 'https://outlet-elektro.be'),
            'consumer_key' => env('WOOCOMMERCE_OUTLET_ELEKTRO_KEY'),
            'consumer_secret' => env('WOOCOMMERCE_OUTLET_ELEKTRO_SECRET'),
        ],

        'bol_koraly_be' => [
            'label' => 'Koraly BV BE (bol.com)',
            'group' => 'Bol.com',
            'type' => 'bol',
            'driver' => BolComClient::class,
            'shop' => 'koraly',
            'country' => 'BE',
            'client_id' => env('BOL_KORALY_BE_CLIENT_ID'),
            'client_secret' => env('BOL_KORALY_BE_CLIENT_SECRET'),
        ],

        'bol_koraly_nl' => [
            'label' => 'Koraly BV NL (bol.com)',
            'group' => 'Bol.com',
            'type' => 'bol',
            'driver' => BolComClient::class,
            'shop' => 'koraly',
            'country' => 'NL',
            'client_id' => env('BOL_KORALY_NL_CLIENT_ID'),
            'client_secret' => env('BOL_KORALY_NL_CLIENT_SECRET'),
        ],

        'bol_outlet_elektro_be' => [
            'label' => 'Exellent Electro Riemst BE (bol.com)',
            'group' => 'Bol.com',
            'type' => 'bol',
            'driver' => BolComClient::class,
            'shop' => 'outlet_elektro',
            'country' => 'BE',
            'client_id' => env('BOL_OUTLET_ELEKTRO_BE_CLIENT_ID'),
            'client_secret' => env('BOL_OUTLET_ELEKTRO_BE_CLIENT_SECRET'),
        ],

        'bol_outlet_elektro_nl' => [
            'label' => 'Exellent Electro Riemst NL (bol.com)',
            'group' => 'Bol.com',
            'type' => 'bol',
            'driver' => BolComClient::class,
            'shop' => 'outlet_elektro',
            'country' => 'NL',
            'client_id' => env('BOL_OUTLET_ELEKTRO_NL_CLIENT_ID'),
            'client_secret' => env('BOL_OUTLET_ELEKTRO_NL_CLIENT_SECRET'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Bol.com
    |--------------------------------------------------------------------------
    */
    'bol' => [
        'auth_url' => env('BOL_AUTH_URL', 'https://login.bol.com/token'),
        'api_url' => env('BOL_API_URL', 'https://api.bol.com/retailer'),
        // Accept header version, see https://api.bol.com/retailer/public/Retailer-API
        'accept' => env('BOL_ACCEPT_HEADER', 'application/vnd.retailer.v11+json'),
    ],

];
