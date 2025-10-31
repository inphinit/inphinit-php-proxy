<p align="center">
<a href="https://packagist.org/packages/inphinit/proxy"><img src="https://img.shields.io/packagist/dt/inphinit/proxy" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/inphinit/proxy"><img src="https://img.shields.io/packagist/v/inphinit/proxy" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/inphinit/proxy"><img src="https://img.shields.io/packagist/l/inphinit/proxy" alt="License"></a>
</p>

## About Inphinit Proxy

Inphinit Proxy is a lightweight PHP proxy library designed to work with html2canvas or other client-side applications that need to bypass CORS restrictions.

Until version 1.x, this project was mainly a proxy solution for _html2canvas_. Starting from version 2.0, it evolved with extensive configuration and customization options, making it suitable for many different use cases.

Although developed as part of the _Inphinit framework_, this library is fully standalone and compatible with any framework — or even plain PHP.

While fully standalone, this library was originally part of the Inphinit framework. If you’re starting a new project, consider adopting the framework itself. For more details: https://inphinit.github.io

## Proxies for other scripting languages

If you're not using PHP but still need a proxy for _html2canvas_, check out the following implementations:

* [html2canvas proxy for ASP.NET (csharp)](https://github.com/brcontainer/html2canvas-csharp-proxy)
* [html2canvas proxy for Classic ASP (vbscript)](https://github.com/brcontainer/html2canvas-asp-vbscript-proxy)
* [html2canvas proxy for Python (Works with any framework)](https://github.com/brcontainer/html2canvas-proxy-python)

## Requirements

1. _PHP 8.4_ is highly recommended (https://www.php.net/supported-versions.php)
    * Minimal: PHP 5.4 compatibility is retained for environments with upgrade constraints
1. cURL PHP extension to use the `CurlDriver`
1. `allow_url_fopen` must be set to `1` on `php.ini` to use `StreamDriver`

## Installing

You can install via composer:

```bash
composer require inphinit/proxy
```

Then include this in your script or controller:

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

try {
    // Execute download
    $proxy->download($_GET['url']);

    // Display raw output
    $proxy->response();
} catch (Exception $ee) {
    $code = $ee->getCode();
    $message = $ee->getMessage();

    echo 'Error: (', $code, ') ', $message;
}
```

If you're not using a framework, you can download the release from https://github.com/inphinit/inphinit-php-proxy/releases, then extract it, move it to your web server, and optionally rename the folder, for example:

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

If you have manually downloaded it to use on your server, you can use the `proxy.php` script, an example:

```javascript
html2canvas(document.getElementById('container'), {
    logging: true,
    proxy: '/proxy/proxy.php'
}).then((canvas) => {
    canvas.toBlob((blob) => { });
});
```

## API Methods

Method | Description
--- | ---
`setMaxDownloadSize(int $value): void` | Set the maximum allowed download size
`getMaxDownloadSize(): int` | Get the maximum allowed download size
`setMaxRedirs(int $value): void` | Set the maximum number of HTTP redirects
`getMaxRedirs(): int` | Get the maximum number of HTTP redirects
`setReferer(string $value): void` | Set the Referer request header
`getReferer(): string` | Get the Referer request header
`setTimeout(int $value): void` | Set the connection timeout in seconds
`getTimeout(): int` | Get the connection timeout in seconds
`setUserAgent(string $value): void` | Set the User-Agent request header
`getUserAgent(): string` | Get the User-Agent request header
`setDrivers(array $drivers): void` | Set the list of driver class names used for downloading resources
`setControlAllowOrigin(string $origin): void` | Set the Access-Control-Allow-Origin header
`setControlAllowHeaders(array $headers): void` | Set the list of allowed headers
`setOptions(string $key, mixed $value): void` | Set generic options
`getOptions([string $key]): mixed` | Get generic options
`getOptionsUpdate(): int` | Returns an internal incremental counter used to determine whether driver options have changed
`setAllowedUrls(array $urls): void` | Set the list of allowed URLs for download
`addAllowedType(string $type, bool $binary): void` | Add a Content-Type to the allowed list, `true` = Base64 encoding, `false` = URL encoding
`removeAllowedType(string $type): void` | Remove a Content-Type from the allowed list
`isAllowedType(string $type, string &$errorMessage): bool` | Check if a given Content-Type is allowed (this method will be used by drivers)
`setTemporary(string $path): void` | Set the temporary storage path or stream for downloaded content (e.g., `/mnt/storage/`, `php://temp`, `php://memory`).
`getTemporary(): resource\|null` | Get the temporary stream resource used for downloaded content
`download(string $url): void` | Perform the download
`setResponseCacheTime(int $seconds): void` | Set the cache duration (in seconds) or disable cache for `Proxy::response()` or `Proxy::jsonp()`
`response(): void` | Dump response to output
`jsonp(string $callback): void` | Output JSONP callback with URL or data URI content
`getContents([int $length[, int $offset]]): string\|null` | If last download was successful, contents will be returned
`getContentType(): string\|null` | If last download was successful, Content-Type will be returned
`getHttpStatus(): int\|null` | If last download was successful, HTTP status will be returned
`reset(): void` | Reset last download

## Generic options

Generic options allow you to customize driver behavior through their native configuration mechanisms (cURL options or stream contexts). Since each driver may require different types of settings, the most flexible approach is to allow these options to store any value. This is particularly useful when developing a new driver. Existing options include:

Usage | Description
--- | ---
`setOptions('curl', array $value)` | Options for `CurlDriver`. See: https://www.php.net/manual/en/curl.constants.php
`setOptions('stream', array $value)` | Options for `StreamDriver`. See: https://www.php.net/manual/en/context.php

To configure the _cURL_ driver, use `'curl'` as the first parameter, for example `$proxy->setOptions('curl', [ ... ]);`, an example to change the SSL version:

```php
$proxy->setOptions('curl', [
    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_3
]);
```

An example to disable SSL verification (for local testing, don't use in a production environment):

```php
$proxy->setOptions('curl', [
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_SSL_VERIFYPEER => false
]);
```

For more constants options to be used with `$proxy->setOptions('curl', [ ... ])`, see: https://www.php.net/manual/en/curl.constants.php

To configure the Stream driver, use `'stream'` as the first parameter in `setOptions()`, an example to set the HTTP protocol version:

```php
$proxy->setOptions('stream', [
    'http' => [
        'protocol_version' => 1.0,
    ]
]);
```

Example SSL configuration:

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

## Content-Type allowed

When executing the `download()` method, a Content-Type validation will be performed, by default the following Content-Types are allowed:

Content-Type | `Proxy::jsonp()`
--- | ---
`image/apng` | base64
`image/png` | base64
`image/avif` | base64
`image/webp` | base64
`image/gif` | base64
`image/jpeg` | base64
`image/svg+xml` | URL-encoded
`image/svg-xml` | URL-encoded

You can define additional allowed Content-Types, for example:

```php
$proxy->addAllowedType('image/x-icon', true);
$proxy->addAllowedType('image/vnd.microsoft.icon', true);
```

The method's second parameter specifies whether `Proxy::jsonp()` should use _URL encoding_ or _Base64_ encoding in the data URI scheme.

To remove an allowed Content-Type use the `Proxy::removeAllowedType()` method, example:

```php
$proxy->removeAllowedType('image/apng');
```

## How to use

To return the download response directly to the browser, use the `Proxy::response()` method:

```php
use Inphinit\Proxy\Proxy;
use Inphinit\Proxy\Drivers\CurlDriver;
use Inphinit\Proxy\Drivers\StreamDriver;

$proxy = new Proxy();

$proxy->setDrivers([
    CurlDriver::class,
    StreamDriver::class
]);

$proxy->download($url);

$proxy->response();
```

If you want to use the JSONP format, replace the `Proxy::response` method with `Proxy::jsonp`. In this example, the callback will return and receive the content in _DATA URI_ format:

```php
use Inphinit\Proxy\Proxy;
use Inphinit\Proxy\Drivers\CurlDriver;
use Inphinit\Proxy\Drivers\StreamDriver;

if (empty($_GET['callback'])) {
    http_response_code(400);
    die('Missing callback');
}

$proxy = new Proxy();

$proxy->setDrivers([
    CurlDriver::class,
    StreamDriver::class
]);

try {
    $proxy->download($url);
    $proxy->jsonp($_GET['callback']);
} catch (Exception $ee) {
}
```

If you need to handle content, you can use the `Proxy::getContents`, `Proxy::getContentType`, `Proxy::getHttpStatus` methods:

```php
use Inphinit\Proxy\Proxy;
use Inphinit\Proxy\Drivers\CurlDriver;
use Inphinit\Proxy\Drivers\StreamDriver;

$proxy = new Proxy();

$proxy->setDrivers([
    CurlDriver::class,
    StreamDriver::class
]);

try {
    $proxy->download($url);

    // Success
    $contents = $proxy->getContents();
    $contentType = $proxy->getContentType();
    $httpStatus = $proxy->getHttpStatus();

    ...

} catch (Exception $ee) {
    $code = $ee->getCode();
    $message = $ee->getMessage();

    echo 'Error: (', $code, ') ', $message;
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
$proxy->setAllowedUrls([
    'https://domain1.com/',        // Allows requests on any path to https://domain1.com
    'https://domain2.com/images/', // Allows requests from the path /images/ on https://domain2.com
    'https://*.mainsite.io/',      // Allows requests on subdomains of mainsite.io
    'https://foo.io:8000/',        // Allows requests to foo.io with port 8000
    '*://other.io/',               // Allows HTTPS and HTTP requests to other.io
]);

$proxy->download($url);
```

## Writing your own driver

The following methods are required to write an `Inphinit\Proxy` compatible driver

Method | Description
--- | ---
`__construct(Proxy $proxy)` | It will receive the `Proxy` instance
`available(): bool` | It will inform if the server has support
`exec(string $url, int &$httpStatus, string &$contentType, int &$errorCode, string &$errorMessage): void` | It will execute the driver and fill in the references

*Optionally* you can use `InterfaceDriver` to avoid errors when writing:

```php
use Inphinit\Proxy\Drivers\InterfaceDriver;
use Inphinit\Proxy\Proxy;

class CustomDriver implements InterfaceDriver
{
    public function __construct(Proxy $proxy)
    {
        ...
    }

    public function available()
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

## Common issues and solutions

When you add an image from another domain to a `<canvas>` element and then try to export it as a new image, a security error occurs (what actually happens is a security lock), which can return the error:

> SecurityError: DOM Exception 18
>
> Error: An attempt was made to break through the security policy of the user agent.

If you're using Google Maps (or Google Maps Static), you might see this error in the console:

> Google Maps API error: MissingKeyMapError

You need to obtain an API Key: https://developers.google.com/maps/documentation/javascript/get-api-key

If you get this error:

> Access to Image at 'file:///...' from origin 'null' has been blocked by CORS policy: Invalid response. Origin 'null' is therefore not allowed access.

This means you are not using an HTTP server, html2canvas does not work with the `file:///` protocol; to resolve this, use Apache, Nginx, or IIS with PHP.

## Debugging with Web Console from DevTools

If you encounter any issues, check your browser's Web Console (Network and Console tabs) to inspect proxy requests and responses, see documentations:

* Firefox: https://firefox-source-docs.mozilla.org/devtools-user/
* Chrome: https://developer.chrome.com/docs/devtools
* Microsoft Edge: https://learn.microsoft.com/en-us/microsoft-edge/devtools/landing/

You can also test the proxy directly by visiting:

`http://[DOMAIN]/[PATH]/proxy?url=http%3A%2F%2Fmaps.googleapis.com%2Fmaps%2Fapi%2Fstaticmap%3Fcenter%3D40.714728%2C-73.998672%26zoom%3D12%26size%3D800x600%26maptype%3Droadmap%26sensor%3Dfalse%261&callback=html2canvas_0`

Replace `[DOMAIN]` with your domain (e.g., `127.0.0.1`) and replace `[PATH]` by your project folder (e.g., `project-1/test`), something like:

`http://localhost/project-1/test/proxy?url=http%3A%2F%2Fmaps.googleapis.com%2Fmaps%2Fapi%2Fstaticmap%3Fcenter%3D40.714728%2C-73.998672%26zoom%3D12%26size%3D800x600%26maptype%3Droadmap%26sensor%3Dfalse%261&callback=html2canvas_0`
