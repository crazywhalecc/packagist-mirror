<?php

declare(strict_types=1);


function conf($name)
{
    global $_conf;
    return $_conf[$name] ?? null;
}
