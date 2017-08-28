<?php

namespace AW2MW;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportAllSourceCommand extends ExportCommand
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
            ->setName('export:source:all')
            ->setDescription('Export every source')
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Force reupload'
            )->addOption(
                'start',
                null,
                InputOption::VALUE_REQUIRED,
                'Start exporting at this ID'
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
        $start = $this->input->getOption('start');
        $reqSource = '
            SELECT idSource
            FROM source
            ';
        if (isset($start)) {
            $reqSource .= 'WHERE idSource >= '.mysql_real_escape_string($start);
        }

        $resSource = $this->a->connexionBdd->requete($reqSource);
        while ($source = mysql_fetch_assoc($resSource)) {
            if ($source['idSource'] > 0) {
                try {
                    $command = $this->getApplication()->find('export:source');
                    $command->run(
                        new ArrayInput(['id' => $source['idSource'], '--force'=>$this->input->getOption('force')]),
                        $output
                    );
                } catch (\Exception $e) {
                    $output->writeln('<info>Couldn\'t export ID '.$source['idSource'].' </info>');
                }
            }
        }
    }
}
