<?php
/*
 * html2canvas-php-proxy 1.2.0
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

define('INPHINIT_PROXY_DIR', 'cache');                // Set folder where the images are stored
define('INPHINIT_PROXY_DIR_CLEANUP', 60 * 5 * 1000);  // Set timeout to clear INPHINIT_PROXY_DIR folder contents
define('INPHINIT_PROXY_DIR_PERMISSION', 0666);        // Set folder permission - use 0644 or 0666 to prevent sploits
define('INPHINIT_PROXY_HTTP_CACHE', 60 * 5 * 1000);   // Set response timeout in seconds or 0/false/null/-1 to disable
define('INPHINIT_PROXY_TIMEOUT', 20);                 // Set download timeout
define('INPHINIT_PROXY_MAX_REDIRS', 10);              // Set max HTTP redirects (location: header)
define('INPHINIT_PROXY_DATAURI', false);              // Set to true to use "Data URI scheme", otherwise return file path
define('INPHINIT_PROXY_PREFER_CURL', true);           // Enable curl (if avaliable)
define('INPHINIT_PROXY_ALLOWED_DOMAINS', '*');        // Set `*` to allow any domain, `*.site.com` for subdomains, and for specific domains use something like `site.com,www.site.com`
define('INPHINIT_PROXY_ALLOWED_PORTS', '80,443');     // Set allowed ports, separated by commas
define('INPHINIT_PROXY_CALLBACK', 'console.error');   // Set alternative callback function

/*
 * Set `false` for disable SSL check
 * Set `true` for enable SSL check (require config `curl.cainfo=/path/to/cacert.pem` in php.ini)
 * Or define path if need config CAINFO manually like this `define('INPHINIT_PROXY_SSL_VERIFY', '/path/to/cacert.pem')`
 */
define('INPHINIT_PROXY_SSL_VERIFY', true);

/*
 * Set 0 for default version, for other versions see constants in: https://www.php.net/manual/en/curl.constants.php#constant.curl-sslversion-default
 * Samples:
 * CURL_SSLVERSION_SSLv2
 * CURL_SSLVERSION_SSLv3
 * CURL_SSLVERSION_TLSv1
 * CURL_SSLVERSION_TLSv1_0
 * CURL_SSLVERSION_TLSv1_1
 * CURL_SSLVERSION_TLSv1_2
 * CURL_SSLVERSION_TLSv1_3 
 */
define('INPHINIT_PROXY_SSL_VERSION', 0);

// Constants (don't change)
define('INPHINIT_PROXY_INIT', time());

$callback = empty($_GET['callback']) ? false : $_GET['callback'];

/*
If execution has reached the time limit prevents page goes blank (off errors)
or generate an error in PHP, which does not work with the DEBUG (from html2canvas.js)
*/
$max_exec = (int) ini_get('max_execution_time');

// Reduces 5 seconds to ensure the execution of the DEBUG
define('INPHINIT_PROXY_MAX_EXEC', $max_exec < 1 ? 0 : ($max_exec - 5));

$http_port = 0;

$tmp = null; // tmp var usage
$response = array();

/**
 * Get headers for requests
 * @return array
 */
function inphinit_boot_headers()
{
    static $headers;

    if ($headers !== null) {
        return $headers;
    }

    $headers = array();

    if (false === empty($_SERVER['HTTP_ACCEPT'])) {
        $headers[] = 'Accept: ' . $_SERVER['HTTP_ACCEPT'];
    }

    if (false === empty($_SERVER['HTTP_USER_AGENT'])) {
        $headers[] = 'User-Agent: ' . $_SERVER['HTTP_USER_AGENT'];
    }

    if (false === empty($_SERVER['HTTP_REFERER'])) {
        $headers[] = 'Referer: ' . $_SERVER['HTTP_REFERER'];
    }

    return $headers;
}

/**
 * Check SSL stream transport
 * @return boolean  returns false if have an problem, returns true if ok
 */
function inphinit_proxy_ssl_support()
{
    static $supported;

    if ($supported !== null) {
        return $supported;
    }

    return $supported = in_array('ssl', stream_get_transports());
}

/**
 * Remove old files defined by INPHINIT_PROXY_DIR_CLEANUP
 * @return void
 */
