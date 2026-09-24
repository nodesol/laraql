<?php

// config for Nodesol/LaraQL
return [
    'directories' => [
        app_path('Models'),
        app_path('GraphQL'),
    ],
    'models' => [
        /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
        'auto_include' => (bool) env('LARAQL_MODELS_AUTO_INCLUDE', false),
    ],
    /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
    'cache' => (bool) env('LARAQL_CACHE', ! config('app.debug')),
    // The store the generated SDL is remembered in. Unset (the default) means the default store
    // of the application, which already follows `CACHE_STORE`. It must stay a string: casting
    // the store name to a boolean made `LARAQL_CACHE_STORE=redis` resolve to a store named `1`
    // and made an empty `CACHE_STORE` fail with "Cache store [] is not defined".
    /** @phpstan-ignore larastan.noEnvCallsOutsideOfConfig */
    'cache_store' => env('LARAQL_CACHE_STORE'),
];
