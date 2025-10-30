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

class StreamDriver
{
    private $context;
    private $proxy;
    private $timeout = 30;
    private $update = 0;

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
        // For convenience, if ini_get is disabled, the function will return true
        return function_exists('ini_get') === false || ini_get('allow_url_fopen') == 1;
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
        $contentType = '';
        $errorCode = 0;
        $errorMessage = null;
        $httpStatus = null;
        $update = $this->proxy->getOptionsUpdate();

        if ($this->context === null || $this->update !== $update) {
            $this->update = $update;

            $this->timeout = $this->proxy->getTimeout();

            $options = array(
                'http' => array(
                    'follow_location' => true,
                    'ignore_errors' => true,
                    'max_redirects' => $this->proxy->getMaxRedirs(),
                    'timeout' => $this->timeout
                )
            );

            $referer = $this->proxy->getReferer();

            if ($referer) {
                $options['http']['referer'] = $referer;
            }

            $userAgent = $this->proxy->getUserAgent();

            if ($userAgent) {
                $options['http']['user_agent'] = $userAgent;
            }

            if (empty($options['http']['method'])) {
                $options['http']['method'] = 'GET';
            }

            $extra = $this->proxy->getOptions('stream');

            // Adds other contexts like Socket, SSL and notification.
            if ($extra) {
                $options += $extra;
            }

            // Adds extra settings to the HTTP context without changing important settings.
            if (isset($extra['http'])) {
                $options['http'] += $extra['http'];
            }

            $this->context = stream_context_create($options);
        }

        $start = microtime(true);
        $handle = fopen($url, 'rb', false, $this->context);

        if ($handle === false) {
            $err = error_get_last();
            $errorCode = $err['type'];
            $errorMessage = $err['message'];
            return false;
        }

        $meta_data = stream_get_meta_data($handle);

        foreach ($meta_data['wrapper_data'] as $index => $header) {
            if ($index === 0) {
                if (preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $header, $match)) {
                    $httpStatus = (int) $match[1];
                } else {
                    $errorMessage = 'Invalid response';
                    break;
                }
            } elseif (stripos($header, 'content-type:') === 0) {
                $contentType = substr($header, 13);
            }
        }

        if ($httpStatus !== null && ($httpStatus < 200 || $httpStatus >= 300)) {
            $errorMessage = '';
        } elseif ($errorMessage === null && $this->proxy->isAllowedType($contentType, $errorMessage)) {
            $downloaded = 0;
            $maxSize = $this->proxy->getMaxDownloadSize();
            $temp = $this->proxy->getTemporary();
            $timeout = $this->timeout;

            while (feof($handle) === false) {
                if ($timeout < (microtime(true) - $start)) {
                    $errorMessage = 'Connection timed out';
                    break;
                }

                $data = fread($handle, 131072);

                $downloaded += strlen($data);

                if ($downloaded > $maxSize) {
                    $errorMessage = 'Download aborted because file size exceeded the maximum allowed';
                    break;
                }

                fwrite($temp, $data);
            }
        }

        fclose($handle);

        return $errorMessage === null;
    }
}
