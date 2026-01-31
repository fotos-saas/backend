<?php

// V1: Egyszerűsített, 2 mód
return [
    'modes' => [
        'normal' => [
            'key' => 'normal',
            'emoji' => '🔔',
            'label' => 'normál',
            'description' => 'Szavazások, bökések, válaszok, hirdetmények',
            'maxPushPerDay' => 3,
            'categories' => ['announcements', 'mentions', 'votes', 'pokes', 'replies'],
        ],
        'quiet' => [
            'key' => 'quiet',
            'emoji' => '🔕',
            'label' => 'csendes',
            'description' => 'Csak kritikus értesítések',
            'maxPushPerDay' => 1,
            'categories' => ['announcements', 'mentions'],
        ],
        // V2-ben bővíthető:
        // 'chill' => [
        //     'key' => 'chill',
        //     'emoji' => '🌙',
        //     'label' => 'pihenhető',
        //     'description' => 'Legfontosabb értesítések',
        //     'maxPushPerDay' => 2,
        //     'categories' => ['announcements', 'mentions', 'votes'],
        // ],
        // 'active' => [
        //     'key' => 'active',
        //     'emoji' => '🔥',
        //     'label' => 'aktív',
        //     'description' => 'Mindenről értesítést kapok',
        //     'maxPushPerDay' => 10,
        //     'categories' => ['all'],
        // ],
    ],

    'type_to_category' => [
        'poke_received' => 'pokes',
        'poke_reaction' => 'pokes',
        'vote_created' => 'votes',
        'vote_ending' => 'votes',
        'vote_closed' => 'votes',
        'mention' => 'mentions',
        'reply' => 'replies',
        'announcement' => 'announcements',
        'event_reminder' => 'events',
        'samples_added' => 'samples',
    ],

    'cleanup' => [
        'read_after_days' => 90,
        'push_logs_after_days' => 30,
    ],

    'rate_limits' => [
        'min_gap_hours' => 2,
    ],
];
