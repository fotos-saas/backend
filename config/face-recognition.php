<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CompreFace Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for CompreFace face recognition service
    | Supports face detection, gender detection, age estimation, and pose analysis.
    |
    */

    'compreface' => [
        'url' => env('COMPREFACE_API_URL', 'http://compreface-api:8080'),
        'api_key' => env('COMPREFACE_API_KEY', ''), // Detection Service
        'recognition_api_key' => env('COMPREFACE_RECOGNITION_API_KEY', ''), // Recognition Service
        'timeout' => env('COMPREFACE_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Face Recognition Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for face detection and grouping thresholds
    |
    */

    'confidence_threshold' => env('FACE_CONFIDENCE_THRESHOLD', 0.7),

    /*
    |--------------------------------------------------------------------------
    | Detection Settings
    |--------------------------------------------------------------------------
    */

    'detection' => [
        'limit' => 10, // Max faces to detect per image
        'det_prob_threshold' => 0.8, // Minimum detection probability
    ],

    /*
    |--------------------------------------------------------------------------
    | Grouping Settings
    |--------------------------------------------------------------------------
    */

    'grouping' => [
        'similarity_threshold' => 0.75, // Minimum similarity to group faces together (0.75 = more matches)
        'auto_name_prefix' => 'Személy', // Auto-generated group name prefix
    ],

];
