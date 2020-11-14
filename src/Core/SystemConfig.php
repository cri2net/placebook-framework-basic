<?php

namespace Placebook\Framework\Core;

use Exception;
use cri2net\php_singleton\Singleton;

class SystemConfig
{
    use Singleton;

    public  static $configPath;
    private static $config;

    private function __construct()
    {
        $this->init();
    }

    /**
     * Initialization of a config (reading from a file)
     * @return void
     */
    private static function init()
    {
        if (self::$config !== null) {
            return;
        }

        if (empty(self::$configPath)) {
            throw new Exception('Please set path to json config file in SystemConfig::$configPath');
        }

        self::$config = [];
        
        if (file_exists(self::$configPath)) {
            self::$config = @json_decode(file_get_contents(self::$configPath));
        }
    }

    /**
     * Reloads the config from the file. Useful when changing the config.
     * @return void
     */
    public static function reload()
    {
        self::$config = null;
        self::init();
    }

    /**
     * Get the value from the global settings.
     * @param  string $name    The key in the config. The path to a more detailed config can be divided through a dot "."
     *                         For example, lang or auth.google.clientId
     * @param  mixed $default  Default value, if the requested value is not found in the config
     * @return mixed
     */
    public static function get(string $name, $default)
    {
        self::getInstance();

        $path = explode('.', $name);
        $conf = self::$config;

        for ($i=0; $i < count($path) - 1; $i++) {
            if (isset($conf->{$path[$i]})) {
                $conf = $conf->{$path[$i]};
            } else {
                return $default;
            }
        }
        
        $last = end($path);
        if (isset($conf->$last)) {
            return $conf->$last;
        }

        return $default;
    }

    /**
     * Set the value in global settings
     * @param  string $name          The key in the config. The path to a more detailed config can be divided through a dot "."
     *                               For example, lang or auth.google.clientId
     * @param  mixed   $value        Value
     * @param  boolean $save_to_file Do I need to save the new value to a file (for permanent storage).
     *                               By default is true. If set to false,
     *                               then the config will only change until the end of the script. OPTIONAL
     * @return mixed
     */
    public static function set(string $name, $value, bool $save_to_file = true)
    {
        self::getInstance();
        $conf = $array = json_decode(json_encode(self::$config), true);

        $new = $value;
        $path = explode('.', $name);

        $length = strlen($name);
        for ($i=count($path) - 1; $i >= 0; $i--) {

            $length -= strlen($path[$i]);
            $tmp_name = substr($name, 0, $length);
            
            if (strlen(rtrim($tmp_name, '.')) == strlen($tmp_name) - 1) {
                
                $tmp_name = rtrim($tmp_name, '.');
                $length--;
            }

            $tmp_val = (array)self::get($tmp_name, []);
            $tmp_val[$path[$i]] = $new;
            $new = $tmp_val;
        }

        $conf = array_merge($conf, $new);
        $new_json = json_encode($conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($save_to_file) {
            file_put_contents(self::$configPath, $new_json);
            self::reload();
        } else {
            self::$config = json_decode($new_json);
        }

        return true;
    }
}
