# Mission Control Laravel Package

[![Build Status](https://travis-ci.org/GrafiteInc/Mission-Control-Laravel-Package.svg?branch=master)](https://travis-ci.org/GrafiteInc/Mission-Control-Laravel-Package)
[![Packagist](https://img.shields.io/packagist/dt/grafite/mission-control-laravel.svg)](https://packagist.org/packages/grafite/mission-control-laravel)
[![license](https://img.shields.io/github/license/mashape/apistatus.svg)](https://packagist.org/packages/grafite/mission-control-laravel)

**Mission Control Laravel Package** - Send data to Grafite's Mission Control system to stay in control of your applications.

Grafite's Mission Control is an elegant Application Performance Management system. Forget being inundated with hundreds of charts and complex configurations for CMS websites, custom E-commerce platforms etc. Utilize the simple user interface, with specific data for high demand moments. Get notifications within minutes of your system being overloaded, or high levels of errors being triggered. Set it up in less than 5 minutes with your next deployment, and take back your weekends.

## Requirements

1. PHP 7.1+

### Composer

```php
composer require grafite/mission-control-laravel
```

### Issues

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

app(Issue::class)->log('Anything you want to say goes here', 'flag');
```

##### Flags

Flags can be any terminology you want, to help sort through your issues.

### Webhook

You can easily tie the webhooks into your application with this package using class and method:

```
use Grafite\MissionControlLaravel\Webhook;

app(Webhook::class)->send('This is a title', 'This is a custom message', 'info');
```

### Mission Control Report

The Report CRON job for Mission Control lets you send back to Mission Control data about traffic and performance of your application. To set this up, simply turn on `access.log` files in your server set up, for developers using FORGE you can do this by running these commands replacing {domain} with your app's domain.

```
sudo su
sed -i -e 's/access_log off;/access_log \/var\/log\/nginx\/{domain}-access.log;/g' /etc/nginx/sites-available/{domain}
```

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

## License
Mission Control PHP Package is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)

### Bug Reporting and Feature Requests
Please add as many details as possible regarding submission of issues and feature requests

### Disclaimer
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
