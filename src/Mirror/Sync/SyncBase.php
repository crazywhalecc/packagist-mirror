<?php

declare(strict_types=1);

namespace Mirror\Sync;

use Exception;
use Mirror\Console\Console;
use Swoole\ExitException;

abstract class SyncBase
{
    abstract public static function getInstance();

    abstract public function sync();

    public function run()
    {
        try {
            $this->sync();
        } catch (ExitException $e) {
            return;
        } catch (Exception $e) {
            Console::error($e->getMessage());
        }
    }

    protected function newTask($k, $provider_path, $provider_hash): array
    {
        return [
            'key' => $k,
            'path' => $provider_path,
            'hash' => $provider_hash,
        ];
    }

    protected function newDist($path, $url): array
    {
        return [
            'path' => $path,
            'url' => $url,
        ];
    }

    protected function newChangeAction($string, $package_name, $int): array
    {
        return [
            'type' => $string,
            'package' => $package_name,
            'time' => $int,
        ];
    }
}
