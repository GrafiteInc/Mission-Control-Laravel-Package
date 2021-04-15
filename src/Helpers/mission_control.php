<?php

use Grafite\MissionControlLaravel\Issue;
use Grafite\MissionControlLaravel\Notify;

if (! function_exists('mission_control_issue')) {
    function mission_control_issue($message, $tag = null)
    {
        return app(Issue::class)->log($message, $tag = null);
    }
}

if (! function_exists('mission_control_notify')) {
    function mission_control_notify($title, $tag = null, $message = null)
    {
        return app(Notify::class)->send($title, $tag = null, $message = null);
    }
}
