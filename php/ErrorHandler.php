<?php

namespace PhpIntegrator;

class ErrorHandler
{
    static private $reserve;

    /**
     * Set up error handling.
     */
    public static function register()
    {
        // Keep some room in case of memory exhaustion
        self::$reserve = str_repeat(' ', 4096);

        // Ensure nothing will be send to stdout
        ini_set('display_errors', false);
        ini_set('log_errors', false);

        // Register our handlers
        register_shutdown_function('ErrorHandler::onShutdown');
        set_error_handler('ErrorHandler::onError');
        set_exception_handler('ErrorHandler::onException');
    }

    /**
     * @throws ErrorException on any error.
     */
    public static function onError($code, $message, $file, $line, $context)
    {
        throw new \ErrorException($message, $code, 1, $file, $line);
    }

    /**
     * Display uncaught exception in JSON.
     *
     * @param \Exception $exception
     */
    public static function onException(\Exception $exception)
    {
        die(json_encode(['error' => [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]]));
    }

    /**
     * Display any fatal error in JSON.
     */
    public static function onShutdown()
    {
        self::$reserve = null;

        $error = error_get_last();

        if ($error !== null) {
            die(json_encode(['error' => $error]));
        }
    }
}
