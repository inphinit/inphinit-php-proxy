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
    const DEFAULT_MAX_REDIRS = 5;
    const DEFAULT_TIMEOUT = 30;

    private $temporary;
    private $core = false;
    private $options = [
        'update' => 0
    ];
    private $driver;
    private $drivers = [];
    private $allowedUrls = ['*'];
    private $allowedTypes = [
        'image/apng' => true,
        'image/png' => true,
        'image/avif' => true,
        'image/webp' => true,
        'image/gif' => true,
        'image/jpeg' => true,
        'image/svg+xml' => false,
        'image/svg-xml' => false // Support for old web servers (an old bug)
    ];

    private $contentType;
    private $httpStatus;
    private $errorCode;
    private $errorMessage;
    private $hasResponse = false;
    private $responseCacheTime = 60;

    public function __construct()
    {
        $this->core = class_exists('Inphinit\App');
        $this->options['max_redirs'] = self::DEFAULT_MAX_REDIRS;
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
        if ($key === 'max_redirs' && $value < 1) {
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
     * @param string $key Optional. If the parameter is not defined, it will
     *                    return an array with all the settings already defined.
     * @return mixed
     */
    public function getOptions($key = null)
    {
        if ($key === null) {
            return $this->options;
        }

        return isset($this->options[$key]) ? $this->options[$key] : null;
    }

    /**
     * Get or redefine allowed URLs
     *
     * @param array $urls Optional. Redefine allowed URLs
     * @return array      Return current allowed URLs
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
     * Add content-type to the allowed list
     *
     * @param string $type
     * @param string $binary
     * @return void
     */
    public function addAllowedType($type, $binary)
    {
        $this->allowedTypes[$type] = $binary;
    }

    /**
     * Remove content-type from the allowed list
     *
     * @param string $type
     * @return void
     */
    public function removeAllowedType($type)
    {
        unset($this->allowedTypes[$type]);
    }

    /**
     * Set temporary handle path, eg.: /mnt/storage/, php://temp, php://memory
     *
     * @param string $path Set path
     * @throws \Inphinit\Exception
     * @throws \Exception
     * @return void
     */
    public function setTemporary($path)
    {
        if ($this->temporary) {
            $this->reset();
            fclose($this->temporary);
        }

        if (strpos($path, 'php://') !== 0) {
            $path = tempnam($path, '~' . mt_rand(0, 99));
        }

        $temp = fopen($path, 'rb+');

        if (!$temp) {
            $this->raise('Failed to open: ' . $path);
        }

        $this->temporary = $temp;
    }

    /**
     * Get temporary stream
     *
     * @return resource
     */
    public function getTemporary()
    {
        return $this->temporary;
    }

    /**
     * Perform download
     *
     * @param string $url Set URL for download
     * @param bool $ignoreDownloadError Optional. Set true for skip exception caused by download error
     * @throws \Inphinit\Exception
     * @throws \Exception
     * @return void
     */
    public function download($url, $ignoreDownloadError = false)
    {
        if ($this->temporary === null) {
            $this->raise('Temporary not defined, you need set Proxy::setTemporary()');
        }

        if ($this->validateUrl($url) === false) {
            $this->raise('URL not allowed: ' . $url);
        }

        $this->reset();

        if ($this->driver === null) {
            $selected = null;

            foreach ($this->drivers as $driver) {
                $selected = new $driver($this);

                if ($selected->available()) {
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

        $contentType = $this->contentType;

        if ($contentType) {
            $contentType = trim($contentType);
        }

        if (array_key_exists($contentType, $this->allowedTypes) === false) {
            $this->raise('Not allowed Content-type: ' . $contentType);
        }

        $this->contentType = $contentType;
        $this->hasResponse = $this->errorCode === null;

        if ($this->errorCode !== null && $this->errorMessage === null) {
            $this->errorMessage = 'Unknown';
        }

        if ($ignoreDownloadError !== true && !$this->hasResponse) {
            $this->raise($this->errorMessage, $this->errorCode);
        }
    }

    /**
     * Enable or disable cache for Proxy::respose() or Proxy::jsonp()
     *
     * @param int $seconds Set seconds
     * @return void
     */
    public function setResponseCacheTime($seconds)
    {
        $this->responseCacheTime = $seconds;
    }

    /**
     * Dump response to output
     *
     * @throws \Inphinit\Exception
     * @throws \Exception
     * @return void
     */
    public function response()
    {
        if ($this->hasResponse === false) {
            $this->raise('No downloads yet');
        }

        header('Access-Control-Allow-Headers: *');
        header('Access-Control-Allow-Methods: OPTIONS, GET');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Request-Method: *');
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
     * @throws \Inphinit\Exception
     * @throws \Exception
     * @return void
     */
    public function jsonp($callback)
    {
        if ($this->hasResponse === false) {
            $this->raise('No downloads yet');
        }

        header('Content-type: application/javascript');

        $this->httpCache();

        $contentType = $this->contentType;
        $extra = null;
        $extract = explode(';', $this->contentType, 2);

        if (isset($extract[1])) {
            list($contentType, $extra) = $extract;
        }

        $binary = $this->allowedTypes[$contentType];

        if ($binary) {
            $contentType .= ';base64';
        } elseif ($extra) {
            $contentType .= ';' . $extra;
        }

        echo $callback, '("';
        echo 'data:' . $contentType . ',';

        $handle = $this->temporary;

        rewind($handle);

        if ($binary) {
            $crlf = '%0D%0A';

            while (feof($handle) === false) {
                $raw = fread($handle, 8151);
                $encoded = base64_encode($raw);
                $encoded = chunk_split($encoded, 76, $crlf);
                echo $encoded;
            }
        } else {
            while (feof($handle) === false) {
                $raw = fgets($handle, 4096);
                echo rawurlencode($raw);
            }
        }

        echo '");';
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
        return $this->temporary ? stream_get_contents($this->temporary, $length, $offset) : '';
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
     * Reset last download
     *
     * @return void
     */
    public function reset()
    {
        $this->contentType = null;
        $this->httpStatus = null;
        $this->errorCode = null;
        $this->errorMessage = null;
        $this->hasResponse = false;

        if ($this->temporary) {
            ftruncate($this->temporary, 0);
            rewind($this->temporary);
        }
    }

    private function httpCache()
    {
        $seconds = $this->responseCacheTime;

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

    private function raise($message, $code = 0)
    {
        $this->reset();

        if ($this->core) {
            throw new \Inphinit\Exception($message, $code, 3);
        } else {
            throw new \Exception($message, $code);
        }
    }

    public function __destruct()
    {
        if ($this->temporary) {
            $meta_data = stream_get_meta_data($this->temporary);

            fclose($this->temporary);

            $this->temporary = null;

            if (strpos($meta_data['uri'], 'php://') !== 0) {
                unlink($meta_data['uri']);
            }
        }

        $this->driver = null;
    }
}
