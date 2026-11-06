<?php

return [

    /*
    |--------------------------------------------------------------------------
    | First admin account
    |--------------------------------------------------------------------------
    |
    | Created by `php artisan db:seed`. Set real values in .env before seeding
    | in production, then change the password from the admin dashboard.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrator'),
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD', 'change-me-now'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */

    'uploads' => [
        'disk' => env('QUIZ_UPLOAD_DISK', 'public'),
        'max_kb' => (int) env('QUIZ_UPLOAD_MAX_KB', 10240),
    ],

];
