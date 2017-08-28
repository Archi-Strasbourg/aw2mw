<?php

namespace AW2MW;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExportAllPersonCommand extends ExportCommand
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
            ->setName('export:person:all')
            ->setDescription('Export every person');
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

        global $config;
        $config = new \ArchiConfig();

        $reqPerson = '
            SELECT
            ep.idEvenement as idEvenementGA,
            p.idPersonne
            FROM _personneEvenement ep
            LEFT JOIN personne p on p.idPersonne = ep.idPersonne
        ';

        $resPerson = $this->a->connexionBdd->requete($reqPerson);
        while ($fetch = mysql_fetch_assoc($resPerson)) {
            if ($fetch['idPersonne'] > 0) {
                @$person = new \ArchiPersonne($fetch['idPersonne']);
                if (!isset($person->nom)) {
                    $this->output->writeln('<error>Personne introuvable</error>');
                    continue;
                }

                $pageName = 'Personne:'.$person->prenom.' '.$person->nom;
                if ($this->services->newPageGetter()->getFromTitle($pageName)->getId() == 0) {
                    try {
                        $command = $this->getApplication()->find('export:person');
                        $command->run(
                            new ArrayInput(['id' => $person->idPersonne]),
                            $output
                        );
                    } catch (\Exception $e) {
                        $output->writeln('<error>Couldn\'t export ID '.$fetch['idPersonne'].' </error>');
                    }
                }
            }
        }
    }
}
