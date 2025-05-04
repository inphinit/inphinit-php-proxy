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

// Enable debug mode
// $proxy->setDebug(true);

// Set allowed URLs
// $proxy->urls([ 'https://*.domain.io/', 'https://cdn.foobar.io/' ]);

// Set allowed content-types
// $proxy->type([ 'image/jpeg', 'image/png' ]);

// Extra configs for CURL
// $proxy->setOptions('curl', [ CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_3 ]);

// Extra configs for stream, see details in: https://www.php.net/manual/en/context.php
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

// Set max timeout connection
// $proxy->setOptions('timeout', 10);

// Set max redirections from location: header
// $proxy->setOptions('maxRedirs', 3);

// Set drivers used for download
$proxy->setDrivers([
    CurlDriver::class,
    StreamDriver::class
]);

// Set temporary location
$proxy->setTemporary('php://temp');

// Use specific directory
// $proxy->setTemporary(__DIR__ . '/cache');

// Execute download
$proxy->download($url);

if (empty($_GET['callback'])) {
    // Display raw output
    $proxy->response();
} else {
    // Display callback with data URI content
    $proxy->jsonp($_GET['callback']);
}
