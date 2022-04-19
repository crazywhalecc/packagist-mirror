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

class SyncPackagesV2 extends SyncBase
{
    use Singleton;

    public function sync()
    {
        Console::verbose('Start syncing packages V2 ...');
        while (true) {
            if (Cache::get('_sync_status') === 'stop') {
                break;
            }
            $job = Channel::get('package_v2_queue')->pop();
            if ($job) {
                $this->doAction(json_decode($job, true));
            // $this->syncPackage(json_decode($job));
            } else {
                Coroutine::sleep(1);
            }
        }
    }

    private function doAction($job)
    {
        $action_type = $job['type'];

        switch ($action_type) {
            case 'update':
                $this->updatePackageV2($job);
                break;
            case 'delete':
                $this->deletePackageV2($job);
                break;
            default:
                Console::error('Unsupported action type: ' . $action_type);
                break;
        }
    }

    private function updatePackageV2($job)
    {
        $package_name = $job['package'];
        $content = ZMRequest::get(conf('api_url') . 'p2/' . $package_name . '.json');
        if ($content === false) {
            Console::error('Failed to get package: ' . $package_name);
            return;
        }

        // JSON Decode
        $package_json = json_decode($content, true);
        if ($package_json === null) {
            Console::error('Failed to decode package: ' . $package_name);
            return;
        }
        if (!isset($package_json['minified'])) {
            Console::error('Package field not found: minified');
            return;
        }

        // Put to file
        DataProvider::writeRawFile('p2/' . $package_name . '.json', $content);
        Cache::appendKey('package_v2_set', $package_name, $job['time']);
    }

    private function deletePackageV2($job)
    {
        $package_name = $job['package'];
        if (!DataProvider::deleteRawFile('p2/' . $package_name . '.json')) {
            return;
        }
        Cache::unsetKey('package_v2_set', $package_name);
    }
}
