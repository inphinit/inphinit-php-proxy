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
    private $proxy;
    private $lastUpdate = 0;
    private $timeout = 30;
    private $handle;

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
            $timeout = $this->proxy->getOptions('timeout');

            $options = array(
                CURLOPT_CONNECTTIMEOUT => $timeout ? $timeout : $this->timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HEADER => false,
                CURLOPT_MAXREDIRS => $this->proxy->getOptions('max_redirs'),
                CURLOPT_RETURNTRANSFER => false
            );

            $extra = $this->proxy->getOptions('curl');

            if ($extra) {
                $options += $extra;
            }

            curl_setopt_array($ch, $options);

            $referer = $this->proxy->getOptions('referer');

            if ($referer) {
                curl_setopt($ch, CURLOPT_REFERER, $referer);
            }

            $userAgent = $this->proxy->getOptions('user_agent');

            if ($userAgent) {
                curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
            }

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
            $errorMessage = 'cURL: ' . curl_error($ch);
        } else {
            $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        }
    }
}
