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
    private $lastUpdate = 0;
    private $proxy;
    private $timeout = 30;

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
        $update = $this->proxy->getOptionsUpdate();

        if ($this->context === null || $this->lastUpdate !== $update) {
            $this->lastUpdate = $update;

            $this->timeout = $this->proxy->getTimeout();

            $options = [
                'http' => [
                    'follow_location' => true,
                    'ignore_errors' => true,
                    'max_redirects' => $this->proxy->getMaxRedirs(),
                    'timeout' => $this->timeout
                ]
            ];

            $referer = $this->proxy->getReferer();

            if ($referer) {
                $options['http']['referer'] = $referer;
            }

            $user_agent = $this->proxy->getUserAgent();

            if ($user_agent) {
                $options['http']['user_agent'] = $user_agent;
            }

            if (empty($options['http']['method'])) {
                $options['http']['method'] = 'GET';
            }

            $extra = $this->proxy->getOptions(get_class($this));

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

        if (empty($meta_data['wrapper_data'])) {
            $errorMessage = 'Missing `wrapper_data` info';
            fclose($handle);
            return false;
        }

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
            $max_size = $this->proxy->getMaxDownloadSize();
            $temp = $this->proxy->getTemporary();
            $timeout = $this->timeout;

            while (feof($handle) === false) {
                if ((microtime(true) - $start) > $timeout) {
                    $errorMessage = 'Connection timed out';
                    break;
                }

                $data = fread($handle, 131072);

                $downloaded += strlen($data);

                if ($downloaded > $max_size) {
                    $errorMessage = 'Download aborted because file size exceeded the maximum allowed';
                    break;
                }

                if (fwrite($temp, $data) === false) {
                    $errorMessage = 'Failed to write to temporary storage';
                    break;
                }
            }
        }

        fclose($handle);

        return $errorMessage === null;
    }
}
