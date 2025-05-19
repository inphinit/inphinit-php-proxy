<?php
/**
 * Inphinit Proxy
 *
 * Copyright (c) 2025 Guilherme Nascimento
 *
 * Released under the MIT license
 */

namespace Inphinit\Proxy;

class Proxy
{
    private $maxDownloadSize = 2097152;
    private $maxRedirs = 5;
    private $referer;
    private $timeout = 30;
    private $userAgent;

    private $temporary;
    private $options = [
        'update' => 0
    ];
    private $driver;
    private $drivers = [];
    private $allowedUrls = [];
    private $allowedUrlsRegEx;
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

    private $coreException = false;
    private $coreHttpStatus = false;

    public function __construct()
    {
        $this->coreException = class_exists('Inphinit\Exception');
        $this->coreHttpStatus = class_exists('Inphinit\Http\Status');
    }

    /**
     * Set the maximum allowed download size
     *
     * @param int $value
     * @return void
     */
    public function setMaxDownloadSize($value)
    {
        $this->maxDownloadSize = $value;
        $this->refreshOptions();
    }

    /**
     * Get the maximum allowed download size
     *
     * @return int
     */
    public function getMaxDownloadSize()
    {
        return $this->maxDownloadSize;
    }

    /**
     * Set the maximum number of HTTP redirects
     *
     * @param int $value
     * @return void
     */
    public function setMaxRedirs($value)
    {
        $this->maxRedirs = $value;
        $this->refreshOptions();
    }

    /**
     * Get the maximum number of HTTP redirects
     *
     * @return int
     */
    public function getMaxRedirs()
    {
        return $this->maxRedirs;
    }

    /**
     * Set the Referer request header
     *
     * @param string $value
     * @return void
     */
    public function setReferer($value)
    {
        $this->referer = $value;
        $this->refreshOptions();
    }

    /**
     * Get the Referer request header
     *
     * @return string
     */
    public function getReferer()
    {
        return $this->referer;
    }

    /**
     * Set connection timeout
     *
     * @param int $value
     * @return void
     */
    public function setTimeout($value)
    {
        $this->timeout = $value;
        $this->refreshOptions();
    }

    /**
     * Get connection timeout
     *
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Set the User-Agent request header
     *
     * @param string $value
     * @return void
     */
    public function setUserAgent($value)
    {
        $this->userAgent = $value;
        $this->refreshOptions();
    }

    /**
     * Get the User-Agent request header
     *
     * @return string
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    /**
     * Set drivers used to download the resource
     *
     * @param array $drivers Set drivers
     * @return void
     */
    public function setDrivers(array $drivers)
    {
        $this->drivers = $drivers;
    }

    /**
     * Set generic options
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function setOptions($key, $value)
    {
        $this->options[$key] = $value;
        $this->refreshOptions();
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
     * Set allowed URLs
     *
     * @param array $urls
     * @return void
     */
    public function setAllowedUrls(array $urls)
    {
        $this->allowedUrls = $urls;
        $this->allowedUrlsRegEx = null;
    }

    /**
     * Add Content-Type to the allowed list
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
     * Remove Content-Type from the allowed list
     *
     * @param string $type
     * @return void
     */
    public function removeAllowedType($type)
    {
        unset($this->allowedTypes[$type]);
    }

    /**
     * Check if Content-Type is allowed
     *
     * @param string $type
     * @return void
     */
    public function isAllowedType($type, &$errorMessage = null)
    {
        $type = trim($type);
        $pos = strpos($type, ';');

        if ($pos > 0) {
            $type = substr($type, 0, $pos);
        }

        if (array_key_exists($type, $this->allowedTypes)) {
            return true;
        }

        $errorMessage = 'The Content-Type header has the value "' . $type . '", which is not allowed';

        return false;
    }

