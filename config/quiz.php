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
    | Speech languages
    |--------------------------------------------------------------------------
    |
    | BCP-47 codes passed to the browser's speech synthesis for each study.
    |
    */

    'locales' => [
        'en-US' => 'English (US)',
        'en-GB' => 'English (UK)',
        'si-LK' => 'Sinhala (Sri Lanka)',
        'ta-LK' => 'Tamil (Sri Lanka)',
        'ta-IN' => 'Tamil (India)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Question categories offered in the editor (free text is also allowed)
    |--------------------------------------------------------------------------
    */

    'categories' => [
        'Animals', 'Birds', 'Sea Creatures', 'Insects',
        'Colors', 'Shapes', 'Numbers', 'Numbers - Counting', 'Letters', 'Letters - Capital Recognition',
        'Phonics - Beginning Sound', 'Phonics - Find the Image', 'Opposites',
        'Food', 'Fruits', 'Vegetables', 'Drinks',
        'Vehicles', 'Nature', 'Weather',
        'Body Parts', 'Clothes', 'Family', 'Feelings',
        'Sports', 'Musical Instruments', 'Household', 'School', 'Objects - Identification',
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
