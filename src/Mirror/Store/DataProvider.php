<?php

declare(strict_types=1);

namespace Mirror\Store;

use Exception;
use Mirror\Console\Console;

class DataProvider
{
    private static $tmp_path = '/tmp/packagist-mirror';

    /**
     * 将变量保存在zm_data下的数据目录，传入数组
     *
     * @param  mixed     $file_array
     * @return false|int 返回文件大小或false
     */
    public static function saveToJson(string $filename, $file_array)
    {
        return file_put_contents(self::$tmp_path . '/' . $filename . '.json', json_encode($file_array, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * 从json加载变量到内存
     *
     * @param  string     $filename 文件名
     * @return null|mixed 返回文件内容数据或null
     */
    public static function loadFromJson(string $filename)
    {
        if (file_exists(self::$tmp_path . '/' . $filename . '.json')) {
            return json_decode(file_get_contents(self::$tmp_path . '/' . $filename . '.json'), true);
        }
        return null;
    }

    public static function getTmpPath(): string
    {
        return self::$tmp_path;
    }

    public static function validateConfig($json): bool
    {
        try {
            self::assertValid($json, 'target_dir');
            self::assertValid($json, 'github_token');
        } catch (Exception $e) {
            Console::error($e->getMessage());
            return false;
        }
        return true;
    }

    public static function writeRawFile($filename, $content)
    {
        $filename = TARGET_DIR . '/' . $filename;
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            Console::info("Directory {$dir} not exists, create it.");
            mkdir($dir, 0777, true);
        }
        Console::info("Write file {$filename}");
        if (file_put_contents($filename, $content) === false) {
            throw new Exception("Write file {$filename} failed.");
        }
    }

    /**
     * @param  mixed     $obj
     * @param  mixed     $key
     * @throws Exception
     */
    private static function assertValid($obj, $key): void
    {
        if (!isset($obj[$key])) {
            throw new Exception('Config `' . $key . '` not set');
        }
        if ($obj[$key] === '') {
            throw new Exception('Config `' . $key . '` is empty');
        }
    }
}
