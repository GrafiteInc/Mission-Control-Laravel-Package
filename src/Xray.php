<?php

namespace Grafite\MissionControl;

use Grafite\MissionControl\Services\PerformanceAnalyzer;
use Unirest\Request as UniRequest;

class Xray
{
    public $token;

    protected $missionControlUrl;

    public function __construct($token = null)
    {
        if (!is_null($token)) {
            $this->token = $token;
        }

        $this->performanceAnalyzer = new PerformanceAnalyzer;
        $this->missionControlUrl = 'http://missioncontrol.test/api/xray';
    }

    /**
     * Send the exception to Mission control.
     *
     * @param Exeption $exception
     *
     * @return bool
     */
    public function analyzePerformance()
    {
        $headers = [
            'token' => $this->token,
        ];

        $query = $this->getPerformance();

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
    public function getPerformance()
    {
        return [
            'memory' => $this->performanceAnalyzer->getMemory(),
            'storage' => $this->performanceAnalyzer->getStorage(),
            'cpu' => $this->performanceAnalyzer->getCpu(),
        ];
    }
}