function inphinit_proxy_clean()
{
    $path = INPHINIT_PROXY_DIR . '/';

    if (
        // prevents this function locks the process that was completed
        (INPHINIT_PROXY_MAX_EXEC === 0 || (time() - INPHINIT_PROXY_INIT) < INPHINIT_PROXY_MAX_EXEC) &&
        is_dir($path)
    ) {
        $handle = opendir($path);

        if (false !== $handle) {
            while (false !== ($filename = readdir($handle))) {
                $fullpath = $path . $filename;

                if (
                    is_file($fullpath) &&
                    strpos($filename, '~') === 0 &&
                    (INPHINIT_PROXY_INIT - filectime($fullpath)) > INPHINIT_PROXY_DIR_CLEANUP
                ) {
                    unlink($fullpath);
                }
            }
        }
    }
}

/**
 * Detect if content-type is valid and get charset if available
 * @param string $content  Content-type
 * @return array           Always return array
 */
function inphinit_proxy_content_type($content)
{
    $content = strtolower($content);
    $encode = null;

    if (preg_match('#[;](\\s+)?charset[=]#', $content) === 1) {
        $encode = preg_split('#[;](\\s+)?charset[=]#', $content);
        $encode = empty($encode[1]) ? null : trim($encode[1]);
    }

    $mime = trim(
        preg_replace('#[;](.*)?$#', '',
            str_replace('content-type:', '',
                str_replace('/x-', '/', $content)
            )
        )
    );

    if (in_array($mime, array(
        'image/bmp', 'image/windows-bmp', 'image/ms-bmp',
        'image/apng', 'image/png',
        'image/jpeg', 'image/gif',
        'image/avif', 'image/webp',
        'text/html', 'application/xhtml', 'application/xhtml+xml',
        'image/svg+xml', // SVG image
        'image/svg-xml' // Old servers (bug)
    )) === false) {
        return array('error' => $mime . ' mimetype is invalid');
    }

    return array(
        'mime' => $mime,
        'encode' => $encode
    );
}

/**
 * Set response headers
 * @param boolean $nocache  If false set cache (if INPHINIT_PROXY_HTTP_CACHE > 0), If true set no-cache in document
 * @return void
 */
function inphinit_proxy_headers($nocache)
{
    $datetime = gmdate('D, d M Y H:i:s');

    if ($nocache === false && is_int(INPHINIT_PROXY_HTTP_CACHE) && INPHINIT_PROXY_HTTP_CACHE > 0) {
        // save to browser cache
        header('Last-Modified: ' . $datetime . ' GMT');
        header('Cache-Control: max-age=' . (INPHINIT_PROXY_HTTP_CACHE - 1));
        header('Pragma: max-age=' . (INPHINIT_PROXY_HTTP_CACHE - 1));
        header('Expires: ' . gmdate('D, d M Y H:i:s', INPHINIT_PROXY_INIT + INPHINIT_PROXY_HTTP_CACHE - 1));
        header('Access-Control-Max-Age:' . INPHINIT_PROXY_HTTP_CACHE);
    } else {
        // no-cache
        header('Pragma: no-cache');
        header('Cache-Control: no-cache');
        header('Expires: '. $datetime .' GMT');
    }

    // set access-control
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Request-Method: *');
    header('Access-Control-Allow-Methods: OPTIONS, GET');
    header('Access-Control-Allow-Headers: *');
}

/**
 * Converte relative-url to absolute-url
 * @param string $url       Set base url
 * @param string $relative  Set relative url
 * @return string           Always return string, if error occurs, return blank string (invalid schema)
 */
