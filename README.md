## Inphinit Proxy

> **NOTE:** In development

Until version 1.x this project focused on understanding the use of proxy for the *html2canvas* library, from version 2.0 onwards the use of this project allows many more configurations and adaptations for varied needs, being able to be used for different objectives.

Despite being part of Inphinit, this project works completely independently, so you can use it with any PHP framework or even without frameworks. Although the project is stand-alone, you can adopt the Inphinit framework for new projects. It is a lightweight framework that can execute many requests per second, surpassing many other frameworks, see: https://inphinit.github.io

## Proxies for other scripting languages

You do not use PHP, but need html2canvas working with proxy, see other proxies:

* [html2canvas proxy in asp.net (csharp)](https://github.com/brcontainer/html2canvas-csharp-proxy)
* [html2canvas proxy in asp classic (vbscript)](https://github.com/brcontainer/html2canvas-asp-vbscript-proxy)
* [html2canvas proxy in python (work any framework)](https://github.com/brcontainer/html2canvas-proxy-python)

## Installing

To install it you must have at least _PHP 5.4_, but it is recommended that you use _PHP 8_ due to PHP support issues, read:

- https://www.php.net/supported-versions.php
- https://www.php.net/eol.php

You can install via composer:

```bash
composer install inphinit/proxy
```

Then add this to your script or controller:

```php
use Inphinit\Proxy\Proxy;
use Inphinit\Proxy\Drivers\CurlDriver;
use Inphinit\Proxy\Drivers\StreamDriver;

$proxy = new Proxy();

// Set drivers used for download
$proxy->setDrivers([
    CurlDriver::class,
    StreamDriver::class
]);

// Set temporary location
$proxy->setTemporary('php://temp');

// Execute download
$proxy->download($url);

// Display raw output
$proxy->response();
```

If you are not using web frameworks, you can download the release from https://github.com/inphinit/inphinit-php-proxy/releases/tag/2.0.0, then extract the contents and move it to the web server and rename the folder, like this (optional):

```bash
mv inphinit-php-proxy-2.0.0 proxy
```

## Configure html2canvas

If you are using a web framework, simply point to the address of the route you configured to use the proxy, for example:

```javascript
html2canvas(document.getElementById('container'), {
    logging: true,
    proxy: '/proxy'
}).then((canvas) => {
    canvas.toBlob((blob) => { });
});
```

If you have manually downloaded it to use on your server, you can use the `proxy.php` script, a example:

```javascript
html2canvas(document.getElementById('container'), {
    logging: true,
    proxy: '/proxy/proxy.php'
}).then((canvas) => {
    canvas.toBlob((blob) => { });
});
```

## Common issues and solutions

When adding an image that belongs to another domain in `<canvas>` and after that try to export the canvas
for a new image, a security error occurs (actually occurs is a security lock), which can return the error:

> SecurityError: DOM Exception 18
>
> Error: An attempt was made to break through the security policy of the user agent.

If using Google Maps (or google maps static) you can get this error in console:

> Google Maps API error: MissingKeyMapError

You need get a API Key in: https://developers.google.com/maps/documentation/javascript/get-api-key

If you get this error:

> Access to Image at 'file:///...' from origin 'null' has been blocked by CORS policy: Invalid response. Origin 'null' is therefore not allowed access.

Means that you are not using an HTTP server, html2canvas does not work over the `file:///` protocol, use Apache, Nginx or IIS with PHP for work.

## Debuging with Web Console from DevTools

If you have any issue is recommend to analyze the log with the Web Console tab and requests with Network tab from your browser, see documentations:

* Firefox: https://firefox-source-docs.mozilla.org/devtools-user/
* Chrome: https://developer.chrome.com/docs/devtools
* Microsoft Edge: https://learn.microsoft.com/pt-br/microsoft-edge/devtools-guide-chromium/landing/

An alternative is to debug issues by accessing the link directly:

`http://[DOMAIN]/[PATH]/proxy?url=http%3A%2F%2Fmaps.googleapis.com%2Fmaps%2Fapi%2Fstaticmap%3Fcenter%3D40.714728%2C-73.998672%26zoom%3D12%26size%3D800x600%26maptype%3Droadmap%26sensor%3Dfalse%261&callback=html2canvas_0`

Replace `[DOMAIN]` by your domain (eg. 127.0.0.1) and replace `[PATH]` by your project folder (eg.: `project-1/test`), something like:

`http://localhost/project-1/test/proxy?url=http%3A%2F%2Fmaps.googleapis.com%2Fmaps%2Fapi%2Fstaticmap%3Fcenter%3D40.714728%2C-73.998672%26zoom%3D12%26size%3D800x600%26maptype%3Droadmap%26sensor%3Dfalse%261&callback=html2canvas_0`

## Setup proxy

Method | Description
--- | ---
`setOptions(string $key, mixed $value): void` | Setup generic options
`getOptions([string $key]): mixed` | Get generic options
`setDrivers(array $drivers): void` | Set drivers used for download resource
`urls([array $urls]): array` | Get or redefine allowed urls
`types([array $types]): array` | Get or redefine allowed content-types
`setTemporary(string $path): void` | Set temporary handle path, eg.: /mnt/storage/, php://temp, php://memory
`getTemporary(): string` | Get temporary stream
`setPublic(string $storage, string $url): void` | Set public storage and public URL for use with JSONP
`download(string $url): void` | Perform download
`setHttpCacheTime(int $seconds): void` | Enable or disable cache for `respose()` or `jsonp()`
`response(): void` | Dump response to output
`jsonp(string $callback[, bool $public]): void` | Output JSONP callback with URL or data URI content
`getContents([int $length[, int $offset]]): string` | If last download was successful, contents will be returned|null
`getContentType(): string` | If last download was successful, content-type will be returned|null
`getHttpStatus(): int` | If last download was successful, HTTP status will be returned|null
`getLastErrorCode(): int` | If last download was failed, error code will be returned|null
`getLastErrorMessage(): void` | If last download was failed, error message will be returned
`resetTemporary(): void` | Reset temporary contents

Setup Curl driver use generic options with `'curl'` in first param, eg.: `$proxy->setOptions('curl', [ ... ]);`, a sample for change SSL version:

```php
$proxy->setOptions('curl', [
    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_3
]);
```

A example for disable SSL verify (for local tests):

```php
$proxy->setOptions('curl', [
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_SSL_VERIFYPEER => false
]);
```

For more constants options for use with `$proxy->setOptions('curl', [ ... ]);`, see: https://www.php.net/manual/en/curl.constants.php

For setup Stream driver use generic options with `'stream'` in first param, eg.: `$proxy->setOptions('stream', [ ... ]);`, a sample for set User-Agent string:

```php
$proxy->setOptions('stream', [
    'http' => [
        'user_agent' => 'foo/bar',
    ]
]);
```

An example SSL configuration:

```php
$proxy->setOptions('stream', [
    'ssl' => [
        'verify_peer'   => true,
        'cafile'        => '/foo/bar/baz/cacert.pem',
        'verify_depth'  => 5,
        'CN_match'      => 'secure.example.com'
    ]
]);
```

For more options for use with `$proxy->setOptions('stream', [ ... ]);`, see: https://www.php.net/manual/en/context.php

## How to use

To return the download response directly to the browser, use the `Proxy::response` method:

```php
use Inphinit\Proxy\Proxy;
use Inphinit\Proxy\Drivers\CurlDriver;
use Inphinit\Proxy\Drivers\StreamDriver;

$proxy = new Proxy();

$proxy->setDrivers([
    CurlDriver::class,
    StreamDriver::class
]);

$proxy->setTemporary('php://temp');
$proxy->download($url);

$proxy->response();
```

If you want to use the JSONP format, replace the `Proxy::response` method with `Proxy::jsonp`. In this example, the callback will return and receive the content in DATA URI format:

```php
use Inphinit\Proxy\Proxy;
use Inphinit\Proxy\Drivers\CurlDriver;
use Inphinit\Proxy\Drivers\StreamDriver;

if (empty($_GET['callback'])) {
    die('Missing callback');
}

$proxy = new Proxy();

$proxy->setDrivers([
    CurlDriver::class,
    StreamDriver::class
]);

$proxy->setTemporary('php://temp');
$proxy->download($url);

$proxy->jsonp($_GET['callback']);
```

If you need to handle content or errors manually, you can use the `Proxy::getContents`, `Proxy::getContentType`, `Proxy::getHttpStatus`, `Proxy::getLastErrorCode`, `Proxy::getLastErrorMessage` methods:

```php
use Inphinit\Proxy\Proxy;
use Inphinit\Proxy\Drivers\CurlDriver;
use Inphinit\Proxy\Drivers\StreamDriver;

if (empty($_GET['callback'])) {
    die('Missing callback');
}

$proxy = new Proxy();

$proxy->setDrivers([
    CurlDriver::class,
    StreamDriver::class
]);

$proxy->setTemporary('php://temp');
$proxy->download($url);

$errcode = $proxy->getLastErrorCode();
$httpStatus = $proxy->getHttpStatus();

if ($errcode) {
    echo $code, ': ', $proxy->getLastErrorCode();
} elseif ($httpStatus >= 400) {
    echo 'HTTP request failed:', $httpStatus;
} else {
    // Sucesss
    $type = $proxy->getContentType();
    $contents = $proxy->getContents();

    ...
}
```

In the examples so far, CurlDriver takes priority, and uses `StreamDriver` as a fallback, but you can change this, in a hypothetical example, if you only want to use `StreamDriver`:

```php
$proxy->setDrivers([
    StreamDriver::class
]);
```

You can also limit the URLs that the proxy can access:

```php
$proxy->urls([
    'https://domain1.com/',        // Allow request in all paths from https://domain1.com
    'https://domain2.com/images/', // Allow requests in /images/ from https://domain2.com
    'https://*.mainsite.io/',      // Allow requests in mainsite.com subdomain
    'https://foo.io:8000/',        // Allow requests in other.io with port 8000
    '*://other.io/',               // Allow HTTP or HTTPS requests in other.io
]);

$proxy->setTemporary('php://temp');
$proxy->download($url);
```

## Writing your own driver

The following methods are required to write an `Inphinit\Proxy` compatible driver

Method | Description
--- | ---
`__construct(Proxy $proxy)` | It will receive the Proxy instance
`support(): bool` | It will inform if the server has support
`exec(string $url, int &$httpStatus, string &$contentType, int &$errorCode, string &$errorMessage): void` | It will execute the driver and fill in the references

*Optionally* you can use Inphinit Interface to avoid errors when writing:

```php
use Inphinit\Proxy\Drivers\InterfaceDriver;

class CustomDriver implements InterfaceDriver
{
    public function __construct(Proxy $proxy)
    {
        ...
    }

    public function support()
    {
        ...
    }

    public function exec($url, &$httpStatus, &$contentType, &$errorCode, &$errorMessage)
    {
        ...
    }
}
```

Once created you can use it like this:

```php
$proxy->setDrivers([
    CustomDriver::class
]);
```
