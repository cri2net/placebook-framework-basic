<?php

namespace Placebook\Framework\Core;

use \Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class Logger implements LoggerInterface
{
    /**
     * Default API URL
     * @var string
     */
    public $api_url = 'http://api.logger.microservices.placebook.com.ua/api/';

    /**
     * Токен доступа к API
     * @var string
     */
    public $api_token = null;

    /**
     * Constructor
     * @param string $api_token Токен доступа к API
     */
    public function __construct($api_token)
    {
        $this->api_token = $api_token;
    }

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array  $context
     *
     * @return integer Log ID
     */
    public function emergency($message, array $context = [])
    {
        return $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array  $context
     *
     * @return integer Log ID
     */
    public function alert($message, array $context = [])
    {
        return $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array  $context
     *
     * @return integer Log ID
     */
    public function critical($message, array $context = [])
    {
        return $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array  $context
     *
     * @return integer Log ID
     */
    public function error($message, array $context = [])
    {
        return $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array  $context
     *
     * @return integer Log ID
     */
    public function warning($message, array $context = [])
    {
        return $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array  $context
     *
     * @return integer Log ID
     */
    public function notice($message, array $context = [])
    {
        return $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array  $context
     *
     * @return integer Log ID
     */
    public function info($message, array $context = [])
    {
        return $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array  $context
     *
     * @return integer Log ID
     */
    public function debug($message, array $context = [])
    {
        return $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Создание лога по API
     * @param  string $level   Важность лога
     * @param  string $message Сообщение
     * @param  array  $context Массив с дополнительными данными
     * @return integer Log ID
     */
    public function log($level, $message, array $context = [])
    {
        $data = [
            'level'   => $level,
            'message' => $message,
            'context' => $context,
        ];

        $args = [];

        if (function_exists('gzcompress')) {
            $args['gzcompress'] = true;
            $args['content_base64'] = base64_encode(gzcompress(serialize($data), 9));
        } else {
            $args['content_base64'] = base64_encode(serialize($data));
        }

        try {
            $args = Api::encodeArguments($args);
            $response = Api::rawRequest("mutation { logAdd($args) }", [], $this->api_url, $this->api_token);
        } catch (Exception $e) {
            return false;
        }

        return (isset($response->data->logAdd)) ? $response->data->logAdd : 0;
    }

    public function shutdownHandler()
    {
        $error = error_get_last();

        if (!empty($error) && isset($error['type'])) {

            $context = $error;
            unset($context['message']);
            
            switch ($error['type']) {
                case E_ERROR:
                case E_PARSE:
                case E_CORE_ERROR:
                case E_COMPILE_ERROR:
                case E_USER_ERROR:
                    $this->critical($error['message'], $context);
                    break;
                
                case E_USER_WARNING:
                case E_WARNING:
                case E_STRICT:
                case E_RECOVERABLE_ERROR:
                    $this->error($error['message'], $context);
                    break;
                
                default:
            }
        }
    }
}
