<?php
/**
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento
 *
 * Released under the MIT license
 */

namespace Inphinit\Proxy;

class Proxy
{
    const DEFAULT_CONTENT_TYPE = 'application/octet-stream';
    const DEFAULT_MAX_REDIRS = 5;
    const DEFAULT_TIMEOUT = 30;

    private $public;
    private $temporary;
    private $core = false;
    private $options = [
        'update' => 0
    ];
    private $driver;
    private $drivers = [];
    private $allowedUrls = ['*'];
    private $allowedTypes = [
        'image/apng',
        'image/png',
        'image/avif',
        'image/webp',
        'image/jpeg',
        'image/gif',
        'image/svg+xml',
        'image/svg-xml' // Support for old web servers (an old bug)
    ];

    private $contentType;
    private $httpStatus;
    private $errorCode;
    private $errorMessage;

    private $publicStorage;
    private $publicUrl;
    private $httpCacheTime = 60;

    public function __construct()
    {
        $this->core = class_exists('Inphinit\App');
        $this->options['maxRedirs'] = self::DEFAULT_MAX_REDIRS;
        $this->options['timeout'] = self::DEFAULT_TIMEOUT;
    }

    /**
     * Setup generic options
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setOptions($key, $value)
    {
        if ($key === 'maxRedirs' && $value < 1) {
            $value = self::DEFAULT_MAX_REDIRS;
        }

        if ($key === 'timeout' && $value < 1) {
            $value = self::DEFAULT_TIMEOUT;
        }

        $this->options[$key] = $value;
        $this->options['update'] += 1;
    }

    /**
     * Get generic options
     *
     * @param string|null $key
     * @return mixed
     */
    public function getOptions($key = null)
    {
        return isset($this->options[$key]) ? $this->options[$key] : null;
    }

    /**
     * Set drivers used for download resource
     *
     * @param array $drivers Set drivers
     * @return void
     */
    public function setDrivers(array $drivers)
    {
        $this->drivers = $drivers;
    }

    /**
     * Get or redefine allowed urls
     *
     * @param array $urls Optional. Redefine allowed urls
     * @return array      Return current allowed urls
     */
    public function urls(array $urls = [])
    {
        if (empty($urls)) {
            return $this->allowedUrls;
        }

        $current = $this->allowedUrls;
        $this->allowedUrls = $urls;
        return $current;
    }

    /**
     * Get or redefine allowed content-types
     *
     * @param array $types Optional. Redefine allowed content-types
     * @return array       Return current allowed content-types
     */
    public function types(array $types = [])
    {
        if (empty($types)) {
            return $this->allowedTypes;
        }

        $current = $this->allowedTypes;
        $this->allowedTypes = $types;
        return $current;
    }

    /**
     * Set temporary handle path, eg.: /mnt/storage/, php://temp, php://memory
     *
     * @param string $path Set path
     * @return void
     */
    public function setTemporary($path)
    {
        if ($this->temporary) {
            $this->resetTemporary();
            fclose($this->temporary);
        }

        if (strpos($path, 'php://') !== 0) {
            $path = tempnam($path, '~' . mt_rand(0, 99));
        }

        $this->temporary = fopen($path, 'r+');
    }

    /**
     * Get temporary stream
     *
     * @return string
     */
    public function getTemporary()
    {
        return $this->temporary;
    }

    /**
     * Set public storage and public URL for use with JSONP
     *
     * @param string $storage Set public dir storage
     * @param string $url     Set URL public path, eg.: `https://foo.io/public/{file}`
     * @return void
     */
    public function setPublic($storage, $url)
    {
        $this->publicStorage = $storage;
        $this->publicUrl = $url;
    }

    /**
     * Perform download
     *
     * @param string $url Define url to download
     * @return void
     */
    public function download($url)
    {
        if ($this->temporary === null) {
            $this->raise('Temporary not defined, you need set Proxy::setTemporary()');
        }

        if ($this->validateUrl($url) === false) {
            $this->raise('URL not allowed: ' . $url);
        }

        $this->resetTemporary();

        if ($this->driver === null) {
            $selected = null;

            foreach ($this->drivers as $driver) {
                $selected = new $driver($this);

                if ($selected->support()) {
                    break;
                } else {
                    $selected = null;
                }
            }

            if ($selected) {
                $this->driver = $selected;
            } else {
                $this->raise('The selected drivers are not supported');
            }
        }

        $this->driver->exec($url, $this->httpStatus, $this->contentType, $this->errorCode, $this->errorMessage);

        if ($this->errorCode === null && !$this->contentType) {
            $this->contentType = self::DEFAULT_CONTENT_TYPE;
        }
    }

