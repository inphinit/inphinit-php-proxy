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

class CurlDriver
{
    private $errorMessage;
    private $handle;
    private $httpStatus;
    private $lastUpdate = 0;
    private $maxDownloadSize;
    private $proxy;

    /**
     * Create instace
     *
     * @param \Inphinit\Proxy\Proxy $proxy
     * @return void
     */
    public function __construct(Proxy $proxy)
    {
        $this->proxy = $proxy;
    }

    /**
     * Check if the driver is available
     *
     * @return bool
     */
    public function available()
    {
        return function_exists('curl_init');
    }

    /**
     * Execute download
     *
     * @param string $url
     * @param int    $httpStatus
     * @param string $contentType
     * @param int    $errorCode
     * @param string $errorMessage
     * @return bool
     */
    public function exec($url, &$httpStatus, &$contentType, &$errorCode, &$errorMessage)
    {
        $update = $this->proxy->getOptions('update');

        if ($this->handle === null || $this->lastUpdate < $update) {
            $this->lastUpdate = $update;

            $this->handle = curl_init();

            $ch = $this->handle;
            $timeout = $this->proxy->getTimeout();

            $options = array(
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HEADER => false,
                CURLOPT_MAXREDIRS => $this->proxy->getMaxRedirs(),
                CURLOPT_RETURNTRANSFER => false
            );

            $extra = $this->proxy->getOptions('curl');

            if ($extra) {
                $options += $extra;
            }

            curl_setopt_array($ch, $options);

            $referer = $this->proxy->getReferer();

            if ($referer) {
                curl_setopt($ch, CURLOPT_REFERER, $referer);
            }

            $userAgent = $this->proxy->getUserAgent();

            if ($userAgent) {
                curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
            }

            curl_setopt($ch, CURLOPT_NOPROGRESS, false);

            $this->maxDownloadSize = $this->proxy->getMaxDownloadSize();

            if (PHP_VERSION_ID < 50500) {
                $progressCallback = function ($download_size, $downloaded, $upload_size, $uploaded) {
                    return $this->abort($downloaded);
                };
            } else {
                $progressCallback = function ($resource, $download_size, $downloaded, $upload_size, $uploaded) {
                    return $this->abort($downloaded);
                };
            }

            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, $progressCallback);

            $temp = $this->proxy->getTemporary();

            if (defined('CURLOPT_WRITEFUNCTION')) {
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$temp) {
                    return fwrite($temp, $data);
                });
            } else {
                curl_setopt($ch, CURLOPT_FILE, $temp);
            }
        } else {
            $ch = $this->handle;
        }

        curl_setopt($ch, CURLOPT_URL, $url);

        curl_exec($ch);

        $code = curl_errno($ch);

        if ($code !== 0) {
            $errorCode = $code;
            $errorMessage = $this->errorMessage ? $this->errorMessage : ('cURL: ' . curl_error($ch));

            if ($this->httpStatus !== null) {
                $httpStatus = $this->httpStatus;
            }

            return false;
        }

        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        return true;
    }

    private function abort($downloaded)
    {
        if ($downloaded > $this->maxDownloadSize) {
            return 1;
        }

        $code = curl_getinfo($this->handle, CURLINFO_HTTP_CODE);

        if ($code >= 100 && ($code < 200 || $code >= 300)) {
            $this->httpStatus = $code;
            return 1;
        }

        $contentType = curl_getinfo($this->handle, CURLINFO_CONTENT_TYPE);

        if ($contentType && $this->proxy->isAllowedType($contentType, $this->errorMessage) === false) {
            return 1;
        }

        return 0;
    }
}
