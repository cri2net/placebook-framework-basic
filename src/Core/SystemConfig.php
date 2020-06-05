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
     * Инициализация конфига (чтение из файла)
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
     * Перезагружает конфиг из файла. Полезно при изменении конфига
     * @return void
     */
    public static function reload()
    {
        self::$config = null;
        self::init();
    }

    /**
     * Получаем значение из глобальных настроек
     * @param  string $name    Ключ в конфиге. Путь к более детальному конфигу можно делить через точку "."
     *                         Например, lang или auth.google.clientId
     * @param  mixed $default  Значение по умолчанию, если в конфиге запрашиваемое значение не найдено
     * @return mixed
     */
    public static function get($name, $default)
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
     * Задаём значение в глобальных настройках
     * @param  string $name          Ключ в конфиге. Путь к более детальному конфигу можно делить через точку "."
     *                               Например, lang или auth.google.clientId
     * @param  mixed   $value        Значение
     * @param  boolean $save_to_file Нужно ли сохранять новое значение в файл (на постоянное хранение).
     *                               По умолчанию true. Если указать false,
     *                               то конфиг изменится только до конца выполнения скрипта. OPTIONAL
     * @return mixed
     */
    public static function set($name, $value, $save_to_file = true)
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
        $new_json = json_encode($conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($save_to_file) {
            file_put_contents(self::$configPath, $new_json);
            self::reload();
        } else {
            self::$config = json_decode($new_json);
        }

        return true;
    }
}
