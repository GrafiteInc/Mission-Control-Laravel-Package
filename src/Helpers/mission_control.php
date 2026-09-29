<?php

use Grafite\MissionControlLaravel\Email;
use Grafite\MissionControlLaravel\Issue;
use Grafite\MissionControlLaravel\Notify;

if (! function_exists('mission_control_issue')) {
    function mission_control_issue($message, $tag = null)
    {
        return app(Issue::class)->log($message, $tag);
    }
}

if (! function_exists('mission_control_notify')) {
    function mission_control_notify($title, $tag = null, $message = null)
    {
        return app(Notify::class)->send($title, $tag, $message);
    }
}

if (! function_exists('mission_control_email')) {
    function mission_control_email(array $payload)
    {
        return app(Email::class)->send($payload);
    }
}
