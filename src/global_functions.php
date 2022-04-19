<?php

declare(strict_types=1);

function conf($name, $default = null)
{
    global $_conf;
    return $_conf[$name] ?? $default;
}
