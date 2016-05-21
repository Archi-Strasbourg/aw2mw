<?php
namespace AW2MW;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportAllCommand extends Command
{
    /**
     * Configure command
     * @return void
     */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('export:all')
            ->setDescription('Export all data');
    }
    /**
     * Execute command
     * @param  InputInterface  $input  Input
     * @param  OutputInterface $output Output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

    }
}
