<?php

declare(strict_types=1);

namespace Mirror;

use Mirror\Console\Console;
use Mirror\Store\Cache;
use Mirror\Store\Channel;
use Mirror\Store\DataProvider;
use Mirror\Sync\SyncComposerPhar;
use Mirror\Sync\SyncDists;
use Mirror\Sync\SyncPackagesJsonFile;
use Mirror\Sync\SyncPackagesV1;
use Mirror\Sync\SyncPackagesV2;
use Mirror\Sync\SyncProviders;
use Mirror\Sync\SyncStatus;
use Mirror\Sync\SyncV2;
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
        if (($config = $input->getOption('config')) === null) {
            $config = WORKING_DIR . '/config.php';
        }
        if (!file_exists($config)) {
            Console::error("config file `{$config}` not exist.");
            return 1;
        }
        $json = include_once $config;
        if ($json === null) {
            Console::error('config file is not a valid object.');
            return 1;
        }
        if (!DataProvider::validateConfig($json)) {
            return 1;
        }
        define('TARGET_DIR', $json['target_dir']);
        global $_conf;
        $_conf = $json;

        pcntl_signal(SIGINT, function () {
            Console::error('Interrupted by user.');
            Console::info('Saving cache and channel ...');
            Cache::set('_sync_status', 'stop');
            $remain = [];
            foreach (Channel::getAll() as $name => $channel) {
                while (!$channel->isEmpty()) {
                    $remain[$name][] = $channel->pop();
                }
            }
            Cache::set('_channels', $remain);
            Cache::save();
            posix_kill(posix_getpid(), SIGTERM);
        });

        // 同步开始，先读缓存
        Cache::load();

        // 启动一个终端的状态监控条

        // 同步 composer.phar
        Cache::append('coroutine_id', go([SyncComposerPhar::getInstance(), 'run']));
        // 同步 packages.json
        Cache::append('coroutine_id', go([SyncPackagesJsonFile::getInstance(), 'run']));
        // 同步 Meta for v2
        Cache::append('coroutine_id', go([SyncV2::getInstance(), 'run']));
        // 同步 status.json
        Cache::append('coroutine_id', go([SyncStatus::getInstance(), 'run']));

        for ($i = 0; $i < 30; ++$i) {
            Cache::append('coroutine_id', go([SyncProviders::getInstance(), 'run']));
        }
        for ($i = 0; $i < 30; ++$i) {
            Cache::append('coroutine_id', go([SyncPackagesV1::getInstance(), 'run']));
        }
        for ($i = 0; $i < 30; ++$i) {
            Cache::append('coroutine_id', go([SyncPackagesV2::getInstance(), 'run']));
        }
        for ($i = 0; $i < 30; ++$i) {
            Cache::append('coroutine_id', go([SyncDists::getInstance(), 'run']));
        }

        // 同步结束
        Event::wait();
        Cache::save();
        return 0;
    }
}
