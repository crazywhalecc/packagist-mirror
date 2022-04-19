<?php

declare(strict_types=1);

namespace Mirror\Sync;

use Mirror\Console\Console;
use Mirror\Singleton;
use Mirror\Store\Cache;
use Mirror\Store\Channel;
use Mirror\Store\DataProvider;
use ZM\Requests\ZMRequest;

class SyncProviders extends SyncBase
{
    use Singleton;

    public function sync()
    {
        Console::verbose('Start syncing providers...');
        while (true) {
            if (Cache::get('_sync_status') === 'stop') {
                break;
            }
            $job = Channel::get('provider_queue')->pop();
            if ($job) {
                $this->syncProvider(json_decode($job, true));
            } else {
                sleep(1);
            }
        }
    }

    private function syncProvider($job)
    {
        $content = ZMRequest::get(conf('repo_url') . $job['path']);
        if ($content === false) {
            Console::warning('Provider ' . $job['path'] . ' sync failed');
            return;
        }
        if (($sum = hash('sha256', $content)) != $job['hash']) {
            Console::warning('Provider ' . $job['path'] . ' sync failed, hash not match');
            Console::warning('Original: ' . $job['hash'] . ', Current: ' . $sum);
            $json_p2 = json_encode($job);
            Channel::get('provider_queue')->push($json_p2);
            return;
        }

        // Put to file
        DataProvider::writeRawFile($job['path'], $content);
        Console::verbose('Provider ' . $job['path'] . ' sync success');
        Cache::appendKey('provider_set', $job['key'], $job['hash']);

        $providers_root = json_decode($content, true);
        if ($providers_root === null) {
            return;
        }

        foreach ($providers_root['providers'] as $package_name => $hashers) {
            $sha256 = $hashers['sha256'];
            $value = Cache::get('package_v1_set')['package_name'] ?? null;
            if ($value !== $sha256) {
                Console::verbose(sprintf('Dispatch packages(%s) to %s', $package_name, 'package_p1_queue'));
                $task = $this->newTask($package_name, 'p/' . $package_name . '$' . $sha256 . '.json', $sha256);
                $json_p1 = json_encode($task);
                Channel::get('package_p1_queue')->push($json_p1);
                Cache::append('package_v1_set.' . date('Y-m-d'), $package_name);
            }
        }
    }
}
