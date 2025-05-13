<?php
/**
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento
 *
 * Released under the MIT license
 */

namespace Inphinit\Proxy\Drivers;

use Inphinit\Proxy\Proxy;

class StreamDriver
{
    private $proxy;
    private $lastUpdate = 0;
    private $timeout = 30;
    private $context;

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
        $update = $this->proxy->getOptions('update');

        if ($this->context === null || $this->lastUpdate < $update) {
            $this->lastUpdate = $update;

            $timeout = $this->proxy->getOptions('timeout');

            $options = array(
                'http' => array(
                    'follow_location' => true,
                    'max_redirects' => $this->proxy->getOptions('max_redirs'),
                    'timeout' => $timeout
                )
            );

            $this->timeout = $timeout;

            $extra = $this->proxy->getOptions('stream');

            if ($extra) {
                $options += $extra;
            }

            $referer = $this->proxy->getOptions('referer');

            if ($referer) {
                $options['http']['referer'] = $referer;
            }

            $userAgent = $this->proxy->getOptions('user_agent');

            if ($userAgent) {
                $options['http']['user_agent'] = $userAgent;
            }

            if (empty($options['http']['method'])) {
                $options['http']['method'] = 'GET';
            }

            $this->context = stream_context_create($options);
        }

        $handle = fopen($url, 'rb', false, $this->context);

        if ($handle) {
            $temp = $this->proxy->getTemporary();
            $timeout = $this->timeout;
            $timedOut = false;
            $start = microtime(true);

            $meta_data = stream_get_meta_data($handle);

            foreach ($meta_data['wrapper_data'] as $index => $header) {
                if ($index === 0) {
                    if (preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $header, $match)) {
                        $httpStatus = (int) $match;
                    } else {
                        $httpStatus = 0;
                        $errorCode = 0;
                        $errorMessage = 'Invalid response';
                        break;
                    }
                } elseif (stripos($header, 'content-type:') === 0) {
                    $contentType = substr($header, 13);
                }
            }

            if ($httpStatus !== 0 && $httpStatus < 300) {
                while (feof($handle) === false) {
                    if ($timeout < (microtime(true) - $start)) {
                        $timedOut = true;
                        $errorCode = 0;
                        $errorMessage = 'Connection timed out';
                        break;
                    }

                    $data = fgets($handle, 4096);

                    fwrite($temp, $data);
                }
            }

            fclose($handle);

            if ($timedOut) {
                $this->proxy->resetTemporary();
            }
        } else {
            $err = error_get_last();
            $errorCode = $err['type'];
            $errorMessage = $err['message'];
        }
    }
}
