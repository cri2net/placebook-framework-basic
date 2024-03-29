<?php

namespace Placebook\Framework\Core;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class Logger implements LoggerInterface
{
    /**
     * Default API URL
     * @var string
     */
    public $api_url = 'https://logger.tinycdn.cloud/api/';

    public $addedLogId = null;

    /**
     * Upload file to logs
     * @var bool
     */
    public $upload_file = false;

    /**
     * API token
     * @var string
     */
    public $api_token = null;

    /**
     * Constructor
     * @param string $api_token API token
     */
    public function __construct(string $api_token)
    {
        $this->api_token = $api_token;
    }

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
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
     * @return void
     */
    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
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
     * @return void
     */
    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Create a log via API
     * @param  string $level   Log Level
     * @param  string $message Message
     * @param  array  $context Array with additional data
     * @return void
     */
    public function log($level, $message, array $context = []): void
    {
        if (empty($this->api_token)) {
            // can't send without API token
            return;
        }

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
            $this->addedLogId = $response['data']['logAdd'] ?? 0;
        } catch (Exception $e) {
        }
    }

    public function shutdownHandler()
    {
        $error = error_get_last();

        if (!empty($error) && isset($error['type'])) {

            $context = $error;
            unset($context['message']);

            if  ($this->upload_file && isset($context['file']) && is_readable($context['file'])) {
                $context['file_content'] = file_get_contents($context['file']);
            }

            $context['type'] = 'php_error';

            $context['headers'] = Http::getAllHeaders();
            $context['_SESSION'] = @$_SESSION;
            $context['_GET'] = $_GET;
            $context['_POST'] = $_POST;
            $context['_FILES'] = $_FILES;
            $context['_SERVER'] = $_SERVER;

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