function inphinit_proxy_absolute_path($url, $relative)
{
    if (strpos($relative, '//') === 0) { // http link // site.com/test
        return 'http:' . $relative;
    }

    if (preg_match('#^[a-z0-9]+[:]#i', $relative) !== 0) {
        $pu = parse_url($relative);

        if (preg_match('#^https?$#i', $pu['scheme']) === 0) {
            return '';
        }

        $relative = '';

        if (isset($pu['path'])) {
            $relative .= $pu['path'];
        }

        if (isset($pu['query'])) {
            $relative .= '?' . $pu['query'];
        }

        if (isset($pu['fragment'])) {
            $relative .= '#' . $pu['fragment'];
        }

        return inphinit_proxy_absolute_path($pu['scheme'] . '://' . $pu['host'], $relative);
    }

    if (preg_match('/^[?#]/', $relative) !== 0) {
        return $url . $relative;
    }

    $pu = parse_url($url);
    $pu['path'] = isset($pu['path']) ? preg_replace('#/[^/]*$#', '', $pu['path']) : '';

    $pm = parse_url('http:// 1/' . $relative);
    $pm['path'] = isset($pm['path']) ? $pm['path'] : '';

    $is_path = $pm['path'] !== '' && strpos(strrev($pm['path']), '/') === 0;

    if (strpos($relative, '/') === 0) {
        $pu['path'] = '';
    }

    $b = $pu['path'] . '/' . $pm['path'];
    $b = str_replace('\\', '/', $b);// Confuso ???

    $ab = explode('/', $b);
    $j = count($ab);

    $ab = array_filter($ab, 'strlen');
    $nw = array();

    for ($i = 0; $i < $j; ++$i) {
        if (isset($ab[$i]) === false || $ab[$i] === '.') {
            continue;
        }

        if ($ab[$i] === '..') {
            array_pop($nw);
        } else {
            $nw[] = $ab[$i];
        }
    }

    $relative  = $pu['scheme'] . '://' . $pu['host'] . '/' . implode('/', $nw) . ($is_path ? '/' : '');

    if (isset($pm['query'])) {
        $relative .= '?' . $pm['query'];
    }

    if (isset($pm['fragment'])) {
        $relative .= '#' . $pm['fragment'];
    }

    $nw = null;
    $ab = null;
    $pm = null;
    $pu = null;

    return $relative;
}

/**
 * Validate HTTP url
 * @param string $url  Set base url
 * @return boolean     Returns true if the URL is http or https, otherwise returns false
 */
function inphinit_proxy_is_http($url)
{
    return preg_match('#^https?[:]//.#i', $url) === 1;
}

/**
 * Check if url is allowed
 * @param string $url  Set base url
 * @return boolean     Returns true if allowed, otherwise returns false
*/
function inphinit_proxy_check_domain($url, &$message) {
    $uri = parse_url($url);

    $domains = array_map('trim', explode(',', INPHINIT_PROXY_ALLOWED_DOMAINS));

    if (in_array('*', $domains) === false) {
        $ok = false;

        foreach ($domains as $domain) {
            if ($domain === $uri['host']) {
                $ok = true;
                break;
            } elseif (strpos($domain, '*') !== false) {
                $domain = strtr($domain, array(
                    '*' => '\\w+',
                    '.' => '\\.'
                ));

                if (preg_match('#^' . $domain . '$#i', $uri['host']) === 1) {
                    $ok = true;
                    break;
                }
            }
        }

        if ($ok === false) {
            $message = '"' . $uri['host'] . '" domain is not allowed';
            return false;
        }
    }

    if (empty($uri['port'])) {
        $port = strcasecmp('https', $uri['scheme']) === 0 ? 443 : 80;
    } else {
        $port = $uri['port'];
    }

    $ports = array_map('trim', explode(',', INPHINIT_PROXY_ALLOWED_PORTS));

    if (in_array($port, $ports)) {
        return true;
    }

    $message = '"' . $port . '" port is not allowed';

    return false;
}

/**
 * create folder for images download
 * @return boolean  
*/
function inphinit_proxy_create_folder()
{
    return is_dir(INPHINIT_PROXY_DIR) || mkdir(INPHINIT_PROXY_DIR, INPHINIT_PROXY_DIR_PERMISSION);
}

/**
 * Create a temp file which will receive the download
 * @param  string   $location  Filled with temp path
 * @param  resource $resource  Filled with file pointer resource
 * @return bool
 */
function inphinit_proxy_temp(&$location, &$resource)
{
    $path = tempnam(INPHINIT_PROXY_DIR, '~' . mt_rand(0, 99));

    if ($handle = fopen($path, 'wb')) {
        $location = $path;
        $resource = $handle;
        return true;
    }

    return false;
}

/**
 * download http request using curl extension (If found HTTP 3xx)
 * @param string   $url       URL requested
 * @param resource $resource  File pointer resource destination
 * @return array              retuns array
 */
