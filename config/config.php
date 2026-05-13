<?php

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'umu_vote',
        'user' => 'root',
        'pass' => '!Log19tan88',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'base_url' => '/umu_vote',
        'event_name' => 'UMU Rubaga Varsity Ball',
        'event_date' => '2026-05-15 17:00:00',
        'allowed_domain' => 'stud.umu.ac.ug',
        'voting_open' => true,
        'voting_start' => '',
        'voting_end' => '',
        'results_public' => false,
        'category_limit' => 10,
        'admin_emails' => [
            'josbert.aijuka@stud.umu.ac.ug',
        ],
    ],
    'google' => [
        'client_id' => '601051630834-grdi2to42eub69ap1oltiqa80phkqter.apps.googleusercontent.com',
        'client_secret' => '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',
        'redirect_uri' => 'http://localhost/umu_vote/google-callback.php',
    ],
    'uploads' => [
        'contestants_dir' => __DIR__ . '/../uploads/contestants',
        'contestants_url' => 'uploads/contestants',
        'max_size' => 2 * 1024 * 1024,
        'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
    ],
];
