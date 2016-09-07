<?php

namespace AW2MW;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExportAllUserCommand extends ExportCommand
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
            ->setName('export:user:all')
            ->setDescription('Export every user');
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
        parent::setup($input, $output);
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
                } catch (Exception $e) {
                    $output->writeln('<info>Couldn\'t export ID '.$user['idUtilisateur'].' </info>');
                }
            }
        }
    }
}
