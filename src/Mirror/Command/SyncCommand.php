<?php

declare(strict_types=1);

namespace Mirror\Command;

use Mirror\Console\Console;
use Mirror\MirrorEntry;
use Mirror\Store\SpinLock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCommand extends Command
{
    protected static $defaultName = 'sync';

    protected function configure()
    {
        $this->setAliases(['sync:start']);
        $this->setDescription('Run syncing | 启动同步');
        $this->setHelp('直接运行可以启动');
        $this->addOption('config', null, InputOption::VALUE_REQUIRED, '配置文件路径');
        $this->addOption('log-level', null, InputOption::VALUE_REQUIRED, '日志级别');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Console::init(intval($input->getOption('log-level') ?? 2));
        SpinLock::init(64);
        date_default_timezone_set('Asia/Shanghai');
        return MirrorEntry::execute($input);
    }
}
