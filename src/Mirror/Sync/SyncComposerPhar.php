<?php

declare(strict_types=1);

namespace Mirror\Sync;

use Exception;
use Mirror\Console\Console;
use Mirror\Singleton;
use Mirror\Store\Cache;
use Mirror\Store\DataProvider;
use Swoole\ExitException;
use ZM\Requests\ZMRequest;

class SyncComposerPhar extends SyncBase
{
    use Singleton;

    /**
     * @throws Exception|ExitException
     */
    public function sync()
    {
        Console::info('Synchronizing composer.phar');

        // 获取最新版的composer.phar版本
        $version = ZMRequest::get('https://getcomposer.org/versions');
        if ($version === false) {
            Console::error('Failed to get the latest version of composer.phar');
            return;
        }
        if (($versions = json_decode($version, true)) === null) {
            Console::error('Failed to decode the versions for composer.phar');
            return;
        }

        // 判断 composer.phar 版本是不是最新的
        $stable = $versions['stable'][0];
        $local_stable_version = Cache::get('local_stable_version');
        if ($local_stable_version === $stable['version']) {
            Console::info('The remote version is equals with local version, no need to anything');
            return;
        }

        // 下载 Composer.phar
        Console::info('Downloading composer.phar now');
        $phar = ZMRequest::get('https://getcomposer.org' . $stable['path']);
        if ($phar === false) {
            Console::error('Failed to download composer.phar');
            return;
        }

        // 下载 composer.phar.sig 签名文件
        $composer_phar_sig = ZMRequest::get('https://getcomposer.org' . $stable['path'] . '.sig');
        if ($composer_phar_sig === false) {
            Console::error('Failed to download composer.phar.sig');
            return;
        }

        // 写入文件
        DataProvider::writeRawFile('versions', $version); // 写入 versions
        DataProvider::writeRawFile('composer.phar', $phar); // 写入 composer.phar
        DataProvider::writeRawFile('download/' . $stable['version'] . '/composer.phar', $phar);
        DataProvider::writeRawFile('composer.phar.sig', $composer_phar_sig); // 写入 composer.phar.sig
        DataProvider::writeRawFile('download/' . $stable['version'] . 'composer.phar.sig', $composer_phar_sig);

        Console::success('[Sync#1] Synchronized composer.phar successfully');

        Cache::set('local_stable_version', $stable['version']);
    }
}
