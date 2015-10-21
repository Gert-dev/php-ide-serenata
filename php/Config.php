<?php

namespace PhpIntegrator;

/**
 * Contains all configuration options
 */
class Config
{
    /**
     * Configuration container
     * @var array
     */
    private static $config = array();

    /**
     * Returns a key
     * @param  string $key     Key of the value required
     * @param  mixed  $default Default value if not found
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        return isset(self::$config[$key]) ? self::$config[$key] : $default;
    }

    /**
     * Sets a new value in the configuration
     * @param string $key   Identifier
     * @param mixed  $value Configuration value
     */
    public static function set($key, $value)
    {
        self::$config[$key] = $value;
    }
}

?>
