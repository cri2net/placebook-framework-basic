<?php

namespace Placebook\Framework\Core;

use Exception;

class Api
{
    /**
     * Send request to API
     * @param  string $query     GraphQL query
     * @param  array  $variables Array with variables. OPTIONAL
     * @param  string $api_url   API URL. OPTIONAL
     * @param  string $api_token API token. OPTIONAL
     * @return StdClass object   response from API
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

        $data = ['query' => $query];
        if (!empty($variables)) {
            $data['variables'] = $variables;
        }
        $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response = (function_exists('curl_init'))
            ? self::sendCurlRequest($data, $api_url, $api_token)
            : self::sendFgetsRequest($data, $api_url, $api_token);

        $response = self::processResponse($response, $response_array);
        try {
            $as_array = SystemConfig::get('api.as_array', false);
        } catch (Exception $e) {
            $as_array = false;
        }

        return ($as_array) ? $response_array : $response;
    }

    /**
     * Send request to API via cURL
     * @param  string $data  GraphQL string with request
     * @param  string $url   API URL
     * @param  string $token API token
     * @return string        Raw response
     */
    protected static function sendCurlRequest($data, $url, $token)
    {
        $headers = [];

        try {
            $acceptLanguage = SystemConfig::get('acceptLanguage', '');
            $headers = SystemConfig::get('api.extraHeaders', []);
            $headers[] = "Accept-Language: $acceptLanguage";
        } catch (Exception $e) {
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
     * Send request to API via file_get_contents
     * @param  string $data  GraphQL string with request
     * @param  string $url   API URL
     * @param  string $token API token
     * @return string        Raw response
     */
    protected static function sendFgetsRequest($data, $url, $token)
    {
        $headers = [];

        try {
            $acceptLanguage = SystemConfig::get('acceptLanguage', '');
            $headers = SystemConfig::get('api.extraHeaders', []);
            $headers[] = "Accept-Language: $acceptLanguage";
        } catch (Exception $e) {
        }
        
        $headers[] = 'Content-Type: application/json';
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
     * Encode args to GraphQL string
     * @param  array  $args assoc array with args
     * @return string Args in GraphQL string
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
     * Process response from API
     * @param  string $response       Raw response from API
     * @param  mixed  $response_array Raw response from API as array. OPTIONAL
     * @return StdClass object
     */
    public static function processResponse($response, &$response_array = null)
    {
        if ($response === false) {
            throw new Exception("Response is false");
        }

        $json = json_decode($response);
        if (($json === false) || ($json === null)) {
            throw new Exception("Response is not valid JSON");
        }

        if (!empty($json->data->errors)) {

            $error = $json->data->errors[0];
            if (isset($error->code)) {
                throw new Exception($error->message, $error->code);
            } else {
                throw new Exception($error->message);
            }
        }

        $response_array = json_decode($response, true);
        $response_array = $response_array['data'];

        return $json->data;
    }
}
