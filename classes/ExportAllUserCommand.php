<?php

namespace AW2MW;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
            ->setDescription('Export every user')
            ->addOption(
                'emails',
                null,
                InputOption::VALUE_NONE,
                'Only list e-mails'
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
        $reqUser = '
            SELECT idUtilisateur, prenom, nom, mail
            FROM utilisateur
            ';

        $resUser = $this->a->connexionBdd->requete($reqUser);
        while ($user = mysql_fetch_assoc($resUser)) {
            if ($user['idUtilisateur'] > 0) {
                if ($this->input->getOption('emails')) {
                    $output->writeln(
                        'UPDATE user SET user_email = "'.
                        mysql_real_escape_string($user['mail']).
                        '" WHERE user_name = "'.
                        mysql_real_escape_string(ucfirst($user['prenom'].' '.$user['nom'])).'";'
                    );
                } else {
                    try {
                        $command = $this->getApplication()->find('export:user');
                        $command->run(
                            new ArrayInput(['id' => $user['idUtilisateur']]),
                            $output
                        );
                    } catch (\Exception $e) {
                        $output->writeln('<error>Couldn\'t export ID '.$user['idUtilisateur'].' </error>');
                    }
                }
            }
        }
    }
}
