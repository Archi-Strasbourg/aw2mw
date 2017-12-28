<?php

namespace AW2MW;

use Mediawiki\Api;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FixUserPreferenceCommand extends ExportCommand
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
            ->setName('fix:user:preference')
            ->setDescription('Set the user email preference')
            ->addOption(
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
        $reqUser = '
            SELECT idUtilisateur, prenom, nom, alerteMail
            FROM utilisateur
            ';
        if (isset($start)) {
            $reqUser .= 'WHERE idUtilisateur >= '.mysql_real_escape_string($start);
        }

        $resUser = $this->a->connexionBdd->requete($reqUser);
        while ($user = mysql_fetch_assoc($resUser)) {
            if ($user['idUtilisateur'] > 0) {
                if (!$user['alerteMail']) {
                    $this->loginManager->login($user['prenom'].' '.$user['nom']);
                    $output->writeln(
                        '<info>Disabling e-mails for '.$user['prenom'].' '.$user['nom'].' ('.$user['idUtilisateur'].')</info>'
                    );
                    $this->api->postRequest(
                        new Api\SimpleRequest(
                            'options',
                            [
                                'optionname'  => 'disablemail',
                                'optionvalue' => 1,
                                'token'       => $this->api->getToken(),
                            ],
                            []
                        )
                    );
                }
            }
        }
    }
}
