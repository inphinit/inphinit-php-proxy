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

            if ($httpStatus !== 0) {
                while (feof($handle) === false) {
                    if ($timeout < (microtime(true) - $start)) {
                        $timedOut = true;
                        $errorCode = 0;
                        $errorMessage = 'Connection timed out';
                        break;
                    }

                    $content = fgets($handle, 4096);
                    fwrite($temp, $content);
                }
            }

            fclose($handle);

            if ($timedOut) {
                $this->proxy->resetTemporary();
            }
        }
    }
}
