<?php

use Grafite\MissionControlLaravel\Issue;
use Grafite\MissionControlLaravel\Notify;

if (! function_exists('mission_control_issue')) {
    function mission_control_issue($message, $tag)
    {
        return app(Issue::class)->log($message, $tag);
    }
}

if (! function_exists('mission_control_notify')) {
    function mission_control_notify($title, $tag, $message)
    {
        return app(Notify::class)->send($title, $tag, $message);
    }
}
