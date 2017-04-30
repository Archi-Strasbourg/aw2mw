<?php

namespace AW2MW;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportEventCommand extends AbstractEventCommand
{
    /**
     * Configure command.
     *
     * @return void
     */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('export:event')
            ->setDescription('Export one specific address event')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'Event ID'
            )->addArgument(
                'section',
                InputArgument::REQUIRED,
                'Page section to replace'
            )->addArgument(
                'pagename',
                InputArgument::REQUIRED,
                'Page name'
            )->addOption(
                'noimage',
                null,
                InputOption::VALUE_NONE,
                "Don't upload images"
            );
    }

    /**
     * Execute command.
     *
     * @param InputInterface  $input  Input
     * @param OutputInterface $output Output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->setup($input, $output);
        $this->exportEvent(
            $this->input->getArgument('id'),
            $this->input->getArgument('section') - 1,
            'Adresse:'.$this->input->getArgument('pagename')
        );
    }
}
