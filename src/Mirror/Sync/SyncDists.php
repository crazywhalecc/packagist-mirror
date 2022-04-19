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

class SyncDists extends SyncBase
{
    use Singleton;

    public function sync()
    {
        Console::verbose('Fetching dist files ...');

        while (true) {
            if (Cache::get('_sync_status') === 'stop') {
                break;
            }
            $job = Channel::get('dist_queue')->pop();
            if ($job) {
                $this->uploadDist(json_decode($job, true));
            } else {
                Coroutine::sleep(1);
            }
        }
    }

    private function uploadDist($job)
    {
        $path = $job['path'];
        $url = $job['url'];

        if (empty($url)) {
            Console::error('Url is invalid');
            return;
        }

        // Count
        Cache::append('dist_set.' . date('Y-m-d'), $path);

        // File is exists
        if (DataProvider::rawFileExists($path)) {
            Console::info('Object file exists: ' . $path);
            return;
        }

        $resp = $this->getDist($url);
        if (is_int($resp)) {
            if ($resp === -2) {
                Channel::get('dist_queue')->push(json_encode($job));
            }
            return;
        }

        // Put to file
        DataProvider::writeRawFile($path, $resp);
    }

    private function getDist($url)
    {
        $headers = [
            'Authorization' => 'token ' . conf('github_token'),
            'User-Agent' => conf('user_agent'),
        ];
        $req = ZMRequest::get($url, $headers, ['timeout' => 180], false);
        if ($req->getStatusCode() === 404) {
            Console::error('Object file not found: ' . $url);
            return 404;
        }

        if ($req->getStatusCode() === 302 || $req->getStatusCode() === 301) {
            $url = $req->getHeaders()['location'];
            return $this->getDist($url);
        }

        if ($req->getStatusCode() !== 200) {
            Console::error('Request github dist failed with code ' . $req->getStatusCode() . ': ' . $url);
            return $req->getStatusCode();
        }

        return $req->getBody();
    }
}
