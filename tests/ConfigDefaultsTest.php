<?php

class ConfigDefaultsTest extends \TestCase
{
    public function testTrafficLoggingDefaultsToFalse()
    {
        $defaults = require __DIR__.'/../config/mission-control.php';

        $this->assertFalse($defaults['log_traffic']);
    }

    public function testEmailLoggingDefaultsToFalse()
    {
        $defaults = require __DIR__.'/../config/mission-control.php';

        $this->assertFalse($defaults['log_emails']);
    }

    public function testSchedulingDefaultsToFalse()
    {
        $defaults = require __DIR__.'/../config/mission-control.php';

        $this->assertFalse($defaults['schedule']['enabled']);
    }

    public function testFirewallSkipsHealthRouteByDefault()
    {
        $defaults = require __DIR__.'/../config/mission-control.php';

        $this->assertContains('up', $defaults['firewall']['except']);
    }

    public function testLongRunningMonitorDefaults()
    {
        $defaults = require __DIR__.'/../config/mission-control.php';

        $this->assertTrue($defaults['long_running']['jobs']['enabled']);
        $this->assertSame(60, $defaults['long_running']['jobs']['threshold']);
        $this->assertTrue($defaults['long_running']['tasks']['enabled']);
        $this->assertSame(300, $defaults['long_running']['tasks']['threshold']);
        $this->assertSame(15, $defaults['long_running']['cooldown']);
    }
}
