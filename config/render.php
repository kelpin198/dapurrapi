<?php

return [
    'initial_admin' => [
        'email' => env('INITIAL_ADMIN_EMAIL'),
        'name' => env('INITIAL_ADMIN_NAME', 'Admin Dapur'),
        'password' => env('INITIAL_ADMIN_PASSWORD'),
    ],
];
