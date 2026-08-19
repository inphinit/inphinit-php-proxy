<?php
/**
 * Inphinit Proxy
 *
 * Copyright (c) 2026 Guilherme Nascimento
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
        if ($this->proxy->pollOptions()) {
            $this->close();

            $this->handle = curl_init();

            $ch = $this->handle;
            $timeout = $this->proxy->getTimeout();

            $options = [
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HEADER => false,
                CURLOPT_MAXREDIRS => $this->proxy->getMaxRedirs(),
                CURLOPT_RETURNTRANSFER => false
            ];

            $extra = $this->proxy->getOptions(get_class($this));

            if ($extra) {
                $options += $extra;
            }

            curl_setopt_array($ch, $options);

            $referer = $this->proxy->getReferer();

            if ($referer) {
                curl_setopt($ch, CURLOPT_REFERER, $referer);
            }

            $user_agent = $this->proxy->getUserAgent();

            if ($user_agent) {
                curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
            }

            curl_setopt($ch, CURLOPT_NOPROGRESS, false);

            $this->maxDownloadSize = $this->proxy->getMaxDownloadSize();

            if (PHP_VERSION_ID < 50500) {
                $progress_callback = function ($downloadSize, $downloaded, $uploadSize, $uploaded) {
                    return $this->abort($downloaded);
                };
            } else {
                $progress_callback = function ($resource, $downloadSize, $downloaded, $uploadSize, $uploaded) {
                    return $this->abort($downloaded);
                };
            }

            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, $progress_callback);

            $temp = $this->proxy->getTemporary();

            if (defined('CURLOPT_WRITEFUNCTION')) {
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($resource, $data) use ($temp) {
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

        $errorCode = curl_errno($ch);

        if ($errorCode !== 0) {
            $errorMessage = $this->errorMessage ? $this->errorMessage : curl_error($ch);

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
            $this->errorMessage = 'Download aborted because file size exceeded the maximum allowed';
            return 1;
        }

        $http_code = curl_getinfo($this->handle, CURLINFO_HTTP_CODE);

        if (($http_code !== 0 && $http_code < 200) || $http_code >= 400) {
            $this->httpStatus = $http_code;
            return 1;
        }

        $content_type = curl_getinfo($this->handle, CURLINFO_CONTENT_TYPE);

        if ($http_code < 300 && $content_type) {
            return $this->proxy->isAllowedType($content_type, $this->errorMessage) ? 0 : 1;
        }

        return 0;
    }

    private function close()
    {
        if ($this->handle && PHP_VERSION_ID < 80500) {
            curl_close($this->handle);
        }

        $this->handle = null;
    }

    public function __destruct()
    {
        $this->close();
    }
}
