<?php
/**
 * Inphinit Proxy
 *
 * Copyright (c) 2025 Guilherme Nascimento
 *
 * Released under the MIT license
 */

namespace Inphinit\Proxy\Drivers;

use Inphinit\Proxy\Proxy;

interface InterfaceDriver
{
    /**
     * Constructor
     *
     * @param \Inphinit\Proxy\Proxy $proxy Instance of the Proxy using this driver
     * @return void
     */
    public function __construct(Proxy $proxy);

    /**
     * Check if the driver is available
     *
     * @return bool True if the driver can be used, false otherwise
     */
    public function available();

    /**
     * Execute download
     *
     * @param string $url          URL of the resource to download
     * @param int    $httpStatus   HTTP status code of the response
     * @param string $contentType  Content-Type of the response
     * @param int    $errorCode    Error code if any error occurred
     * @param string $errorMessage Error message if any error occurre
     * @return bool                True on success, false on failure
     */
    public function exec($url, &$httpStatus, &$contentType, &$errorCode, &$errorMessage);
}
