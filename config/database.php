<?php
/***************************************************************
 * Global Database Configuration
 **************************************************************/

return [
    /**
     * Default Database Driver
     */
    'default' => env('DB_CONNECTION', 'mysql'),

    /**
     * Database Stores
     */
    'connections' => [

        'mysql' => [
            'driver' => 'mysql',
            'host' => env('MYSQL_DB_HOST', '127.0.0.1'),
            'port' => env('MYSQL_DB_PORT', 3306),
            'database' => env('MYSQL_DB_DATABASE', ''),
            'username' => env('MYSQL_DB_USERNAME', ''),
            'password' => env('MYSQL_DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collection' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null
        ],

        'redis' => [

            'client' => 'phpredis', // predis/phpredis

            'default' => [
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'password' => env('REDIS_PASSWORD', ''),
                'port' => env('REDIS_PORT', 6379),
                'database' => env('REDIS_DATABASE', 0),
                'timeout' => env('REDIS_TIMEOUT', 0.0),
                'prefix' => env('REDIS_PREFIX', '')
            ],
            'session' => [
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'password' => env('REDIS_PASSWORD', ''),
                'port' => env('REDIS_PORT', 6379),
                'database' => env('SESSION_REDIS_DATABASE', 1),
                'timeout' => 0.0,
                'prefix' => env('SESSION_REDIS_PREFIX', 'key')
            ]
        ],
    ]

];
