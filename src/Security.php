<?php

namespace Grafite\MissionControlLaravel;

use Grafite\MissionControl\SecurityService;

class Security
{
    public $securityService;

    public function __construct()
    {
        $this->securityService = new SecurityService(
            config('mission-control.api_token', null),
            config('mission-control.api_key', null)
        );
    }

    public function lookup($ipAddress)
    {
        return $this->securityService->lookup($ipAddress);
    }

    public function flagAsBadActor($ipAddress)
    {
        return $this->securityService->flag($ipAddress, 'flag');
    }

    public function unflagAsBadActor($ipAddress)
    {
        return $this->securityService->flag($ipAddress, 'unflag');
    }

    public function recordThreat($name, $payload)
    {
        return $this->securityService->recordThreat($name, $payload);
    }

    public function isMalicious($request)
    {
        // Nothing to scan on requests without input (typical GETs),
        // so skip the pattern sweep entirely.
        if (empty($request->input())) {
            return false;
        }

        $attackTypes = [
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
        ];

        $attackTypes = config('mission-control.attacks', $attackTypes);

        $input = $request->input();

        foreach ($attackTypes as $name => $patterns) {
            foreach ($patterns as $pattern) {
                if (! $match = $this->match($name, $pattern, $input, $request)) {
                    continue;
                }

                $threat = $this->recordThreat($name, $input);

                break;
            }
        }

        return $threat ?? false;
    }

    public function match($name, $pattern, $input, $request)
    {
        $result = false;

        if (! is_array($input) && ! is_string($input)) {
            return false;
        }

        $isRegex = in_array($name, ['xss', 'lfi', 'sqli']);
        $isRfi = ($name === 'rfi');
        $isPhp = ($name === 'php');

        if ($isRegex && ! is_array($input)) {
            return preg_match($pattern, $input);
        }

        if ($isRfi && ! is_array($input)) {
            $cleanedInput = $this->applyExceptions($input, $request);

            if (! preg_match($pattern, $cleanedInput)) {
                return false;
            }

            return $this->checkContent($cleanedInput);
        }

        if ($isPhp && ! is_array($input)) {
            return (stripos($input, $pattern) === 0);
        }

        foreach ($input as $key => $value) {
            if (empty($value)) {
                continue;
            }

            if (is_array($value)) {
                if (!$result = $this->match($name, $pattern, $value, $request)) {
                    continue;
                }

                break;
            }

            if ($isRegex) {
                if (! $result = preg_match($pattern, $value)) {
                    continue;
                }
            }

            if ($isRfi) {
                $cleanedValue = $this->applyExceptions($value, $request);

                if (! preg_match($pattern, $cleanedValue)) {
                    continue;
                }

                if (! $result = $this->checkContent($cleanedValue)) {
                    continue;
                }
            }

            if ($isPhp) {
                if (! $result = (stripos($value, $pattern) === 0)) {
                    continue;
                }
            }

            break;
        }

        return $result;
    }

    protected function applyExceptions($string, $request)
    {
        $exceptions = [];

        $domain = $request->getHost();

        $exceptions[] = 'http://' . $domain;
        $exceptions[] = 'https://' . $domain;
        $exceptions[] = 'http://&';
        $exceptions[] = 'https://&';

        return str_replace($exceptions, '', $string);
    }

    protected function checkContent($value)
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)) {
            return false;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => 2,
            ],
            'https' => [
                'timeout' => 2,
            ],
        ]);

        $contents = @file_get_contents($value, false, $context, 0, 8192);

        if (!empty($contents)) {
            return (strstr($contents, '<?php') !== false);
        }

        return false;
    }
}
