<?php

namespace AW2MW;

use Mediawiki\Api;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportAllPersonCommentsCommand extends ExportCommand
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
            ->setName('export:person:comments')
            ->setDescription('Export every person comment')
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

        global $config;
        $config = new \ArchiConfig();

        $reqPerson = '
            SELECT
            ep.idEvenement as idEvenementGA,
            p.idPersonne
            FROM _personneEvenement ep
            LEFT JOIN personne p on p.idPersonne = ep.idPersonne
        ';

        $start = $this->input->getOption('start');
        if (isset($start)) {
            $reqPerson .= 'WHERE p.idPersonne >= '.mysql_real_escape_string($start);
        }

        $resPerson = $this->a->connexionBdd->requete($reqPerson);
        while ($fetch = mysql_fetch_assoc($resPerson)) {
            if ($fetch['idPersonne'] > 0) {
                @$person = new \ArchiPersonne($fetch['idPersonne']);
                if (!isset($person->nom) || empty(trim($person->nom))) {
                    $this->output->writeln('<error>Personne introuvable</error>');
                    continue;
                }

                $comments = [];

                $pageName = 'Personne:'.$person->prenom.' '.$person->nom;

                $pageID = $this->services->newPageGetter()->getFromTitle($pageName)->getID();

                if ($pageID > 0) {
                    $this->output->writeln('<info>Exporting comments on "'.$pageName.'"…</info>');

                    $events = $person->getEvents($person->idPersonne);
                    foreach ($events as $section => $event) {
                        $reqEventsComments = "SELECT c.idCommentairesEvenement as idCommentaire, c.nom as nom,
                        c.prenom as prenom,c.email as email,DATE_FORMAT(c.date,'"._('%d/%m/%Y à %kh%i')."') as dateF,
                        c.commentaire as commentaire,c.idUtilisateur as idUtilisateur
                                 ,date_format( c.date, '%Y%m%d%H%i%s' ) AS dateTri
                                FROM commentairesEvenement c
                                LEFT JOIN utilisateur u ON u.idUtilisateur = c.idUtilisateur
                                WHERE c.idEvenement = '".mysql_real_escape_string($event['idEvenementAssocie'])."'
                                AND CommentaireValide=1
                                ORDER BY DateTri ASC
                                ";
                        $resEventsComments = $this->a->connexionBdd->requete($reqEventsComments);
                        while ($comment = mysql_fetch_assoc($resEventsComments)) {
                            $comments[] = $comment;
                        }
                    }
                    $commentDates = [];
                    foreach ($comments as $key => $comment) {
                        $commentDates[$key] = $comment['dateTri'];
                    }
                    array_multisort($commentDates, SORT_ASC, $comments);

                    foreach ($comments as $comment) {
                        $this->loginManager->login($comment['prenom'].' '.$comment['nom']);
                        $this->api->postRequest(
                            new Api\SimpleRequest(
                                'commentsubmit',
                                [
                                    'pageID'      => $pageID,
                                    'parentID'    => 0,
                                    'commentText' => $this->convertHtml(
                                        (string) $this->bbCode->convertToDisplay(['text' => $comment['commentaire']])
                                    ),
                                    'token'       => $this->api->getToken(),
                                ]
                            )
                        );
                        //This is to make sure comments are posted in the right order
                        sleep(1);
                    }
                } else {
                    $this->output->writeln('<comment>Skipping "'.$pageName.'" because it does not exist</comment>');
                }
            }
        }
    }
}
