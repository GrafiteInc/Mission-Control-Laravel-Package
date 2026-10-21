<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Grafite\MissionControlLaravel\Security;
use Grafite\MissionControlLaravel\Middleware\Firewall;

class FirewallTest extends TestCase
{
    public $security;

    public function setUp(): void
    {
        parent::setUp();

        Config::set('mission-control.environments', ['testing']);

        $this->security = Mockery::mock(Security::class);
        $this->security->shouldReceive('isMalicious')->andReturn(false);

        app()->instance(Security::class, $this->security);
    }

    protected function runFirewall($uri, $ipAddress, $userAgent = 'Mozilla/5.0')
    {
        $request = Request::create($uri, 'GET', [], [], [], [
            'REMOTE_ADDR' => $ipAddress,
            'HTTP_USER_AGENT' => $userAgent,
        ]);

        $passed = false;

        (new Firewall)->handle($request, function () use (&$passed) {
            $passed = true;

            return 'ok';
        });

        $this->assertTrue($passed);
    }

    public function testExcludedPathSkipsFirewall()
    {
        $this->security->shouldNotReceive('lookup');

        $this->runFirewall('/up', '8.8.8.8');
    }

    public function testHealthCheckFromPrivateIpSkipsFirewall()
    {
        $this->security->shouldNotReceive('lookup');

        $this->runFirewall('/', '10.0.1.23', 'ELB-HealthChecker/2.0');
    }

    public function testHealthCheckUserAgentFromPublicIpIsChecked()
    {
        $this->security->shouldReceive('lookup')->once()->andReturn([true, true]);

        $this->runFirewall('/', '8.8.8.8', 'ELB-HealthChecker/2.0');
    }

    public function testRegularRequestIsChecked()
    {
        $this->security->shouldReceive('lookup')->once()->andReturn([true, true]);

        $this->runFirewall('/', '8.8.8.8');
    }
}