    /**
     * Set temporary handle path, eg.: /mnt/storage/, php://temp, php://memory
     *
     * @param string $path
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

        if ($temp === false) {
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
     * @param string $url          Set URL for download
     * @throws \Inphinit\Exception
     * @throws \Exception
     * @return bool
     */
    public function download($url)
    {
        if ($this->temporary === null) {
            $temporary = tmpfile();

            if ($temporary) {
                $this->temporary = $temporary;
            } else {
                $this->raise('Failed to open temporary file');
            }
        }

        if ($this->validateUrl($url) === false) {
            $this->raise('URL not allowed: ' . $url);
        }

        $this->errorCode = null;
        $this->errorMessage = null;
        $this->httpStatus = null;

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
                $this->raise('None of the defined drivers are supported');
            }
        }

        $success = $this->driver->exec($url, $this->httpStatus, $this->contentType, $this->errorCode, $this->errorMessage);

        if ($success) {
            $httpStatus = $this->httpStatus;
            $contentType = $this->contentType;

            if ($contentType) {
                $contentType = trim($contentType);
            }

            $this->contentType = $contentType;

            if ($httpStatus !== null && ($httpStatus < 200 || $httpStatus >= 300)) {
                $success = false;

                $this->errorCode = $httpStatus;
                $this->errorMessage = 'HTTP error: ' . $httpStatus;

                if ($this->coreHttpStatus) {
                    $this->errorMessage = Status::message($httpStatus, $this->errorMessage);
                }
            } elseif ($this->isAllowedType($contentType, $this->errorMessage) === false) {
                $success = false;
                $this->errorCode = 0;
            }
        }

        if ($success) {
            $this->hasResponse = true;
        } else {
            $this->reset();

            if ($this->errorMessage === null) {
                $this->errorMessage = 'An unexpected issue occurred';
            }

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

        $this->sendHeaders($this->contentType);

        $handle = $this->temporary;

        rewind($handle);

        while (feof($handle) === false) {
            echo fgets($handle, 131072);
        }
    }

    /**
     * Output JSONP callback with URL or data URI content
     *
     * @param string $callback     Set callback
     * @throws \Inphinit\Exception
     * @throws \Exception
     * @return void
     */
    public function jsonp($callback)
    {
        if ($this->hasResponse === false) {
            $this->raise('No downloads yet');
        }

        $this->sendHeaders('application/javascript');

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
            while (feof($handle) === false) {
                $raw = fread($handle, 8151);
                echo base64_encode($raw);
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
     * If last download was successful, Content-Type will be returned
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
     * Reset last download
     *
     * @return void
     */
    public function reset()
    {
        $this->contentType = null;
        $this->hasResponse = false;

        if ($this->temporary) {
            ftruncate($this->temporary, 0);
            rewind($this->temporary);
        }
    }

    private function refreshOptions()
    {
        $this->options['update'] += 1;
    }

    private function sendHeaders($contentType)
    {
        header('Access-Control-Allow-Headers: *');
        header('Access-Control-Allow-Methods: OPTIONS, GET');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Request-Method: *');

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
        header('Content-type: ' . $contentType);
    }

    private function validateUrl($url)
    {
        $urlList = $this->allowedUrls;

        if ($urlList) {
            if ($this->allowedUrlsRegEx === null) {
                $regex = implode('|', $urlList);
                $regex = preg_quote($regex, '#');
                $regex = strtr($regex, array(
                    '\\*' => '[^/]+',
                    '\\|' => '|'
                ));

                $this->allowedUrlsRegEx = '#^(' . $regex . ')#';
            }

            if (preg_match($this->allowedUrlsRegEx, $url) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function raise($message, $code = 0)
    {
        if ($this->coreException) {
            throw new \Inphinit\Exception($message, $code, 3);
        } else {
            throw new \Exception($message, $code);
        }
    }

    public function __destruct()
    {
        if ($this->temporary) {
            $meta_data = stream_get_meta_data($this->temporary);
            $path = $meta_data['uri'];

            fclose($this->temporary);

            $this->temporary = null;

            if (strpos($path, 'php://') !== 0 && is_file($path)) {
                unlink($path);
            }
        }

        $this->driver = null;
    }
}
