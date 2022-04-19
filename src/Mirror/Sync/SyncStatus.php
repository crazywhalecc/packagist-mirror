<?php

declare(strict_types=1);

namespace Mirror\Sync;

use Exception;
use Mirror\Console\Console;
use Mirror\Singleton;
use Mirror\Store\Cache;
use Mirror\Store\Channel;
use Mirror\Store\DataProvider;
use Swoole\Coroutine;

class SyncStatus extends SyncBase
{
    use Singleton;

    /**
     * @throws Exception
     */
    public function sync()
    {
        Console::verbose('Start syncing ' . Console::setColor('status', 'gold'));

        while (true) {
            if (Cache::get('_sync_status') === 'stop') {
                break;
            }
            Console::debug('Next status syncing time: ' . date('H:i:s', time() + 5));
            Coroutine::sleep(5);
            $status = [];
            $content = [];

            // 如果变量为空字符串，则可能是其他协程还没到下一个循环
            $packagist_last_modified = Cache::get('packagist_last_modified');
            if ($packagist_last_modified === null) {
                continue;
            }

            $date_time = Cache::get('last_update_time_key');
            if ($date_time === null) {
                continue;
            }

            $packagist_last = $packagist_last_modified;
            $packagist_date_time = date('Y-m-d H:i:s', strtotime($packagist_last));

            $composer_last = $date_time;
            $packagist_last = $packagist_date_time;

            $interval = intval(strtotime($composer_last) - strtotime($packagist_last));
            $status['Delayed'] = 0;
            $status['Interval'] = $interval;
            if ($interval < 0) {
                $status['Title'] = 'Delayed ' . -$interval . ' seconds, waiting for updates...';
                $status['Delayed'] = -$interval;
                if (-$interval >= 600) {
                    $status['ShouldReportDelay'] = true;
                }
            } else {
                $status['Title'] = "Synchronized within {$interval} Seconds!";
                $status['ShouldReportDelay'] = false;
            }

            $content['Last_Update'] = [
                'Composer' => $date_time,
                'Packagist' => $packagist_date_time,
            ];

            // Queue
            $content['Queue'] = [
                'Providers' => Channel::get('provider_queue')->length(),
                'Packages' => Channel::get('package_p1_queue')->length() + Channel::get('package_v2_queue')->length(),
                'Dists' => Channel::get('dist_queue')->length(),
                'DistsRetry' => Cache::getCount('dist_queue_retry'),
            ];

            // Statistics
            $content['Statistics'] = [
                'Dists_Available' => Cache::getCount('dist_set'),
                'Dists_Failed' => $this->countSuffix('dist_set', 'failed'),
                'Dists_403' => $this->countSuffix('dist_set', '403'),
                'Dists_410' => $this->countSuffix('dist_set', '410'),
                'Dists_404' => $this->countSuffix('dist_set', '404'),
                'Dists_500' => $this->countSuffix('dist_set', '500'),
                'Dists_502' => $this->countSuffix('dist_set', '502'),
                'Dists_Meta_Missing' => Cache::getCount('dists_no_meta_key'),
                'Packages' => Cache::getCount('package_v1_set'),
                'Packages_No_Data' => Cache::getCount('packages_no_data'),
                'Providers' => Cache::getCount('provider_set'),
                'Versions' => Cache::getCount('versions_set'),
            ];

            // Today Updated
            $content['Today_Updated'] = [
                'Dists' => Cache::getCount('dist_set.' . date('Y-m-d')),
                'Packages' => Cache::getCount('package_v1_set.' . date('Y-m-d')),
                'PackagesHashFile' => Cache::getCount('package_v1_set_hash.' . date('Y-m-d')),
                'ProvidersHashFile' => Cache::getCount('provider_set.' . date('Y-m-d')),
                'Versions' => Cache::getCount('versions_set.' . date('Y-m-d')),
            ];

            $status['Content'] = $content;
            $status['CacheSeconds'] = 30;

            $status['UpdateAt'] = date('Y-m-d H:i:s');
            $status_json = json_encode($status);
            DataProvider::writeRawFile('status.json', $status_json);
        }
    }

    private function countSuffix($key, $suffix): int
    {
        $key .= '-' . $suffix;
        return Cache::getCount($key);
    }
}
