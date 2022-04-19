<?php

declare(strict_types=1);

namespace Mirror;

use Exception;
use Mirror\Command\SyncCommand;
use Mirror\Store\DataProvider;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleApplication extends Application
{
    public const VERSION_ID = 1;

    public const VERSION = '0.1.0';

    public function __construct(string $name = 'UNKNOWN')
    {
        parent::__construct($name, self::VERSION);
        $this->initEnv();
    }

    public function initEnv(): ConsoleApplication
    {
        define('WORKING_DIR', getcwd());
        if (!is_dir(DataProvider::getTmpPath())) {
            mkdir(DataProvider::getTmpPath(), 0777, true);
        }
        ini_set('memory_limit', '4096M');
        $this->add(new SyncCommand());
        return $this;
    }

    public function run(InputInterface $input = null, OutputInterface $output = null): int
    {
        try {
            return parent::run($input, $output);
        } catch (Exception $e) {
            echo "{$e->getMessage()} at {$e->getFile()}({$e->getLine()})" . PHP_EOL;
            exit(1);
        }
    }
}
