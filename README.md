## PHP Proxy html2canvas

This script allows you to use `html2canvas.js` with different servers, ports and protocols (http, https),
preventing to occur "tainted" when exporting the `<canvas>` for image.

Atualmente o projeto se tornou parte do framework Inphinit, mas ele continua totalmente independente (stand alone), permitindo usar com qualquer framework ou diretamente. Se tiver interesse em experimentar o framework Inphinit acesse:

* https://inphinit.github.io/

## Proxies for other scripting languages

You do not use PHP, but need html2canvas working with proxy, see other proxies:

* [html2canvas proxy in asp.net (csharp)](https://github.com/brcontainer/html2canvas-csharp-proxy)
* [html2canvas proxy in asp classic (vbscript)](https://github.com/brcontainer/html2canvas-asp-vbscript-proxy)
* [html2canvas proxy in python (work any framework)](https://github.com/brcontainer/html2canvas-proxy-python)

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

## Follow

I ask you to follow me or "star" my repository to track updates

## Run script in Cross-domain (data URI scheme)

(See details: https://github.com/inphinit/html2canvas-php-proxy/issues/9)

> Note: Enable cross-domain in proxy server can consume more memory, but can be faster in execution it performs only one request at the proxy server.

> Note: If the file html2canvasproxy.php is in the same domain that your project, you do not need to enable this option.

> Note: Disable the "cross-domain" does not mean you will not be able to capture images from different servers, in other words, the "cross-domain" here refers to `html2canvas.js` (not necessarily the javascript file, but the place where runs) and the "html2canvas.php" are in different domains, the "cross-domain" here refers domain.

In some cases you may want to use this [html2canvasproxy.php](https://github.com/inphinit/html2canvas-php-proxy/blob/master/html2canvasproxy.php) on a specific server, but the `html2canvas.js` and another server, this would cause problems in your project with the security causing failures in execution. In order to use security just set in the [html2canvasproxy.php](https://github.com/inphinit/html2canvas-php-proxy/blob/master/html2canvasproxy.php):

Enable data uri scheme for use proxy for all servers:

```
define('INPHINIT_PROXY_DATAURI', true);
```

Disable data uri scheme:

```
define('INPHINIT_PROXY_DATAURI', false);
```

## Setup proxy

Definition | Description
--- | ---
`INPHINIT_PROXY_DIR` | Set folder where the images are stored
`INPHINIT_PROXY_DIR_CLEANUP` | Set timeout to clear INPHINIT_PROXY_DIR folder contents
`INPHINIT_PROXY_DIR_PERMISSION` | Set folder permission - use 0644 or 0666 to prevent sploits
`INPHINIT_PROXY_HTTP_CACHE` | Set response timeout in seconds or `0`/`false`/`null`/`-1` to disable
`INPHINIT_PROXY_TIMEOUT` | Set download timeout in seconds
`INPHINIT_PROXY_MAX_REDIRS` | Set max HTTP redirects (`location:` header)
`INPHINIT_PROXY_DATAURI` | Set to true to use "Data URI scheme", otherwise return file path
`INPHINIT_PROXY_PREFER_CURL` | Enable curl (if avaliable)
`INPHINIT_PROXY_ALLOWED_DOMAINS` | Set `*` to allow any domain, `*.site.com` for subdomains, and for specific domains use something like `site.com,www.site.com`
`INPHINIT_PROXY_ALLOWED_PORTS` | Set allowed ports, separated by commas
`INPHINIT_PROXY_CALLBACK` | Set alternative callback function
`INPHINIT_PROXY_SSL_VERIFY` | Set `false` for disable SSL check, `true` for enable SSL check (require config `curl.cainfo=/path/to/cacert.pem` in php.ini) or define path if need config CAINFO manually like this `define('INPHINIT_PROXY_SSL_VERIFY', '/path/to/cacert.pem')`
`INPHINIT_PROXY_SSL_VERSION` | Set 0 for default version, for others version see constants in: https://www.php.net/manual/en/curl.constants.php#constant.curl-sslversion-default

## Usage

> Note: Requires PHP5+

* [Google maps](https://github.com/inphinit/html2canvas-php-proxy/blob/master/examples/google-maps.html)
* [Test case](https://github.com/inphinit/html2canvas-php-proxy/blob/master/examples/usable-example.html)

```html
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title>html2canvas php proxy</title>
        <script src="html2canvas.js"></script>
        <script>
        window.onload = function () {
            html2canvas(document.getElementById('container'), {
                logging: true, //Enable log (use Web Console for get Errors and Warnings)
                proxy: '../html2canvasproxy.php'
            }).then((canvas) => {
                canvas.toBlob((blob) => {
                    const img = new Image;
                    const url = URL.createObjectURL(blob);

                    img.src = url;

                    document.getElementById('output').appendChild(img);
                });
            });
        };
        </script>
    </head>
    <body>
        <p>
            <img alt="google maps static" src="https://maps.googleapis.com/maps/api/staticmap?center=40.714728,-73.998672&amp;zoom=12&amp;size=600x300&amp;maptype=roadmap&amp;key=[YOUR_API_KEY]&amp;signature=[YOUR_SIGNATURE]">
        </p>
    </body>
</html>
```

## Debuging with Web Console from DevTools

If you have any issue is recommend to analyze the log with the Web Console tab and requests with Network tab from your browser, see documentations:

* Firefox: https://firefox-source-docs.mozilla.org/devtools-user/
* Chrome: https://developer.chrome.com/docs/devtools
* Microsoft Edge: https://learn.microsoft.com/pt-br/microsoft-edge/devtools-guide-chromium/landing/

An alternative is to debug issues by accessing the link directly:

`http://[DOMAIN]/[PATH]/html2canvasproxy.php?url=http%3A%2F%2Fmaps.googleapis.com%2Fmaps%2Fapi%2Fstaticmap%3Fcenter%3D40.714728%2C-73.998672%26zoom%3D12%26size%3D800x600%26maptype%3Droadmap%26sensor%3Dfalse%261&callback=html2canvas_0`

Replace `[DOMAIN]` by your domain (eg. 127.0.0.1) and replace `[PATH]` by your project folder (eg.: `project-1/test`), something like:

`http://localhost/project-1/test/html2canvasproxy.php?url=http%3A%2F%2Fmaps.googleapis.com%2Fmaps%2Fapi%2Fstaticmap%3Fcenter%3D40.714728%2C-73.998672%26zoom%3D12%26size%3D800x600%26maptype%3Droadmap%26sensor%3Dfalse%261&callback=html2canvas_0`

## Changelog

Changelog moved to https://github.com/inphinit/html2canvas-php-proxy/blob/master/CHANGELOG.md

## Next versions

The ideas here are not ready or are not public in the main script, are only suggestions. You can offer suggestions on [issues](https://github.com/inphinit/html2canvas-php-proxy/issues/new).
