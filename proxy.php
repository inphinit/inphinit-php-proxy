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

// Set allowed URLs
/*
$proxy->setAllowedUrls([
    'https://*.domain.io/',
    'https://cdn.foobar.io/'
]);
*/

// Set allowed content-types
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

// Set max timeout connection
// $proxy->setOptions('timeout', 10);

// Set max redirections from location: header
// $proxy->setOptions('max_redirs', 3);

// Set current user-agent
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $proxy->setOptions('user_agent', $_SERVER['HTTP_USER_AGENT']);
}

// Set current referer
if (isset($_SERVER['HTTP_REFERER'])) {
    $proxy->setOptions('referer', $_SERVER['HTTP_REFERER']);
}

// Set drivers used for download
$proxy->setDrivers([
    CurlDriver::class,
    StreamDriver::class
]);

// Set temporary location
$proxy->setTemporary('php://temp');

// Use specific directory
// $proxy->setTemporary(__DIR__ . '/cache');

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
