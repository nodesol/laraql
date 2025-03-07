<?php

// config for Nodesol/LaraQL
return [
    "directories" => [
        app_path("Models"),
        app_path("GraphQL"),
    ],
    "models" => [
        "auto_include" => (bool) env("LARAQL_MODELS_AUTO_INCLUDE", false)
    ],
    "cache" => (bool) env("LARAQL_CACHE", !config("app.debug")),
];
