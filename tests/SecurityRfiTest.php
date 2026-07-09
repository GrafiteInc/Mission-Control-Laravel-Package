<?php

use Grafite\MissionControlLaravel\Security;
use Illuminate\Http\Request;

class SecurityRfiTest extends \TestCase
{
    public function testMatchUsesSanitizedUrlForRfiCheck()
    {
        $service = new class extends Security
        {
            public $checkedValue;

            protected function checkContent($value)
            {
                $this->checkedValue = $value;

                return true;
            }
        };

        $request = Request::create('/test', 'GET');
        $request->server->set('HTTP_HOST', 'safe.example');

        $result = $service->match('rfi', '#(http|ftp){1,1}(s){0,1}://.*#i', 'http://evil.example/payload.php', $request);

        $this->assertTrue((bool) $result);
        $this->assertSame('http://evil.example/payload.php', $service->checkedValue);
    }

    public function testMatchChecksRfiInNestedArrays()
    {
        $service = new class extends Security
        {
            public $checkedValue;

            protected function checkContent($value)
            {
                $this->checkedValue = $value;

                return true;
            }
        };

        $request = Request::create('/test', 'POST', [
            'payload' => [
                'url' => 'https://evil.example/loader.php',
            ],
        ]);
        $request->server->set('HTTP_HOST', 'safe.example');

        $result = $service->match(
            'rfi',
            '#(http|ftp){1,1}(s){0,1}://.*#i',
            $request->input(),
            $request
        );

        $this->assertTrue((bool) $result);
        $this->assertSame('https://evil.example/loader.php', $service->checkedValue);
    }

    public function testCheckContentReturnsFalseForInvalidUrl()
    {
        $service = app(Security::class);

        $method = new \ReflectionMethod(Security::class, 'checkContent');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'not-a-url');

        $this->assertFalse($result);
    }
}
