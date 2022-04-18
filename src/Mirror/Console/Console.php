<?php

declare(strict_types=1);

namespace Mirror\Console;

use Swoole\Atomic;
use Swoole\Coroutine;

class Console
{
    public static $theme = 'default';

    private static $info_level;

    private static $default_theme = [
        'success' => 'green',
        'info' => 'lightblue',
        'warning' => 'yellow',
        'error' => 'red',
        'verbose' => 'blue',
        'debug' => 'gray',
        'trace' => 'gray',
    ];

    private static $output_file;

    private static $theme_config = [];

    /**
     * 初始化服务器的控制台参数
     */
    public static function init(int $info_level, string $theme = 'default', array $theme_config = [])
    {
        self::$info_level = new Atomic($info_level);
        self::$theme = $theme;
        self::$theme_config = $theme_config;
    }

    public static function setOutputFile($file)
    {
        if (!file_exists($file)) {
            if (!touch($file)) {
                self::error('无法创建输出日志文件');
                return;
            }
        }
        if (!is_writable($file)) {
            self::error('无法写入日志文件');
            return;
        }
        self::$output_file = $file;
    }

    public static function setLevel(int $level)
    {
        if (self::$info_level === null) {
            self::$info_level = new Atomic($level);
        }
        self::$info_level->set($level);
    }

    public static function getLevel()
    {
        return self::$info_level->get();
    }

    public static function setColor($string, $color = '')
    {
        $string = self::stringable($string);
        switch ($color) {
            case 'black':
                return TermColor::color8(30) . $string . TermColor::RESET;
            case 'red':
                return TermColor::color8(31) . $string . TermColor::RESET;
            case 'green':
                return TermColor::color8(32) . $string . TermColor::RESET;
            case 'yellow':
                return TermColor::color8(33) . $string . TermColor::RESET;
            case 'blue':
                return TermColor::color8(34) . $string . TermColor::RESET;
            case 'pink': // I really don't know what stupid color it is.
            case 'lightpurple':
                return TermColor::color8(35) . $string . TermColor::RESET;
            case 'lightblue':
                return TermColor::color8(36) . $string . TermColor::RESET;
            case 'white':
                return TermColor::color8(37) . $string . TermColor::RESET;
            case 'gold':
                return TermColor::frontColor256(214) . $string . TermColor::RESET;
            case 'gray':
                return TermColor::frontColor256(59) . $string . TermColor::RESET;
            case 'lightlightblue':
                return TermColor::frontColor256(63) . $string . TermColor::RESET;
            case '':
                return $string;
            default:
                return TermColor::frontColor256($color) . $string . TermColor::RESET;
        }
    }

    public static function error($obj, $head = null)
    {
        if ($head === null) {
            $head = self::getHead('E');
        }
        if (self::$info_level !== null && in_array(self::$info_level->get(), [3, 4])) {
            $trace = debug_backtrace()[1] ?? ['file' => '', 'function' => ''];
            $trace = '[' . ($trace['class'] ?? '') . ':' . ($trace['function'] ?? '') . '] ';
        }
        self::log($head . ($trace ?? '') . self::stringable($obj), self::getThemeColor(__FUNCTION__));
    }

    public static function trace($color = null)
    {
        $log = "Stack trace:\n";
        $trace = debug_backtrace();
        // array_shift($trace);
        foreach ($trace as $i => $t) {
            if (!isset($t['file'])) {
                $t['file'] = 'unknown';
            }
            if (!isset($t['line'])) {
                $t['line'] = 0;
            }
            if (!isset($t['function'])) {
                $t['function'] = 'unknown';
            }
            $log .= "#{$i} {$t['file']}({$t['line']}): ";
            if (isset($t['object']) and is_object($t['object'])) {
                $log .= get_class($t['object']) . '->';
            }
            $log .= "{$t['function']}()\n";
        }
        if ($color === null) {
            $color = self::getThemeColor('trace');
        }
        self::log($log, $color);
    }

    public static function log($obj, $color = '')
    {
        if (!is_string($obj)) {
            $obj = self::stringable($obj);
        }
        if (self::$output_file !== null) {
            self::logToFile($obj);
        }
        echo self::setColor($obj, $color) . "\n";
    }

