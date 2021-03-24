<?php

namespace Grafite\MissionControlLaravel;

use Grafite\MissionControl\IssueService;

class Issue
{
    public function __construct()
    {
        $this->issueService = new IssueService(
            config('mission-control.api_token', null),
            config('mission-control.api_key', null)
        );

        $this->issueService->setBaseRequest([
            'report_referer' => request()->server->get('HTTP_REFERER'),
            'report_user_agent' => request()->server->get('HTTP_USER_AGENT'),
            'report_host' => request()->server->get('HTTP_HOST'),
            'report_server_name' => request()->server->get('SERVER_NAME'),
            'report_remote_addr' => request()->server->get('REMOTE_ADDR'),
            'report_server_software' => request()->server->get('SERVER_SOFTWARE'),
            'report_uri' => request()->server->get('REQUEST_URI'),
            'report_time' => request()->server->get('REQUEST_TIME'),
            'report_method' => request()->server->get('REQUEST_METHOD'),
            'report_query' => request()->server->get('QUERY_STRING'),
            'app_base' => base_path(),
        ]);
    }

    public function exception($exception)
    {
        return $this->issueService->exception($exception);
    }

    public function log($message, $tag)
    {
        return $this->issueService->log($message, $tag);
    }
}