function inphinit_proxy_curl_download($url, $resource)
{
    $uri = parse_url($url);

    // Reformat url
    $current_url  = (empty($uri['scheme']) ? 'http': $uri['scheme']) . '://';
    $current_url .= empty($uri['host']) ? '': $uri['host'];

    if (isset($uri['port'])) {
        $current_url .= ':' . $uri['port'];
    }

    $current_url .= empty($uri['path']) ? '/': $uri['path'];
    $current_url .= empty($uri['query']) ? '': ('?' . $uri['query']);

    $ch = curl_init();

    if (INPHINIT_PROXY_SSL_VERIFY === true) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    } elseif (is_string(INPHINIT_PROXY_SSL_VERIFY)) {
        if (is_file(INPHINIT_PROXY_SSL_VERIFY)) {
            curl_close($ch);
            return array('error' => 'Not found certificate: ' . INPHINIT_PROXY_SSL_VERIFY);
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_CAINFO, INPHINIT_PROXY_SSL_VERIFY);
    } else {
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    }

    if (INPHINIT_PROXY_SSL_VERSION !== 0) {
        curl_setopt($ch, CURLOPT_SSLVERSION, INPHINIT_PROXY_SSL_VERSION);
    }

    curl_setopt($ch, CURLOPT_TIMEOUT, INPHINIT_PROXY_TIMEOUT);
    curl_setopt($ch, CURLOPT_URL, $current_url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, INPHINIT_PROXY_MAX_REDIRS);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    if (version_compare(PHP_VERSION, '5.1.2', '<')) {
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    }

    if (isset($uri['user'])) {
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_USERPWD, $uri['user'] . ':' . (isset($uri['pass']) ? $uri['pass'] : ''));
    }

    $headers = inphinit_boot_headers();
    $headers[] = 'Host: ' . $uri['host'];
    $headers[] = 'Connection: close';

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $data = curl_exec($ch);

    $curl_err = curl_errno($ch);

    $result = null;

    if ($curl_err !== 0) {
        $result = array('error' => 'CURL failed: (' . $curl_err . ') ' . curl_error($ch));
    } else {
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        if ($http_code != 200) {
            $result = array('error' => 'Request returned HTTP_' . $http_code);
        }

        if ($result === null) {
            $result = inphinit_proxy_content_type($content_type);

            if (empty($result['error'])) {
                fwrite($resource, $data);
            }
        }
    }

    curl_close($ch);

    return $result;
}

