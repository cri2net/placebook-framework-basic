<?php

namespace Placebook\Framework\Core;

use Exception;

class Http
{
    /**
     * Sending headers to redirect
     * @param  string  $location    New location
     * @param  boolean $permanently Permanent redirect (301, by default) or temporary (307). OPTIONAL
     * @param  boolean $exit        Do I need to complete the work. OPTIONAL
     * @return void
     */
    public static function redirect(string $location, bool $permanently = true, bool $exit = true)
    {
        if ($permanently) {
            header("HTTP/1.1 301 Moved Permanently");
        } else {
            header("HTTP/1.1 307 Temporary Redirect");
        }
        header("Location: $location");
        if ($exit) {
            exit();
        }
    }

    /**
     * Enable GZIP
     * 
     * @param  string  $data         content for compression
     * @param  boolean $echo         need echo. OPTIONAL
     * @param  string  $content_type Content-type for header. OPTIONAL
     * @param  string  $charset      charset of $data. OPTIONAL
     * 
     * @return void | string
     */
    public static function gzip(
        string $data,
        bool $echo = true,
        string $content_type = 'text/html',
        string $charset = 'UTF-8'
    )
    {
        $supportsGzip = (
            isset($_SERVER['HTTP_ACCEPT_ENCODING'])
            && (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false)
            && function_exists('gzencode')
        );
        
        if (!$supportsGzip) {
            if (!$echo) {
                return $data;
            }
            $content = $data;
        } else {
            $content = gzencode($data, 9);
        }

        if (!$echo) {
            return $content;
        }

        if ($supportsGzip) {
            header('Content-Encoding: gzip');
            header('Vary: Accept-Encoding');
        }
        
        header("Content-type: $content_type; charset: $charset");
        header('Content-Length: ' . strlen($content));

        echo $content;
    }

    /**
     * Downloading content by reference via curl
     * @param  string $url              URL
     * @param  boolean $follow_location Value for CURLOPT_FOLLOWLOCATION. OPTIONAL
     * @param  array   $extra_headers   Sets additional request headers via CURLOPT_HTTPHEADER. OPTIONAL
     * @return string                   Response
     */
    public static function httpGet(string $url, bool $follow_location = true, array $extra_headers = [])
    {
        $ch = curl_init();
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_HEADER         => false,
            CURLOPT_HTTPGET        => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => $follow_location,
        ];
        if (!empty($extra_headers)) {
            $options[CURLOPT_HTTPHEADER] = $extra_headers;
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    }
    
    /**
     * POST request via curl
     * @param  string       $url  URL
     * @param  array|string $data Data
     * @return string             Response
     */
    public static function httpPost(string $url, $data)
    {
        $data = (is_array($data)) ? http_build_query($data) : $data;

        $ch = curl_init();
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_HEADER         => false,
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_SSL_VERIFYHOST => false,
        ];
        
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    }

    /**
     * Downloading content by reference via file_get_contents with User-Agent, like a browser
     * @param  string $url    URL
     * @param  string $method HTTP type of request. OPTIONAL
     * @param  array  $data   Data. OPTIONAL
     * @return string         Raw response
     */
    public static function fgets(string $url, string $method = 'GET', array $data = [])
    {
        $data = http_build_query($data);

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => [
                    // "Content-type: application/x-www-form-urlencoded",
                    "User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0",
                ],
                'timeout' => 15,
                'content' => $data,
            ],
        ]);

        return file_get_contents($url, false, $context);
    }

    /**
     * Polyfill for \getallheaders() function
     * @link https://github.com/ralouphie/getallheaders/ Original function
     * 
     * @return array http headers
     */
    public static function getAllHeaders() : array
    {
        if (function_exists('\\getallheaders')) {
            return \getallheaders();
        }
        
        $headers = [];
        $copy_server = [
            'CONTENT_TYPE'   => 'Content-Type',
            'CONTENT_LENGTH' => 'Content-Length',
            'CONTENT_MD5'    => 'Content-Md5',
        ];
        
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $key = substr($key, 5);
                if (!isset($copy_server[$key]) || !isset($_SERVER[$key])) {
                    $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                    $headers[$key] = $value;
                }
            } elseif (isset($copy_server[$key])) {
                $headers[$copy_server[$key]] = $value;
            }
        }

        if (!isset($headers['Authorization'])) {
            if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
                $basic_pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
                $headers['Authorization'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $basic_pass);
            } elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
                $headers['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
            }
        }
        
        return $headers;
    }
}
