<?php

declare(strict_types=1);

namespace Mirror;

trait Singleton
{
    public static $static;

    public static function getInstance()
    {
        if (static::$static === null) {
            static::$static = new static();
        }
        return static::$static;
    }
}
