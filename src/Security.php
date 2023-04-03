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
            // 'sqli' => [
            //     '#[\d\W](union select|union join|union distinct)[\d\W]#is',
            //     '#[\d\W](union|union select|insert|from|where|concat|into|cast|truncate|select|delete|having)[\d\W]#is',
            // ]
        ];

        $log = null;

        foreach ($attackTypes as $name => $patterns) {
            foreach ($patterns as $pattern) {
                if (! $match = $this->match($name, $pattern, $request->input(), $request)) {
                    continue;
                }

                $threat = $this->recordThreat($name, $request->input());

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

        if (in_array($name, ['xss', 'lfi', 'sqli']) && ! is_array($input)) {
            return preg_match($pattern, $input);
        }

        if (in_array($name, ['rfi']) && ! is_array($input)) {
            if (! $result = preg_match($pattern, $this->applyExceptions($input, $request))) {
                return false;
            }

            return $this->checkContent($result);
        }

        if (in_array($name, ['php']) && ! is_array($input)) {
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

            if (in_array($name, ['xss', 'lfi', 'sqli'])) {
                if (! $result = preg_match($pattern, $value)) {
                    continue;
                }
            }

            if (in_array($name, ['rfi', 'php'])) {
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
        $contents = @file_get_contents($value);

        if (!empty($contents)) {
            return (strstr($contents, '<?php') !== false);
        }

        return false;
    }
}