/**
 * Download http request recursive (If found HTTP 3xx)
 * @param string   $url       URL requested
 * @param resource $resource  Save downloaded url contents
 * @return array              retuns array
*/
function inphinit_proxy_socket_download($url, $resource, $caller)
{
    $errno = 0;
    $errstr = '';

    ++$caller;

    if ($caller > INPHINIT_PROXY_MAX_REDIRS) {
        return array('error' => 'Limit of ' . INPHINIT_PROXY_MAX_REDIRS . ' redirects was exceeded, maybe there is a problem: ' . $url);
    }

    $uri = parse_url($url);
    $secure = strcasecmp($uri['scheme'], 'https') === 0;

    if ($secure && inphinit_proxy_ssl_support() === false) {
        return array('error' => 'No SSL stream support detected');
    }

    $port = empty($uri['port']) ? ($secure ? 443 : 80) : ((int) $uri['port']);
    $host = ($secure ? 'ssl://' : '') . $uri['host'];

    $fp = fsockopen($host, $port, $errno, $errstr, INPHINIT_PROXY_TIMEOUT);

    if ($fp === false) {
        return array('error' => 'SOCKET: ' . $errstr . '(' . $errno . ') - ' . $host . ':' . $port);
    } else {
        $bl = "\r\n";

        fwrite(
            $fp, 'GET ' . (
                empty($uri['path'])  ? '/' : $uri['path']
            ) . (
                empty($uri['query']) ? '' : ('?' . $uri['query'])
            ) . ' HTTP/1.0' . $bl
        );

        if (isset($uri['user'])) {
            $auth = base64_encode($uri['user'] . ':' . (isset($uri['pass']) ? $uri['pass'] : ''));
            fwrite($fp, 'Authorization: Basic ' . $auth . $bl);
        }

        foreach (inphinit_boot_headers() as $header) {
            fwrite($fp, $header . $bl);
        }

        fwrite($fp, 'Host: ' . $uri['host'] . $bl);
        fwrite($fp, 'Connection: close' . $bl . $bl);

        $is_redirect = true;
        $is_body = false;
        $is_http = false;
        $encode = null;
        $mime = null;
        $data = '';

        while (false === feof($fp)) {
            if (INPHINIT_PROXY_MAX_EXEC !== 0 && (time() - INPHINIT_PROXY_INIT) >= INPHINIT_PROXY_MAX_EXEC) {
                return array('error' => 'Maximum execution time of ' . (INPHINIT_PROXY_MAX_EXEC + 5) . ' seconds exceeded, configure this with ini_set/set_time_limit or "php.ini" (if safe_mode is enabled)');
            }

            $data = fgets($fp);

            if ($data === false) {
                continue;
            }

            if ($is_http === false) {
                if (preg_match('#^HTTP/1\.#i', $data) === 0) {
                    fclose($fp); // Close connection
                    $data = '';
                    return array('error' => 'This request did not return a HTTP response valid');
                }

                $tmp = preg_replace('#(HTTP/1[.]\\d |[^0-9])#i', '',
                    preg_replace('#^(HTTP/1[.]\\d \\d{3}) [\\w\\W]+$#i', '$1', $data)
                );

                if ($tmp === '304') {
                    fclose($fp);// Close connection
                    $data = '';
                    return array('error' => 'Request returned HTTP_304, this status code is incorrect because the html2canvas not send Etag');
                } else {
                    $is_redirect = preg_match('#^3\\d{2}$#', $tmp) !== 0;

                    if ($is_redirect === false && $tmp !== '200') {
                        fclose($fp);
                        $data = '';
                        return array('error' => 'Request returned HTTP_' . $tmp);
                    }

                    $is_http = true;

                    continue;
                }
            }

            if ($is_body === false) {
                if (preg_match('#^location[:]#i', $data) !== 0) { // 200 force 302
                    fclose($fp);// Close connection

                    $data = trim(preg_replace('#^location[:]#i', '', $data));

                    if ($data === '') {
                        return array('error' => '"Location:" header is blank');
                    }

                    $next_uri = $data;
                    $data = inphinit_proxy_absolute_path($url, $data);

                    if ($data === '') {
                        return array('error' => 'Invalid scheme in url (' . $next_uri . ')');
                    }

                    if (inphinit_proxy_is_http($data) === false) {
                        return array('error' => '"Location:" header redirected for a non-http url (' . $data . ')');
                    }

                    return inphinit_proxy_socket_download($data, $resource, $caller);
                } elseif (preg_match('#^content-length[:](\s)?0$#i', $data) !== 0) {
                    fclose($fp);
                    $data = '';
                    return array('error' => 'source is blank (Content-length: 0)');
                } elseif (preg_match('#^content-type[:]#i', $data) !== 0) {
                    $response = inphinit_proxy_content_type($data);

                    if (isset($response['error'])) {
                        fclose($fp);
                        return $response;
                    }

                    $encode = $response['encode'];
                    $mime = $response['mime'];
                } elseif ($is_body === false && trim($data) === '') {
                    $is_body = true;
                    continue;
                }
            } elseif ($is_redirect) {
                fclose($fp);
                $data = '';
                return array('error' => 'The response should be a redirect "' . $url . '", but did not inform which header "Localtion:"');
            } elseif ($mime === null) {
                fclose($fp);
                $data = '';
                return array('error' => 'Not set the mimetype from "' . $url . '"');
            } else {
                fwrite($resource, $data);
                continue;
            }
        }

        fclose($fp);

        $data = '';

        if ($is_body === false) {
            return array('error' => 'Content body is empty');
        } elseif ($mime === null) {
            return array('error' => 'Not set the mimetype from "' . $url . '"');
        }

        return array(
            'mime' => $mime,
            'encode' => $encode
        );
    }
}

if (empty($_SERVER['HTTP_HOST'])) {
    $response = array('error' => 'The client did not send the Host header');
} elseif (empty($_SERVER['SERVER_PORT'])) {
    $response = array('error' => 'The Server-proxy did not send the PORT (configure PHP)');
} elseif (INPHINIT_PROXY_MAX_EXEC < 10) {
    $response = array('error' => 'Execution time is less 15 seconds, configure this with ini_set/set_time_limit or "php.ini" (if safe_mode is enabled), recommended time is 30 seconds or more');
} elseif (INPHINIT_PROXY_MAX_EXEC <= INPHINIT_PROXY_TIMEOUT) {
    $response = array('error' => 'The execution time is not configured enough to TIMEOUT in SOCKET, configure this with ini_set/set_time_limit or "php.ini" (if safe_mode is enabled), recommended that the "max_execution_time =;" be a minimum of 5 seconds longer or reduce the TIMEOUT in "define(\'INPHINIT_PROXY_TIMEOUT\', ' . INPHINIT_PROXY_TIMEOUT . ');"');
} elseif (empty($_GET['url'])) {
    $response = array('error' => 'No such parameter "url"');
} elseif (inphinit_proxy_is_http($_GET['url']) === false) {
    $response = array('error' => 'Only http scheme and https scheme are allowed');
} elseif (inphinit_proxy_check_domain($_GET['url'], $message) === false) {
    $response = array('error' => $message);
} elseif (inphinit_proxy_create_folder() === false) {
    $err = error_get_last();
    $response = array('error' => 'Can not create directory'. (
        $err !== null && empty($err['message']) ? '' : (': ' . $err['message'])
    ));
    $err = null;
} else {
    $http_port = (int) $_SERVER['SERVER_PORT'];

    $success = inphinit_proxy_temp($temp_location, $temp_source);

    if ($success === false) {
        $err = error_get_last();

        $response = array('error' => 'Can not create file'. (
            $err !== null && empty($err['message']) ? '' : (': ' . $err['message'])
        ));

        $err = null;
    } elseif (INPHINIT_PROXY_PREFER_CURL && function_exists('curl_init')) {
        $response = inphinit_proxy_curl_download($_GET['url'], $temp_source);
    } else {
        $response = inphinit_proxy_socket_download($_GET['url'], $temp_source, 0);
    }

    if ($success) fclose($temp_source);
}

