<?php

namespace AW2MW;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExportAllRouteCommand extends ExportCommand
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
            ->setName('export:route:all')
            ->setDescription('Export every route');
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
        $req = '
            SELECT idParcours
            FROM parcoursArt
            WHERE isActif = 0
            ';

        $res = $this->a->connexionBdd->requete($req);
        while ($route = mysql_fetch_assoc($res)) {
            try {
                $command = $this->getApplication()->find('export:route');
                $command->run(
                    new ArrayInput(['id' => $route['idParcours']]),
                    $output
                );
            } catch (\Exception $e) {
                $output->writeln('<info>Couldn\'t export ID '.$route['idParcours'].' </info>');
            }
        }
    }
}
