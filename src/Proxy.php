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
    private $options = [];
    private $optionsUpdate = 1;
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

    private $controlAllowOrigin = '';
    private $controlAllowHeaders = [
        'Authorization',
        'Content-Type',
        'Upgrade-Insecure-Requests',
        'X-Requested-With'
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
        $this->refreshOptionsUpdate();
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
        $this->refreshOptionsUpdate();
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
        $this->refreshOptionsUpdate();
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
     * Set the connection timeout in seconds
     *
     * @param int $value
     * @return void
     */
    public function setTimeout($value)
    {
        $this->timeout = $value;
        $this->refreshOptionsUpdate();
    }

    /**
     * Get the connection timeout in seconds
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
        $this->refreshOptionsUpdate();
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
     * Set the list of driver class names used for downloading resources
     *
     * @param array $drivers
     * @return void
     */
    public function setDrivers(array $drivers)
    {
        $this->drivers = $drivers;
    }

    /**
     * Set the Access-Control-Allow-Origin header
     *
     * @param string $origin
     * @throws \Inphinit\Exception
     * @throws \Exception
     * @return void
     */
    public function setControlAllowOrigin($origin)
    {
        if ($origin !== '*' && preg_match('#^https?://.*$#', $origin) !== 1) {
            $this->raise('Invalid origin');
        }

        $this->controlAllowOrigin = $origin;
    }

    /**
     * Set the list of allowed headers
     *
     * @param array $headers
     * @return void
     */
    public function setControlAllowHeaders(array $headers)
    {
        $this->controlAllowHeaders = $headers;
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
        $this->refreshOptionsUpdate();
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
     * Gets the update value (incremental), used by drivers to check if they need to reconfigure something.
     *
     * @return int
     */
    public function getOptionsUpdate()
    {
        return $this->optionsUpdate;
    }

    /**
     * Set the list of allowed URLs for download
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
     * Add a Content-Type to the allowed list
     *
     * @param string $type
     * @param bool $binary
     * @return void
     */
    public function addAllowedType($type, $binary)
    {
        if (is_bool($binary)) {
            $this->allowedTypes[$type] = $binary;
        } else {
            $this->raise('The $binary parameter must be boolean');
        }
    }

    /**
     * Remove a Content-Type from the allowed list
     *
     * @param string $type
     * @return void
     */
    public function removeAllowedType($type)
    {
        unset($this->allowedTypes[$type]);
    }

    /**
     * Check if a given Content-Type is allowed (this method will be used by drivers)
     *
     * @param string      $type
     * @param string|null $errorMessage
     * @return bool
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
     * Set the temporary storage path or stream for downloaded content, eg.: /mnt/storage/, php://temp, php://memory
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
        } elseif ($path !== 'php://memory' && preg_match('#^php://temp(/maxmemory:\d+)?$#', $path) !== 1) {
            $this->raise('Invalid stream: ' . $path);
        }

        $temp = fopen($path, 'r+b');

        if ($temp === false) {
            $this->raise('Failed to open: ' . $path);
        }

        $this->temporary = $temp;
    }

    /**
     * Get the temporary stream resource used for downloaded content
     *
     * @return resource|null
     */
    public function getTemporary()
    {
        return $this->temporary;
    }

    /**
     * Perform the download
     *
     * @param string $url          Set URL for download
     * @throws \Inphinit\Exception
     * @throws \Exception
     * @return void
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

        $httpStatus = $this->httpStatus;
        $contentType = $this->contentType;

        if ($contentType) {
            $contentType = trim($contentType);
            $this->contentType = $contentType;
        }

        if ($httpStatus !== null && ($httpStatus < 200 || $httpStatus >= 300)) {
            if ($this->coreHttpStatus) {
                $this->errorMessage = Status::message($httpStatus, $this->errorMessage);
            } else {
                $this->errorMessage = 'HTTP error: ' . $httpStatus;
            }

            $success = false;
        } elseif ($success) {
            if ($this->isAllowedType($contentType, $this->errorMessage) === false) {
                $this->errorCode = 0;
                $success = false;
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
     * Set the cache duration (in seconds) or disable cache for Proxy::respose() or Proxy::jsonp()
     *
     * @param int $seconds Set seconds
     * @return void
     */
    public function setResponseCacheTime($seconds)
    {
        if ($seconds < 0) {
            $this->raise('Seconds must be 0 or greater');
        }

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
            echo fread($handle, 131072);
        }
    }

    /**
     * Output JSONP callback with URL or data URI content
     *
     * @param string $callback     JavaScript callback function name
     * @throws \Inphinit\Exception
     * @throws \Exception
     * @return void
     */
    public function jsonp($callback)
    {
        if ($this->hasResponse === false) {
            $this->raise('No downloads yet');
        }

        if (preg_match('#^[$_a-z][\w$]*$#i', $callback) !== 1) {
            $this->raise('Invalid callback name');
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
     * @param int $length Optional. The maximum bytes to read. If it is not defined or is -1, it will read the entire remaining buffer.
     * @param int $offset Optional. Seek to the specified offset before reading. If this number is negative, no seeking will occur and reading will start from the current position.
     * @return string|false
     */
    public function getContents($length = -1, $offset = -1)
    {
        return $this->temporary ? stream_get_contents($this->temporary, $length, $offset) : false;
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

    private function refreshOptionsUpdate()
    {
        $this->optionsUpdate += 1;
    }

    private function sendHeaders($contentType)
    {
        header('Access-Control-Allow-Credentials: true');

        if ($this->controlAllowOrigin) {
            header('Access-Control-Allow-Origin: ' . $this->controlAllowOrigin);
        }

        if ($this->controlAllowHeaders) {
            header('Access-Control-Allow-Headers: ' . implode(', ', $this->controlAllowHeaders));
        }

        header('Access-Control-Allow-Methods: OPTIONS, GET');

        $seconds = $this->responseCacheTime;
        $time = time();

        if ($seconds > 0) {
            header('Access-Control-Max-Age: ' . $seconds);
            header('Cache-Control: public, max-age=' . $seconds);
            $date = gmdate('D, d M Y H:i:s', $time + $seconds);
        } else {
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            $date = gmdate('D, d M Y H:i:s');
        }

        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $time) . ' GMT');
        header('Expires: ' . $date . ' GMT');
        header('Content-type: ' . $contentType);
    }

    private function validateUrl($url)
    {
        if ($this->allowedUrls) {
            if ($this->allowedUrlsRegEx === null) {
                foreach ($this->allowedUrls as &$entry) {
                    $entry = preg_quote($entry, '#');
                    $entry = str_replace('\\*', '[^/]+', $entry);
                }

                $this->allowedUrlsRegEx = '#^(' . implode('|', $this->allowedUrls) . ')$#';
            }

            if (preg_match($this->allowedUrlsRegEx, $url) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function raise($message, $code = 0)
    {
        $message = get_class($this->driver) . ': ' . $message;

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