// set mime-type
header('Content-Type: application/javascript');

if (is_array($response) && false === empty($response['mime'])) {
    clearstatcache();

    if (false === file_exists($temp_location)) {
        $response = array('error' => 'Request was downloaded, but file can not be found, try again');
    } elseif (filesize($temp_location) < 1) {
        $response = array('error' => 'Request was downloaded, but there was some problem and now the file is empty, try again');
    } else {
        $extension = str_replace(array('image/', 'text/', 'application/'), '', $response['mime']);
        $extension = str_replace(array('windows-bmp', 'ms-bmp'), 'bmp', $extension);
        $extension = str_replace(array('svg+xml', 'svg-xml'), 'svg', $extension);
        $extension = str_replace('xhtml+xml', 'xhtml', $extension);
        $extension = str_replace('jpeg', 'jpg', $extension);

        $location_file = $_GET['url'];
        $location_file = INPHINIT_PROXY_DIR . '/~' . strlen($location_file) . '.' . sha1($location_file) . '.' . $extension;

        if (file_exists($location_file)) {
            unlink($location_file);
        }

        if (chmod($temp_location, INPHINIT_PROXY_DIR_PERMISSION) && rename($temp_location, $location_file)) {
            inphinit_proxy_headers(false);
            inphinit_proxy_clean();

            $mime = $response['mime'];

            if ($response['encode'] !== null) {
                $mime .= '; charset=' . rawurlencode($response['encode']);
            }

            if ($callback === false) {
                header('Content-Type: ' . $mime);
                echo file_get_contents($location_file);
            } elseif (INPHINIT_PROXY_DATAURI) {
                $tmp = $response = null;

                header('Content-Type: application/javascript');

                if (strpos($mime, 'image/svg') !== 0 && strpos($mime, 'image/') === 0) {
                    echo $callback, '("data:', $mime, ';base64,',
                        base64_encode(
                            file_get_contents($location_file)
                        ),
                    '");';
                } else {
                    echo $callback, '("data:', $mime, ',',
                        rawurlencode(file_get_contents($location_file)),
                    '");';
                }
            } else {
                $tmp = $response = null;

                header('Content-Type: application/javascript');

                $dir_name = dirname($_SERVER['SCRIPT_NAME']);

                if ($dir_name === '\/' || $dir_name === '\\') {
                    $dir_name = '';
                }

                echo $callback, '(',
                    json_encode(
                        ($http_port === 443 ? 'https://' : 'http://') .
                        preg_replace('#[:]\\d+$#', '', $_SERVER['HTTP_HOST']) .
                        ($http_port === 80 || $http_port === 443 ? '' : (
                            ':' . $_SERVER['SERVER_PORT']
                        )) .
                        $dir_name. '/' .
                        $location_file
                    ),
                ');';
            }

            exit;
        } else {
            $response = array('error' => 'Failed to rename the temporary file');
        }
    }
}

if (is_array($tmp) && isset($temp_location) && file_exists($temp_location)) {
    // Remove temporary file if an error occurred
    unlink($temp_location);
}

inphinit_proxy_headers(true); // no-cache

header('Content-Type: application/javascript');

inphinit_proxy_clean();

if ($callback === false) {
    $callback = INPHINIT_PROXY_CALLBACK;
}

echo $callback, '(',
    json_encode('error: html2canvas-proxy-php: ' . $response['error']),
');';
