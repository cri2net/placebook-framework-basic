<?php

namespace Placebook\Framework\Core;

use \Exception;

class Api
{
    /**
     * Отправка готового запроса к API
     * @param  string $query     Текст запроса в GraphQL
     * @param  array  $variables Массив с переменными. OPTIONAL
     * @param  string $api_url   Ссылка на API. OPTIONAL
     * @param  string $api_token Токен для доступа к API. OPTIONAL
     * @return StdClass object   Ответ от API
     */
    public static function rawRequest($query, $variables = [], $api_url = null, $api_token = null)
    {
        if (($api_url === null) && defined('API_URL')) {
            $api_url = API_URL;
        }
        if (($api_token === null) && defined('API_TOKEN')) {
            $api_token = API_TOKEN;
        }

        if (empty($api_url) || empty($api_token)) {
            throw new Exception("Please specifed url and token for API");
        }

        $data = json_encode([
            'query'     => $query,
            'variables' => $variables,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (function_exists('curl_init')) {
            $response = self::sendCurlRequest($data, $api_url, $api_token);
        } else {
            $response = self::sendFgetsRequest($data, $api_url, $api_token);
        }

        $response = self::processResponse($response);
        return $response;
    }

    /**
     * Отправляет подготовленный запрос к API через cURL
     * @param  string $data  JSON строка с запросом
     * @param  string $url   Ссылка на API
     * @param  string $token Токен для доступа к API
     * @return string        Ответ от API
     */
    protected static function sendCurlRequest($data, $url, $token)
    {
        try {
            $acceptLanguage = SystemConfig::get('acceptLanguage', '');
            $headers = SystemConfig::get('api.extraHeaders', []);
            $headers[] = "Accept-Language: $acceptLanguage";
        } catch (Exception $e) {
            $headers = [];
        }
        
        $headers[] = 'Content-Type: text/json';
        $headers[] = "Authorization: $token";

        $ch = curl_init();
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_HEADER         => false,
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER     => $headers,
        ];
        
        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }
    
    /**
     * Отправляет подготовленный запрос к API через file_get_contents
     * @param  string $data  JSON строка с запросом
     * @param  string $url   Ссылка на API
     * @param  string $token Токен для доступа к API
     * @return string        Ответ от API
     */
    protected static function sendFgetsRequest($data, $url, $token)
    {
        try {
            $acceptLanguage = SystemConfig::get('acceptLanguage', '');
            $headers = SystemConfig::get('api.extraHeaders', []);
            $headers[] = "Accept-Language: $acceptLanguage";
        } catch (Exception $e) {
            $headers = [];
        }
        
        $headers[] = 'Content-Type: text/json';
        $headers[] = "Authorization: $token";

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => $headers,
                'content' => $data,
            ],
        ]);

        return file_get_contents($url, false, $context);
    }

    /**
     * Кодирует аргументы в строку, чтоб не нарушился синтаксис GraphQL
     * @param  array  $args Ассоциативный массив с аргументами
     * @return string Строка с закодированными аргументами
     */
    public static function encodeArguments(array $args)
    {
        $res = '';

        foreach ($args as $key => $value) {
            $res .= "$key: " . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ',';
        }

        return trim($res, ',');
    }

    /**
     * Обработка ответа от API
     * @param  string $response Сырой ответ от API
     * @return StdClass object
     */
    public static function processResponse($response)
    {
        if ($response === false) {
            throw new Exception("Response is false");
        }

        $json = json_decode($response);
        if (($json === false) || ($json === null)) {
            throw new Exception("Response is not valid JSON");
        }

        if (!empty($json->data->errors)) {
            foreach ($json->data->errors as $error) {

                if (isset($error->code)) {
                    throw new Exception($error->message, $error->code);
                } else {
                    throw new Exception($error->message);
                }
            }
        }

        return $json->data;
    }
}
