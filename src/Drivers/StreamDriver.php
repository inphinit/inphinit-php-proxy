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
     * Check driver support
     *
     * @return bool
     */
    public function support()
    {
        // No checks required
        return true;
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

            $options = array(
                'http' => array(
                    'method' => 'GET',
                    'max_redirects' => $this->proxy->getOptions('maxRedirs')
                )
            );

            $extra = $this->proxy->getOptions('stream');

            if ($extra) {
                $options += $extra;
            }

            $timeout = $this->proxy->getOptions('timeout');

            $options['http']['timeout'] = $timeout;

            $this->timeout = $timeout;
            $this->context = stream_context_create($options);
        }

        $handle = fopen($url, 'r', false, $this->context);

        if ($handle) {
            $temp = $this->proxy->getTemporary();
            $timeout = $this->timeout;
            $timedOut = false;
            $start = microtime(true);

            $first = true;
            $body = false;

            while (feof($handle) === false) {
                if ($timeout < (microtime(true) - $start)) {
                    $timedOut = true;
                    $errorCode = 0;
                    $errorMessage = 'Connection timed out';
                    break;
                }

                $content = fgets($handle, 4096);

                if ($first) {
                    $first = false;

                    if (preg_match('#^HTTP/1\.\d\s+(\d{3})\s+#', $content, $match)) {
                        $httpStatus = $match[1];
                    } else {
                        break;
                    }
                } elseif ($body) {
                    fwrite($temp, $content);
                } elseif (trim($content) === '') {
                    $body = true;
                } elseif (stripos($content, 'content-type:') === 0) {
                    $contentType = trim($content);
                }
            }

            fclose($handle);

            if ($timedOut) {
                $this->proxy->resetTemporary();
            }
        }
    }
}
