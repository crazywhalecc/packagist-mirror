<?php

declare(strict_types=1);

namespace Mirror;

use Mirror\Console\Console;
use Mirror\Store\Cache;
use Mirror\Store\DataProvider;
use Mirror\Sync\SyncComposerPhar;
use Swoole\Event;
use Symfony\Component\Console\Input\InputInterface;

class MirrorEntry
{
    /**
     * @internal
     * @var array
     */
    public static $mirror_conf = [];

    public static function execute(InputInterface $input): int
    {
        if (($config = $input->getOption('config')) === false) {
            $config = WORKING_DIR . '/config.json';
        }
        if (!file_exists($config)) {
            Console::error("config file `{$config}` not exist.");
            return 1;
        }
        $json = json_decode(file_get_contents($config), true);
        if ($json === null) {
            Console::error('config file is not a valid json object.');
            return 1;
        }
        if (!DataProvider::validateConfig($json)) {
            return 1;
        }
        define('TARGET_DIR', $json['target_dir']);
        global $_conf;
        $_conf = $json;

        // 同步开始，先读缓存
        Cache::load();
        go([SyncComposerPhar::class, 'sync']);

        // 同步结束
        Event::wait();
        Cache::save();
        return 0;
    }
}
