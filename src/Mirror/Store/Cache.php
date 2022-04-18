<?php

declare(strict_types=1);

namespace Mirror\Store;

class Cache
{
    private static $cache = [];

    public static function get($key, $default = null)
    {
        return self::$cache[$key] ?? $default;
    }

    public static function set($key, $value)
    {
        self::$cache[$key] = $value;
    }

    public static function save()
    {
        file_put_contents('/tmp/' . md5(getcwd()) . '.json', serialize(self::$cache));
    }

    public static function load()
    {
        if (file_exists('/tmp/' . md5(getcwd()) . '.json')) {
            self::$cache = unserialize(file_get_contents('/tmp/' . md5(getcwd()) . '.json'));
        }
    }
}
