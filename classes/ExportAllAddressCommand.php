<?php

namespace AW2MW;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExportAllAddressCommand extends ExportCommand
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
            ->setName('export:address:all')
            ->setDescription('Export every address');
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
        return;
        $reqUser = '
            SELECT idUtilisateur
            FROM utilisateur
            ';

        $resUser = $this->a->connexionBdd->requete($reqUser);
        while ($user = mysql_fetch_assoc($resUser)) {
            if ($user['idUtilisateur'] > 0) {
                try {
                    $command = $this->getApplication()->find('export:user');
                    $command->run(
                        new ArrayInput(['id' => $user['idUtilisateur']]),
                        $output
                    );
                } catch (\Exception $e) {
                    $output->writeln('<info>Couldn\'t export ID '.$user['idUtilisateur'].' </info>');
                }
            }
        }
    }
}
