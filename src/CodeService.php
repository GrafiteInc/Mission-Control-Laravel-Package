<?php

namespace Grafite\MissionControlLaravel;

use Exception;
use Illuminate\Support\Facades\Http;
use Grafite\MissionControl\BaseService;
use Illuminate\Http\Client\ConnectionException;

class CodeService extends BaseService
{
    public $token;

    public $key;

    protected $missionControlUrl;

    public function __construct($token = null, $key = null)
    {
        $this->token = $token;
        $this->key = $key;
        $this->missionControlUrl = $this->missionControlDomain('status');
    }

    /**
     * Send code scan results to Mission Control.
     * Payload should include scan_type field.
     *
     * @param  array  $payload
     * @return bool
     */
    public function send(array $payload): bool
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'key' => $this->key,
        ];

        if (is_null($this->token)) {
            throw new Exception("Missing token", 1);
        }

        if (is_null($this->key)) {
            throw new Exception("Missing key", 1);
        }

        $query = [
            'data' => $payload,
        ];

        try {
            $response = Http::withHeaders($headers)->retry(3, 100)->post($this->missionControlUrl, $query);

            if ($response->status() != 200) {
                $this->error($response->reason());
            }

            return true;
        } catch (ConnectionException $th) {
            // There is no need to log this as an issue.
        }

        return false;
    }
}
