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

    public function testFirewallSkipsHealthRouteByDefault()
    {
        $defaults = require __DIR__.'/../config/mission-control.php';

        $this->assertContains('up', $defaults['firewall']['except']);
    }
}
