<?php

declare(strict_types=1);

namespace Mirror\Store;

use Mirror\Console\Console;

class Channel
{
    private static $channel = [];

    public static function get($channel_name)
    {
        if (!isset(self::$channel[$channel_name])) {
            Console::debug('No channel ' . $channel_name . ', creating...');
            self::$channel[$channel_name] = new \Swoole\Coroutine\Channel(65536 * 8);
        }
        return self::$channel[$channel_name];
    }

    /**
     * @return \Swoole\Coroutine\Channel[]
     */
    public static function getAll(): array
    {
        return self::$channel;
    }
}
