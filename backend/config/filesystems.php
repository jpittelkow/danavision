<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        'gcs' => [
            'driver' => 'gcs',
            'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
            'bucket' => env('GOOGLE_CLOUD_STORAGE_BUCKET'),
            'key_file_path' => env('GOOGLE_CLOUD_KEY_FILE'),
            'credentials' => [],
            'throw' => false,
        ],

        'azure' => [
            'driver' => 'azure',
            'connection_string' => env('AZURE_STORAGE_CONNECTION_STRING'),
            'container' => env('AZURE_STORAGE_CONTAINER'),
            'throw' => false,
        ],

        'do_spaces' => [
            'driver' => 's3',
            'key' => env('DO_SPACES_KEY'),
            'secret' => env('DO_SPACES_SECRET'),
            'region' => env('DO_SPACES_REGION', 'nyc3'),
            'bucket' => env('DO_SPACES_BUCKET'),
            'endpoint' => env('DO_SPACES_ENDPOINT', 'https://nyc3.digitaloceanspaces.com'),
            'use_path_style_endpoint' => false,
            'throw' => false,
        ],

        'minio' => [
            'driver' => 's3',
            'key' => env('MINIO_ACCESS_KEY'),
            'secret' => env('MINIO_SECRET_KEY'),
            'region' => env('MINIO_REGION', 'us-east-1'),
            'bucket' => env('MINIO_BUCKET'),
            'endpoint' => env('MINIO_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'throw' => false,
        ],

        'b2' => [
            'driver' => 's3',
            'key' => env('B2_APPLICATION_KEY_ID'),
            'secret' => env('B2_APPLICATION_KEY'),
            'region' => env('B2_REGION', 'us-west-002'),
            'bucket' => env('B2_BUCKET'),
            'endpoint' => env('B2_ENDPOINT'),
            'use_path_style_endpoint' => false,
            'throw' => false,
        ],

        'backups' => [
            'driver' => 'local',
            'root' => storage_path('app/backups'),
            'throw' => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
