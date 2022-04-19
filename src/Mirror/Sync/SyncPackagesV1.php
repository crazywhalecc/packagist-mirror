<?php

declare(strict_types=1);

namespace Mirror\Sync;

use Mirror\Console\Console;
use Mirror\Singleton;
use Mirror\Store\Cache;
use Mirror\Store\Channel;
use Mirror\Store\DataProvider;
use Swoole\Coroutine;
use ZM\Requests\ZMRequest;

class SyncPackagesV1 extends SyncBase
{
    use Singleton;

    public function sync()
    {
        Console::verbose('Start syncing packages V1 ...');
        while (true) {
            if (Cache::get('_sync_status') === 'stop') {
                break;
            }
            $job = Channel::get('package_p1_queue')->pop();
            if ($job) {
                $this->syncPackage(json_decode($job, true));
            } else {
                sleep(1);
            }
        }
    }

    private function syncPackage($job)
    {
        if (DataProvider::rawFileExists($job['path'])) {
            Console::info('Sync packages v1 ignored exists: ' . $job['path']);
            return;
        }
        $content = ZMRequest::get(conf('repo_url') . $job['path']);
        if ($content === false) {
            Console::warning('Packages V1 ' . $job['path'] . ' sync failed');
            return;
        }

        if (($sum = hash('sha256', $content)) != $job['hash']) {
            Console::warning('Packages V1 ' . $job['path'] . ' sync failed, hash not match');
            Console::warning('Original: ' . $job['hash'] . ', Current: ' . $sum);
            return;
        }
        $co = Coroutine::stats()['coroutine_num'];
        $obj = Channel::get('package_p1_queue')->stats();
        $dist = Channel::get('dist_queue')->stats();
        $v2 = Channel::get('package_v2_queue')->stats();
        echo "\r";
        echo "[p1_queue{$obj['queue_num']}]";
        echo "\t[Co{$co}]";
        echo "\t[dist_queue{$dist['queue_num']}]";
        echo "\t[v2_queue{$v2['queue_num']}]";

        // Put to file
        DataProvider::writeRawFile($job['path'], $content);

        // Json decode
        $response = json_decode($content, true);
        if ($response === null) {
            return;
        }

        Cache::appendKey('package_v1_set', $job['key'], $job['hash']);
        foreach ($response['packages'] as $package_name => $versions) {
            foreach ($versions as $version_name => $package_version) {
                $dist_name = $package_name . '/' . $version_name;

                $dist = $package_version['dist'];
                if (!isset($dist['reference'])) {
                    Cache::unsetKey('package_v1_set', $job['key']);
                    return;
                }
                $path = 'dists/' . $package_name . '/' . $dist['reference'] . '.' . $dist['type'];

                $exist = Cache::inArray($path, 'dist_set');
                if (!$exist) {
                    $dist = $this->newDist($path, $dist['url']);
                    Channel::get('dist_queue')->push(json_encode($dist));
                    Cache::append('versions_set', $dist_name);
                    Cache::append('versions_set.' . date('Y-m-d'), $dist_name);
                }
            }
        }

        Cache::append('package_v1_set_hash.' . date('Y-m-d'), $job['path']);
    }
}