    /**
     * Enable or disable cache for Proxy::respose() or Proxy::jsonp()
     *
     * @param int $seconds Set seconds
     * @return void
     */
    public function setHttpCacheTime($seconds)
    {
        $this->httpCacheTime = $seconds;
    }

    /**
     * Dump response to output
     *
     * @return void
     */
    public function response()
    {
        if ($this->temporary === null) {
            $this->raise('Temporary not defined, you need set Proxy::setTemporary()');
        }

        if ($this->errorCode) {
            $this->raise($this->errorMessage, $this->errorCode);
        }

        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Request-Method: *');
        header('Access-Control-Allow-Methods: OPTIONS, GET');
        header('Access-Control-Allow-Headers: *');
        header('Content-type: ' . $this->contentType);

        $this->httpCache();

        $handle = $this->temporary;

        rewind($handle);

        while (feof($handle) === false) {
            echo fgets($handle, 4096);
        }
    }

    /**
     * Output JSONP callback with URL or data URI content
     *
     * @param string $callback Set callback
     * @param string $public   Set public URL
     * @return void
     */
    public function jsonp($callback, $public = false)
    {
        if ($this->temporary === null) {
            $this->raise('Temporary not defined, you need set Proxy::setTemporary()');
        }

        if ($this->errorCode) {
            $this->raise($this->errorMessage, $this->errorCode);
        }

        header('Content-type: application/javascript');

        $this->httpCache();

        if ($public) {
            $url = $this->temporaryToPublic();
            echo $callback, '(', $url, ');';
        } else {
            $crlf = "\r\n";

            echo $callback, '("';
            echo 'data:' . $this->contentType . ';base64,';

            $handle = $this->temporary;

            rewind($handle);

            while (feof($handle) === false) {
                $raw = fread($handle, 8151);
                echo base64_encode($raw);
            }

            echo '");';
        }
    }

    /**
     * If last download was successful, contents will be returned
     *
     * @param int $length Optional. The maximum bytes to read.
     * @param int $offset Optional. Seek to the specified offset before reading.
     * @return string|null
     */
    public function getContents($length = -1, $offset = -1)
    {
        if ($this->temporary) {
            return stream_get_contents($this->temporary, $length, $offset);
        }
    }

    /**
     * If last download was successful, content-type will be returned
     *
     * @return string|null
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * If last download was successful, HTTP status will be returned
     *
     * @return int|null
     */
    public function getHttpStatus()
    {
        return $this->httpStatus;
    }

    /**
     * If last download was failed, error code will be returned
     *
     * @return int|null
     */
    public function getLastErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * If last download was failed, error message will be returned
     *
     * @return string|null
     */
    public function getLastErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * Reset temporary contents
     *
     * @return void
     */
    public function resetTemporary()
    {
        if ($this->temporary) {
            ftruncate($this->temporary, 0);
            rewind($this->temporary);
        }
    }

    private function httpCache()
    {
        $seconds = $this->httpCacheTime;

        if ($seconds > 0) {
            $datetime = gmdate('D, d M Y H:i:s');

            header('Access-Control-Max-Age:' . $seconds);
            header('Cache-Control: max-age=' . $seconds);
            header('Last-Modified: ' . $datetime . ' GMT');
            header('Pragma: max-age=' . $seconds);
        } else {
            $datetime = gmdate('D, d M Y H:i:s', time() + $seconds);

            header('Cache-Control: no-cache');
            header('Pragma: no-cache');
        }

        header('Expires: ' . $datetime .' GMT');
    }

    private function validateUrl($url)
    {
        $alloweds = $this->allowedUrls;

        if (in_array('*', $alloweds) === false) {
            $re = implode('|', $alloweds);
            $re = preg_quote($re, '#');
            $re = strtr($re, array(
                '\\*' => '\\w+',
                '\\|' => '|'
            ));

            if (!preg_match('#^(' . $re . ')#', $url)) {
                return false;
            }
        }

        return true;
    }

    private function temporaryToPublic()
    {
    }

    private function raise($message, $code = 0)
    {
        if ($this->core) {
            throw new \Inphinit\Exception($message, $code, 3);
        } else {
            throw new \Exception($message, $code);
        }
    }

    public function __destruct()
    {
        if ($this->temporary) {
            fclose($this->temporary);
            $this->driver = $this->temporary = null;
        }
    }
}
