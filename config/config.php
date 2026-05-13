<?php
$host = $_SERVER['HTTP_HOST'] ?? '';
$isLive = strpos($host, 'umuelections.fwh.is') !== false;
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'umu_vote',
        'user' => 'root',
        'pass' => '!Log19tan88',
        'charset' => 'utf8mb4',
    ],
    'environments' => [
        'local' => [
            'hosts' => ['localhost', '127.0.0.1'],
            'db' => [
                'host' => 'localhost',
                'name' => 'umu_vote',
                'user' => 'root',
                'pass' => '!Log19tan88',
                'charset' => 'utf8mb4',
            ],
        ],
        'live' => [
            'hosts' => ['umuelections.fwh.is'],
            'db' => [
                'host' => 'sql212.infinityfree.com',
                'name' => 'if0_41909216_umu_vote',
                'user' => 'if0_41909216',
                'pass' => 'Josbert001',
                'charset' => 'utf8mb4',
            ],
        ],
    ],
    'app' => [
        'base_url' => '',
        'event_name' => 'UMU Rubaga Varsity Ball',
        'event_date' => '',
        'allowed_domain' => 'stud.umu.ac.ug',
        'voting_open' => false,
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
        'redirect_uri' => $isLive
            ? 'https://umuelections.fwh.is/umu_vote/google-callback.php'
            : 'http://localhost/umu_vote/google-callback.php',
    ],
    'uploads' => [
        'contestants_dir' => __DIR__ . '/../uploads/contestants',
        'contestants_url' => 'uploads/contestants',
        'max_size' => 2 * 1024 * 1024,
        'allowed_types' => ['image/jpeg', 'image/png', 'image/webp'],
    ],
];

