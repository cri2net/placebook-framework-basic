<?php

namespace Placebook\Framework\Core;

use \Exception;

class Http
{
    /**
     * Отправка заголовков для редиректа
     * @param  string  $location    Новая ссылка
     * @param  boolean $permanently Постоянный редирект (301, по умолчанию) или временный (307). OPTIONAL
     * @param  boolean $exit        Нужно ли завершить работу. OPTIONAL
     * @return void
     */
    public static function redirect($location, $permanently = true, $exit = true)
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
     * @param  integer $offset       offset for expire header. OPTIONAL
     * 
     * @return void | string
     */
    public static function gzip($data, $echo = true, $content_type = 'text/html', $charset = 'UTF-8', $offset = 1209600)
    {
        $supportsGzip = (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) && function_exists('gzencode');
        $expire = "expires: " . gmdate("D, d M Y H:i:s", time() + $offset) . " GMT";
        
        if (!$supportsGzip) {
            if (!$echo) {
                return $data;
            }
            $content = $data;
        } else {
            $content = gzencode(trim(preg_replace('/\s+/', ' ', $data)), 9);
        }

        if (!$echo) {
            return $content;
        }

        if ($supportsGzip) {
            header('Content-Encoding: gzip');
            header('Vary: Accept-Encoding');
        }
        
        header("Content-type: $content_type; charset: $charset");
        header("cache-control: must-revalidate");
        header($expire);
        header('Content-Length: ' . strlen($content));

        echo $content;
    }

    /**
     * Выкачивание содержимого по ссылке через curl
     * @param  string $url              Ссылка
     * @param  boolean $follow_location Управление опцией CURLOPT_FOLLOWLOCATION. OPTIONAL
     * @param  array   $extra_headers   Задаёт дополнительные заголовки запроса через CURLOPT_HTTPHEADER. OPTIONAL
     * @return string                   Содержимое
     */
    public static function httpGet($url, $follow_location = true, $extra_headers = [])
    {
        $ch = curl_init();
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_HEADER         => false,
            CURLOPT_HTTPGET        => true,
            CURLOPT_RETURNTRANSFER => true,
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
     * POST запрос через curl
     * @param  string       $url Ссылка
     * @param  array|string $data Данные
     * @return string Ответ
     */
    public static function httpPost($url, $data)
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
     * Выкачивание содержимого через file_get_contents
     * С подстановкой User-Agent, как будто браузер
     * @param  string $url    HTTP cсылка
     * @param  string $method Тип HTTP запроса. OPTIONAL
     * @param  array  $data   Передаваемые данные в запросе. OPTIONAL
     * @return string Содержимое (сырой ответ)
     */
    public static function fgets($url, $method = 'GET', $data = array())
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
     * Метод парсит переменную $_SERVER и генерирует массив с HTTP Заголовками запроса
     * Как аналог \getallheaders(), но она доступна только при работе php из-под apache
     * @link https://github.com/ralouphie/getallheaders/ Оригинальный код функции
     * 
     * @return array http headers
     */
    public static function getAllHeaders()
    {
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
