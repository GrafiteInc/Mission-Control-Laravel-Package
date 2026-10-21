<?php

return [
    /**
     * The environments you want to run Mission Control in.
     */
    'environments' => [
        'production',
    ],

    /**
     * The queues you want to monitor.
     */
    'queues' => [
        'default' => 'database',
        'sync' => 'sync',
    ],

    /**
     * The duration of seconds at which a DB query takes too long.
     */
    'query_threshold' => 5,

    /**
     * The duration of seconds at which a page load takes too long.
     */
    'page_load_threshold' => 2.0,

    /**
     * Log JavaScript errors to Mission Control.
     */
    'log_javascript_errors' => false,

    /**
     * Log web traffic to Mission Control.
     */
    'log_traffic' => false,

    /**
     * Log the emails sent by your application to Mission Control.
     */
    'log_emails' => false,

    /**
     * Include the email bodies with the log. Mission Control encrypts
     * subjects, bodies, headers and recipients at rest, and purges
     * all email logs after 30 days.
     */
    'log_email_bodies' => true,

    /**
     * The maximum number of characters of each email body to send.
     */
    'email_body_limit' => 65536,

    /**
     * The log levels you wish to send to Mission Control.
     */
    'levels' => [
        'emergency',
        'alert',
        'critical',
        'error',
    ],

    /**
     * The API token can be found
     * on your user API Tokens page. A token
     * is manditory for all calls to Mission Control.
     */
    'api_token' => env('MISSION_CONTROL_USER_TOKEN'),

    /**
     * The API key for your project can be found
     * on the setting page for your project. This key
     * is manditory for all calls to Mission Control.
     */
    'api_key' => env('MISSION_CONTROL_PROJECT_KEY'),

    /**
     * The UUID for your project can be found
     * on the setting page for your project. This key
     * is manditory for webhook calls to Mission Control.
     *
     * It is required for JavaScript error reporting!
     */
    'api_uuid' => env('MISSION_CONTROL_PROJECT_UUID'),

    /**
     * The URL for Mission Control. Custom domains are an option.
     */
    'url' => env('MISSION_CONTROL_URL', 'https://missioncontrolapp.io'),

    /**
     * Requests the firewall should skip, such as load balancer health checks.
     * Paths use Request::is() patterns. User agents are only trusted when the
     * request comes from a private IP (ex. an AWS load balancer node), so
     * Route 53 health checks should have their path added to except.
     */
    'firewall' => [
        'except' => [
            'up',
        ],
        'health_check_user_agents' => [
            'ELB-HealthChecker',
        ],
    ],

    /**
     * Control your attack monitoring
     */
    'attacks' => [
        'xss' => [
            // Evil starting attributes
            '#(<[^>]+[\x00-\x20\"\'\/])(form|formaction|on\w*|xmlns|xlink:href)[^>]*>?#iUu',
            // javascript:, livescript:, vbscript:, mocha: protocols
            '!((java|live|vb)script|mocha|feed|data):(\w)*!iUu',
            '#-moz-binding[\x00-\x20]*:#u',
            // Unneeded tags
            '#</*(applet|meta|xml|blink|link|style|script|embed|object|iframe|frame|frameset|ilayer|layer|bgsound|title|base)[^>]*>?#i'
        ],
        'php' => [
            'bzip2://',
            'expect://',
            'glob://',
            'phar://',
            'php://',
            'ogg://',
            'rar://',
            'ssh2://',
            'zip://',
            'zlib://',
        ],
        'lfi' => [
            '#\.\/#is',
        ],
        'rfi' => [
            '#(http|ftp){1,1}(s){0,1}://.*#i',
        ],
    ]
];
