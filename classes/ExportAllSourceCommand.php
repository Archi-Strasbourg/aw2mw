<?php
namespace AW2MW;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Mediawiki\Api;
use Mediawiki\DataModel;
use AW2MW\Config;

class ExportAllSourceCommand extends ExportCommand
{
    /**
     * Configure command
     *
     * @return void
     */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('export:source:all')
            ->setDescription('Export every source');
    }


    /**
     * Execute command
     *
     * @param InputInterface  $input  Input
     * @param OutputInterface $output Output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::setup($input, $output);
        $reqSource="
            SELECT idSource
            FROM source
            ";

        $resSource = $this->a->connexionBdd->requete($reqSource);
        while ($source = mysql_fetch_assoc($resSource)) {
            if ($source['idSource'] > 0) {
                try {
                    $command = $this->getApplication()->find('export:source');
                    $command->run(
                        new ArrayInput(array('id'=>$source['idSource'])),
                        $output
                    );
                } catch (Exception $e) {
                    $output->writeln('<info>Couldn\'t export ID '.$source['idSource'].' </info>');
                }
            }
        }

    }
}
