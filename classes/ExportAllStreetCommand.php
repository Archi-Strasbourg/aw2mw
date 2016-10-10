<?php

namespace AW2MW;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExportAllStreetCommand extends ExportCommand
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
            ->setName('export:street:all')
            ->setDescription('Export every street');
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
        $reqStreet = '
            SELECT idRue
            FROM rue
            ';

        $resStreet = $this->a->connexionBdd->requete($reqStreet);
        while ($street = mysql_fetch_assoc($resStreet)) {
            if ($street['idRue'] > 0) {
                try {
                    $command = $this->getApplication()->find('export:street');
                    $command->run(
                        new ArrayInput(['id' => $street['idRue']]),
                        $output
                    );
                } catch (Exception $e) {
                    $output->writeln('<info>Couldn\'t export ID '.$street['idRue'].' </info>');
                }
            }
        }
    }
}
