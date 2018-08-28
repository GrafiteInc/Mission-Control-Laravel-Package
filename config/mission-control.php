<?php

return [

    /**
     * The API token for your project can be found
     * on the setting page for your project. This token
     * is manditory for nearly all calls to Mission Control.
     */
    'api_token' => env('MISSION_CONTROL_TOKEN'),

    /**
     * The webhook config is pulling from the webhook
     * you can find in your project settings. This gives you the
     * ability to send notifications to your teams on Mission Control
     * and even ping channels in Slack.
     */
    'webhook' => env('MISSION_CONTROL_WEBHOOK'),

    /**
     * The format is regarding your access.log files.
     *
     * You can set custom formats which work with your
     * access.log configs. By default we use configs that
     * work with FORGE setups. This is the NGINX default.
     */
    'format' => env('MISSION_CONTROL_FORMAT', '%a %l %u %t "%m %U %H" %>s %O "%{Referer}i" \"%{User-Agent}i"'),

    /**
     * You need to set this the file location of your
     * project's access.log files. If you set up your server
     * with FORGE then you should be able to set your access.log
     * to the following: /var/log/nginx/{domain}-access.log
     */
    'access_log_file_path' => env('MISSION_CONTROL_LOG', '/var/log/nginx/{domain}-access.log'),

];
