<?php

namespace Grafite\MissionControl;

use Unirest\Request as UniRequest;

class Atlas
{
    public $token;

    protected $missionControlUrl;

    public function __construct($token = null)
    {
        if (!is_null($token)) {
            $this->token = $token;
        }

        $this->service = new \Grafite\MissionControl\Services\TrafficAnalyzer;
        $this->missionControlUrl = 'http://missioncontrol.test/api/atlas';
    }

    /**
     * Send the exception to Mission control.
     *
     * @param Exeption $exception
     *
     * @return bool
     */
    public function sendTrafficReport($log, $format)
    {
        $headers = [
            'token' => $this->token,
        ];

        $query = $this->getTraffic($log, $format);

        $response = UniRequest::post($this->missionControlUrl, $headers, $query);

        if ($response->code != 200) {
            error_log('Unable to message Grafite Mission Control, please confirm your token');
        }
    }

    /**
     * Collect data and set report details.
     *
     * @return array
     */
    public function getTraffic($log, $format)
    {
        return $this->service->analyze($log, $format);
    }
}
