# Omnipulse Reporter

**Omnipulse Reporter** - A elegantly simple way to generate tests for Laravel.

Unify all your logging and exception messages into the most elegant web service imaginable. Want to get all your important data fed straight to your slack channels? Need direct contact regarding your app status or errors? Checkout (https://omnipulse.io)[https://omnipulse.io]

## Requirements

1. PHP 5.6+
3. Laravel 5.2+

### Composer

Start a new Laravel project:
```php
composer create-project laravel/laravel your-project-name
```

Then run the following to add LaraTest
```php
composer require omnipulse/reporter
```

/home/vagrant/Code/_projects/Grafite/Telescope/bin/xray 3114c8e096f14fd8f28b9eb239fc6b16

sudo su sed -i -e 's/access_log off;/access_log \/var\/log\/nginx\/builder.grafite.test-access.log;/g' /etc/nginx/sites-available/builder.grafite.test

sudo su && sed -i -e 's/access_log off;/access_log \/var\/log\/nginx\/builder.grafite.test-access.log;/g' /etc/nginx/sites-available/builder.grafite.test

```
*/5 * * * * /home/vagrant/Code/_projects/Grafite/Telescope/bin/xray 3114c8e096f14fd8f28b9eb239fc6b16
```



### Token

Set up your app/ website on omnipulse.io and get your token. Then in your app in the `config/services.php` file add:

```
'omnipulse' => [
    'token' => env('OMNIPULSE_TOKEN'),
],
```

Then add the token to your env file:

```
OMNIPULSE_TOKEN={{some token string}}
```

### Providers

```php
Omnipulse\Reporter\OmnipulseReporterProvider::class
```

### Use Cases

Here are some basic use cases.

If you want to log something for your own information
```
app(Omnipulse\Reporter\Relay::class)->log('message', 'flag');
relay_log('message', 'flag');
```

Want to collect your exception calls?
```
app(Omnipulse\Reporter\Relay::class)->exception('message', 'flag');
relay_exception($exception);
```

## License
Omnipulse Reporter is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)

### Bug Reporting and Feature Requests
Please add as many details as possible regarding submission of issues and feature requests

### Disclaimer
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
