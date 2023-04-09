# Mission Control Laravel Package

[![Build Status](https://github.com/GrafiteInc/Mission-Control-Laravel-Package/workflows/PHP%20Package%20Tests/badge.svg?branch=main)](https://github.com/GrafiteInc/Mission-Control-Laravel-Package/actions?query=workflow%3A%22PHP+Package+Tests%22)
[![Packagist](https://img.shields.io/packagist/dt/grafite/mission-control-laravel.svg)](https://packagist.org/packages/grafite/mission-control-laravel)
[![license](https://img.shields.io/github/license/mashape/apistatus.svg)](https://packagist.org/packages/grafite/mission-control-laravel)

**Mission Control Laravel Package** - Send data to Grafite's Mission Control system to stay in control of your applications.

Grafite's Mission Control is an elegant Application Performance Management system. Forget being inundated with hundreds of charts and complex configurations for CMS websites, custom E-commerce platforms etc. Utilize the simple user interface, with specific data for high demand moments. Get notifications within minutes of your system being overloaded, or high levels of errors being triggered. Set it up in less than 5 minutes with your next deployment, and take back your weekends.

## Requirements

1. PHP 7.3+

### Composer

```php
composer require grafite/mission-control-laravel
```

### Environment Variables

You need to add these variables to your environment. These will be the format for apps deployed with Laravel FORGE.

> Just remember you need to enable the logs on your server - see below for info

```
MISSION_CONTROL_USER_TOKEN={api_token}
MISSION_CONTROL_PROJECT_KEY={project_key}
MISSION_CONTROL_PROJECT_UUID={project_uuid} // required for JS error reporting
```

### Publishing Configuration
```php
php artisan vendor:publish --provider="Grafite\MissionControlLaravel\GrafiteMissionControlLaravelProvider"
```

## Blade Directives
You may wish to tap into the JavaScript error reporting feature. If so you need to do simply add the following to your header:

```
@missionControl
```

It will inject a JS error trace with an window listener for errors.

### Issues

By default Mission Control will log all exceptions and logs from any environment you specify in the config. You can disable this by removing all environments. Otherwise you can log things manually.

Issues lets you peak into your exceptions or any flagged messages you'd like to track. You can do so using the following methods:

```
use Grafite\MissionControlLaravel\Issue;

try {
    // do some code
} catch (Exception $e) {
    app(Issue::class)->exception($e);
}
```

Or if you just want to flag an potential issue or concern in your applicaiton:

```
use Grafite\MissionControlLaravel\Issue;

app(Issue::class)->log('Anything you want to say goes here', 'tag');
```

##### Tags

Tags can be any terminology you want, to help sort through your issues.

### Notify

You can easily give yourself tagged notifications for your applications throuh this handy service:

```
use Grafite\MissionControlLaravel\Notify;

app(Notify::class)->send('This is a title', 'info', 'This is a custom message');
```

### Mission Control Report

The Report CRON job for Mission Control lets you send back to Mission Control data about the performance of your application.

Then if you're using FORGE (default setup) you can add the following to the Scheduled Jobs:

```
COMMAND: php /home/forge/{domain}/artisan mission-control:report
USER: forge
FREQUENCY: Custom
CUSTOM SCHEDULE: */5 * * * *
```

![Forge Screenshot](https://getmissioncontrol.io/img/forge_screenshot.png)

If not simply add this to your `CRONTAB`:

```
*/5 * * * * php /{app-path}/artisan mission-control:report
```

## For Security Measures

```
COMMAND: php /home/forge/{domain}/artisan mission-control:virus-scan
USER: forge
FREQUENCY: Custom
CUSTOM SCHEDULE: 0 1 * * *
```

### Install ClamAV
```
sudo apt-get install clamav clamav-daemon mailutils -y
sudo service clamav-freshclam stop
sudo freshclam
```

### PHPUnit Settings

Your tests may begin to fail, if this happens just add these environment variables to your `phpunit.xml` files. You can also add them directly to your CI tool of choice.

```
<env name="MISSION_CONTROL_USER_TOKEN" value="testing"/>
<env name="MISSION_CONTROL_PROJECT_KEY" value="testing"/>
```

## License
Mission Control PHP Package is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)

### Bug Reporting and Feature Requests
Please add as many details as possible regarding submission of issues and feature requests

### Disclaimer
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
