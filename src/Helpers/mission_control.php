<?php

use Grafite\MissionControlLaravel\Email;
use Grafite\MissionControlLaravel\Document;
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

if (! function_exists('mission_control_document')) {
    function mission_control_document($title, $content, $type = null, array $options = [])
    {
        return app(Document::class)->push($title, $content, $type, $options);
    }
}
