<?php

declare(strict_types=1);

namespace Mirror\Sync;

use Mirror\Console\Console;
use Mirror\Singleton;
use Mirror\Store\Cache;
use Mirror\Store\Channel;
use Mirror\Store\DataProvider;
use Mirror\Store\SpinLock;
use Swoole\Coroutine;
use ZM\Requests\ZMRequest;

class SyncPackagesJsonFile extends SyncBase
{
    use Singleton;

    public function sync()
    {
        Console::verbose('Synchronizing packages json file');

        $packagist = [
            'repo_url' => conf('repo_url'),
            'api_url' => conf('api_url'),
            'user_agent' => conf('user_agent', 'Chrome'),
        ];
        $mirror = [
            'provider_url' => conf('provider_url'),
            'dist_url' => conf('dist_url'),
            'api_iteration_interval' => conf('api_iteration_interval'),
        ];
        if (!$this->getPackagesJson($packagist, $json, $last_modified)) {
            return;
        }
        Console::success('Packages json file synchronized');
        Cache::set('packagist_last_modified_key', $last_modified);

        // 计算packages.json的sha256并比较
        $sum = hash('sha256', $json);
        if ($sum === Cache::get('packagist_json_sum_key')) {
            Console::info('Packages json file is not changed');
            return;
        }

        // JSON 解析
        $packages_json = json_decode($json, true);
        if ($packages_json === null) {
            Console::error('Packages json file is not valid json');
            return;
        }

        // 分发 provider
        foreach ($packages_json['provider-includes'] as $k => $v) {
            $provider_hash = $v['sha256'];
            $provider_path = str_replace('%hash%', $provider_hash, $k);

            // 从缓存获取
            $value = Cache::get('provider_set')[$k] ?? null;
            if ($value !== $provider_hash) {
                Console::verbose('Dispatch providers: ' . $k);
                $task = $this->newTask($k, $provider_path, $provider_hash);
                $json_p2 = json_encode($task);
                Channel::get('provider_queue')->push($json_p2);

                SpinLock::transaction('provider_set', function () use ($k, $provider_hash) {
                    $set = Cache::get('provider_set.' . date('Y-m-d'), []);
                    $set[$k] = $provider_hash;
                    Cache::set('provider_set.' . date('Y-m-d'), $set);
                });
            }
        }

        while (true) {
            $dist_queue_size = Channel::get('dist_queue')->length();
            $provider_queue_size = Channel::get('provider_queue')->length();
            $package_p1_queue_size = Channel::get('package_p1_queue')->length();
            $package_v2_queue_size = Channel::get('package_v2_queue')->length();
            $left = $dist_queue_size + $provider_queue_size + $package_p1_queue_size + $package_v2_queue_size;
            if ($left === 0) {
                break;
            }
            Console::verbose(sprintf('Processing %d, Check again in 1s.', $left));
            Coroutine::sleep(1);
        }

        // 更新 packages.json
        $new_packages_json = json_decode($json, true);
        $last_update_time = date('Y-m-d H:i:s');
        Cache::set('last_update_time_key', $last_update_time);

        // Ignore the upstream info，虽然我也不知道为什么
        $new_packages_json['info'] = '';
        $new_packages_json['last-update'] = $last_update_time;
        $new_packages_json['metadata-url'] = $mirror['provider_url'] . 'p2/%package%.json';
        $new_packages_json['providers-url'] = $mirror['provider_url'] . 'p/%package%$%hash%.json';
        $new_packages_json['mirrors'] = [[
            'dist-url' => $mirror['dist_url'] . 'dists/%package%/%reference%.%type%',
            'preferred' => true,
        ]];

        $packages_json_new = json_encode($new_packages_json);

        // 更新 packages.json
        DataProvider::writeRawFile('packages.json', $packages_json_new);
        Cache::set('packagist_json_sum_key', $sum);
        Console::success('[Sync#2] Packages json file updated');
    }

    protected function getPackagesJson(array $packagist, &$json, &$last_modified): bool
    {
        $url = $packagist['repo_url'] . 'packages.json';
        $headers = [
            'User-Agent' => $packagist['user_agent'],
        ];
        $client = ZMRequest::get($url, $headers, [], false);
        if ($client->statusCode !== 200) {
            Console::error('Packages json file download failed with code ' . $client->statusCode . ': ' . $client->errMsg);
            return false;
        }
        $last_modified = strtotime($client->getHeaders()['last-modified'] ?? 'Mon, 18 Apr 2022 17:47:48 GMT');

        $json = $client->getBody();
        return true;
    }
}
