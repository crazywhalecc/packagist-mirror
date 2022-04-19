<?php

declare(strict_types=1);

namespace Mirror\Sync;

use Mirror\Console\Console;
use Mirror\Singleton;
use Mirror\Store\Cache;
use Mirror\Store\Channel;
use ZM\Requests\ZMRequest;

class SyncV2 extends SyncBase
{
    use Singleton;

    private $packagist;

    public function sync()
    {
        Console::verbose('SyncV2 running...');

        $this->packagist = [
            'repo_url' => conf('repo_url'),
            'api_url' => conf('api_url'),
            'user_agent' => conf('user_agent', 'Chrome'),
        ];

        $last_ts = Cache::get('v2_last_update_time_key');
        if (empty($last_ts)) {
            $last_ts = $this->getInitTimestamp();
            if ($last_ts === false) {
                return;
            }
            if (!$this->syncAll()) {
                return;
            }
        }

        $changes = $this->getMetadataChanges($last_ts);
        if ($changes === false) {
            return;
        }

        // Dispatch changes
        $ts_api = $changes['timestamp'];
        if ($ts_api === $last_ts) {
            Console::verbose('No changes in metadata.');
            return;
        }
        foreach ($changes['actions'] as $v) {
            $package_name = $v['package'];
            $update_time = $v['time'];

            $stored_update_time = Cache::get('package_v2_set', [])[$package_name] ?? null;
            if ($stored_update_time === null) {
                Channel::get('package_v2_queue')->push(json_encode($v));
                continue;
            }

            if ($stored_update_time != $update_time) {
                Channel::get('package_v2_queue')->push(json_encode($v));
            }
        }

        Cache::set('v2_last_update_time_key', $ts_api);
    }

    private function syncAll(): bool
    {
        Console::verbose('SyncV2: sync all packages...');
        $content = $this->getAllPackages();
        if ($content === false) {
            Console::error('Failed to get all packages.');
            return false;
        }
        $list = json_decode($content, true);
        if ($list === null) {
            Console::error('Failed to decode all packages.');
            return false;
        }
        Console::success('[Sync#3] SyncV2: sync all packages done. Count: ' . count($list['packageNames']));
        foreach ($list['packageNames'] as $package_name) {
            Channel::get('package_v2_queue')->push(json_encode($this->newChangeAction('update', $package_name, 0)));
            Channel::get('package_v2_queue')->push(json_encode($this->newChangeAction('update', $package_name . '~dev', 0)));
        }
        return true;
    }

    private function getInitTimestamp()
    {
        Console::verbose('Getting init timestamp...');
        $url = $this->packagist['api_url'] . 'metadata/changes.json';
        $client = ZMRequest::get($url, ['User-Agent' => $this->packagist['user_agent']], [], false);
        $changes_json = json_decode($client->getBody(), true);
        if ($changes_json === null) {
            Console::error('V2 metadata/changes.json file is not a valid json file');
            return false;
        }
        Console::verbose('Init timestamp: ' . $changes_json['timestamp']);
        return $changes_json['timestamp'];
    }

    private function getMetadataChanges($last_ts)
    {
        Console::verbose('Getting metadata changes...');
        $url = $this->packagist['api_url'] . 'metadata/changes.json?since=' . $last_ts;
        $client = ZMRequest::get($url, ['User-Agent' => $this->packagist['user_agent']], [], false);
        $changes_json = json_decode($client->getBody(), true);
        if ($changes_json === null) {
            Console::error('V2 metadata/changes.json file is not a valid json file');
            return false;
        }
        return $changes_json;
    }

    private function getAllPackages()
    {
        Console::verbose('Getting all packages...');
        $url = $this->packagist['api_url'] . 'packages/list.json';
        $obj = ZMRequest::get($url);
        Console::verbose('All packages done');
        return $obj;
    }
}
