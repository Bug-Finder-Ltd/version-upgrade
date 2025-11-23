<?php
return [
    // update server URL for checking and downloading updates
    'update_server_url' => env('UPDATE_SERVER_URL', ''),

    // menu to inject into generalsettings at runtime and optionally permanently
    'menu' => [
        'version_upgradation' => [
            'route' => 'admin.version.upgradation',
            'icon' => 'fal fa-solar-system',
            'short_description' => 'Update your version to access new features and continue your journey more comfortably and efficiently.',
        ]
    ]
];
