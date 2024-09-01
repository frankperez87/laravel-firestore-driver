<?php

// config for LaravelFirestore/LaravelFirestoreDriver
return [
    'credentials' => env('FIREBASE_CREDENTIALS', env('GOOGLE_APPLICATION_CREDENTIALS')),
];
