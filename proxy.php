<?php
use Inphinit\Proxy\Proxy;
use Inphinit\Proxy\Drivers\CurlDriver;
use Inphinit\Proxy\Drivers\StreamDriver;

if (empty($_GET['url'])) {
    echo 'URL not defined';
    exit;
}

$url = $_GET['url'];

//Usage without autoload
require 'src/Proxy.php';
require 'src/Drivers/CurlDriver.php';
require 'src/Drivers/StreamDriver.php';

$proxy = new Proxy();

// Set drivers used for download
$proxy->setDrivers([
    //CurlDriver::class,
    StreamDriver::class
]);

/*
// PHP 5.4 sintax
$proxy->setDrivers([
    'Inphinit\Proxy\Drivers\CurlDriver',
    'Inphinit\Proxy\Drivers\StreamDriver',
]);
*/

// Set max download size
// $proxy->setMaxDownloadSize(5242880);

// Set max redirections
// $proxy->setMaxRedirs(3);

// Set current referer
if (isset($_SERVER['HTTP_REFERER'])) {
    $proxy->setReferer($_SERVER['HTTP_REFERER']);
}

// Set max timeout connection
// $proxy->setTimeout(10);

// Set current user-agent
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $proxy->setUserAgent($_SERVER['HTTP_USER_AGENT']);
}

// Use specific directory
// $proxy->setTemporary(__DIR__ . '/cache');

// Set allowed URLs
/*
$proxy->setAllowedUrls([
    'https://*.domain.io/',
    'https://cdn.foobar.io/'
]);
*/

// Set allowed Content-Types
// $proxy->addAllowedType('image/ico', true);

// Extra configs for CurlDriver
// $proxy->setOptions('curl', [ CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_3 ]);

// Extra configs for StreamDriver, see details in: https://www.php.net/manual/en/context.php
/*
$proxy->setOptions('stream', [
    'http' => [
        'method' => 'POST'
        'user_agent' => 'foo/bar',
    ],
    'ssl'  => [
        ...
    ]
]);
*/

try {
    // Execute download
    $proxy->download($url);

    if (empty($_GET['callback'])) {
        // Display raw output
        $proxy->response();
    } else {
        // Display callback with data URI content
        $proxy->jsonp($_GET['callback']);
    }
} catch (Exception $ee) {
    $code = $ee->getCode();
    $message = $ee->getMessage();
    echo "Error: ({$code}) {$message}";
}