    public static function debug($msg)
    {
        if (self::$info_level !== null && self::$info_level->get() >= 4) {
            $msg = self::stringable($msg);
            $trace = debug_backtrace()[1] ?? ['file' => '', 'function' => ''];
            $trace = '[' . ($trace['class'] ?? '') . ':' . ($trace['function'] ?? '') . '] ';
            Console::log(self::getHead('D') . ($trace) . $msg, self::getThemeColor(__FUNCTION__));
        }
    }

    public static function verbose($obj, $head = null)
    {
        if ($head === null) {
            $head = self::getHead('V');
        }
        if (self::$info_level !== null && in_array(self::$info_level->get(), [4])) {
            $trace = debug_backtrace()[1] ?? ['file' => '', 'function' => ''];
            $trace = '[' . ($trace['class'] ?? '') . ':' . ($trace['function'] ?? '') . '] ';
        }
        if (self::$info_level !== null && self::$info_level->get() >= 3) {
            self::log($head . ($trace ?? '') . self::stringable($obj), self::getThemeColor(__FUNCTION__));
        }
    }

    public static function success($obj, $head = null)
    {
        if ($head === null) {
            $head = self::getHead('S');
        }
        if (self::$info_level !== null && in_array(self::$info_level->get(), [4])) {
            $trace = debug_backtrace()[1] ?? ['file' => '', 'function' => ''];
            $trace = '[' . ($trace['class'] ?? '') . ':' . ($trace['function'] ?? '') . '] ';
        }
        if (self::$info_level->get() >= 2) {
            self::log($head . ($trace ?? '') . self::stringable($obj), self::getThemeColor(__FUNCTION__));
        }
    }

    public static function info($obj, $head = null)
    {
        if ($head === null) {
            $head = self::getHead('I');
        }
        if (self::$info_level !== null && in_array(self::$info_level->get(), [4])) {
            $trace = debug_backtrace()[1] ?? ['file' => '', 'function' => ''];
            $trace = '[' . ($trace['class'] ?? '') . ':' . ($trace['function'] ?? '') . '] ';
        }
        if (self::$info_level->get() >= 2) {
            self::log($head . ($trace ?? '') . self::stringable($obj), self::getThemeColor(__FUNCTION__));
        }
    }

    public static function warning($obj, $head = null)
    {
        if ($head === null) {
            $head = self::getHead('W');
        }
        if (self::$info_level !== null && in_array(self::$info_level->get(), [4])) {
            $trace = debug_backtrace()[1] ?? ['file' => '', 'function' => ''];
            $trace = '[' . ($trace['class'] ?? '') . ':' . ($trace['function'] ?? '') . '] ';
        }
        if (self::$info_level->get() >= 1) {
            self::log($head . ($trace ?? '') . self::stringable($obj), self::getThemeColor(__FUNCTION__));
        }
    }

    public static function printProps(array $out, $tty_width)
    {
        $store = '';
        foreach ($out as $k => $v) {
            $line = $k . ': ' . $v;
            if (strlen($line) > 19 && $store == '' || $tty_width < 53) {
                Console::log($line);
            } else {
                if ($store === '') {
                    $store = str_pad($line, 19, ' ', STR_PAD_RIGHT);
                } else {
                    $store .= (' |   ' . $line);
                    Console::log($store);
                    $store = '';
                }
            }
        }
        if ($store != '') {
            Console::log($store);
        }
    }


    private static function getHead($mode)
    {
        $head = date('[m-d H:i:s] ') . "[{$mode[0]}] ";
        $head .= "[#".Coroutine::getCid()."] ";
        return $head;
    }

    private static function getThemeColor(string $function)
    {
        return self::$theme_config[self::$theme][$function] ?? self::$default_theme[$function];
    }

    private static function stringable($str)
    {
        if (is_object($str) && method_exists($str, '__toString')) {
            return $str;
        }
        if (is_string($str) || is_numeric($str)) {
            return strval($str);
        }
        if (is_callable($str)) {
            return '{Closure}';
        }
        if (is_bool($str)) {
            return $str ? '*True*' : '*False*';
        }
        if (is_array($str)) {
            return json_encode($str, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
        }
        if (is_resource($str)) {
            return '{Resource}';
        }
        if (is_null($str)) {
            return 'NULL';
        }
        return '{Not Stringable Object:' . get_class($str) . '}';
    }

    private static function logToFile(string $obj)
    {
        file_put_contents(self::$output_file, $obj . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
