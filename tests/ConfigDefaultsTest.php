<?php

class ConfigDefaultsTest extends \TestCase
{
    public function testTrafficLoggingDefaultsToFalse()
    {
        $defaults = require __DIR__.'/../config/mission-control.php';

        $this->assertFalse($defaults['log_traffic']);
    }
}
