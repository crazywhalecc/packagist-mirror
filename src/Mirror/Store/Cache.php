<?php

declare(strict_types=1);

namespace Mirror\Store;

use Mirror\Console\Console;

class Cache
{
    private static $cache = [];

    public static function get($key, $default = null)
    {
        return self::$cache[$key] ?? $default;
    }

    public static function getCount($key): int
    {
        return count(self::$cache[$key] ?? []);
    }

    public static function set($key, $value)
    {
        self::$cache[$key] = $value;
    }

    public static function append($key, $value)
    {
        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = [];
        }
        self::$cache[$key][] = $value;
    }

    public static function save()
    {
        Console::info('Saved cache.');
        file_put_contents('/tmp/' . md5(getcwd()) . '.json', serialize(self::$cache));
    }

    public static function load()
    {
        if (file_exists('/tmp/' . md5(getcwd()) . '.json')) {
            Console::info('Loading cache...');
            self::$cache = unserialize(file_get_contents('/tmp/' . md5(getcwd()) . '.json'));
            if (isset(self::$cache['_channels'])) {
                unset(self::$cache['_channels']['status_bar']);
                go(function () {
                    foreach (self::$cache['_channels'] as $name => $channels) {
                        foreach ($channels as $channel) {
                            Channel::get($name)->push($channel);
                        }
                    }
                    self::unset('_channels');
                });
            }
        }
    }

    public static function appendKey(string $string, $key, $value)
    {
        if (!isset(self::$cache[$string])) {
            self::$cache[$string] = [];
        }
        self::$cache[$string][$key] = $value;
    }

    public static function inArray(string $value, string $key): bool
    {
        return in_array($value, self::$cache[$key] ?? []);
    }

    public static function unsetKey(string $string, $key)
    {
        unset(self::$cache[$string][$key]);
    }

    public static function unset(string $string)
    {
        unset(self::$cache[$string]);
    }
}
