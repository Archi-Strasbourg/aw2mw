<?php

namespace AW2MW;

use Mediawiki\Api;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExportPersonCommand extends ExportCommand
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
            ->setName('export:person')
            ->setDescription('Export one specific person')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'Person ID'
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
        $id = $input->getArgument('id');
        @$person = new \ArchiPersonne($id);
        if (!isset($person->nom)) {
            $this->output->writeln('<error>Personne introuvable</error>');

            return;
        }

        $pageName = 'Personne:'.$person->prenom.' '.$person->nom;
        $this->output->writeln('<info>Exporting "'.$pageName.'"…</info>');

        $this->loginAsAdmin();
        $this->deletePage($pageName);

        $this->login('aw2mw bot');

        $events = $person->getEvents($id);

        $reqImage = "
            SELECT idImage
            FROM _personneImage
            WHERE idPersonne = '".mysql_real_escape_string($id)."'
        ";

        $resImage = $config->connexionBdd->requete($reqImage);

        $intro = '{{Infobox personne'.PHP_EOL;
        $intro .= '|nom='.$person->nom.PHP_EOL;
        $intro .= '|prenom='.$person->prenom.PHP_EOL;
        if ($person->dateNaissance != '0000-00-00') {
            $intro .= '|date_naissance='.$person->dateNaissance.PHP_EOL;
        }
        if ($person->dateDeces != '0000-00-00') {
            $intro .= '|date_décès='.$person->dateDeces.PHP_EOL;
        }
        if ($fetch = mysql_fetch_object($resImage)) {
            $command = $this->getApplication()->find('export:image');
            $command->run(
                new ArrayInput(['id' => $fetch->idImage]),
                $this->output
            );
            $filename = $this->getImageName($fetch->idImage);
            $intro .= '|photo='.$filename.PHP_EOL;
        }
        $reqJob = 'SELECT nom
            FROM `metier`
            WHERE `idMetier` ='.$person->idMetier;
        $resJob = $config->connexionBdd->requete($reqJob);
        if ($fetch = mysql_fetch_object($resJob)) {
            $intro .= '|métier='.$fetch->nom.PHP_EOL;
        }
        $intro .= '}}'.PHP_EOL;

        $sections = [];
        $sections[0] = $content = $intro;

        $this->api->postRequest(
            new Api\SimpleRequest(
                'edit',
                [
                    'title'   => $pageName,
                    'md5'     => md5($intro),
                    'text'    => $intro,
                    'section' => 0,
                    'bot'     => true,
                    'summary' => 'Informations importées depuis Archi-Wiki',
                    'token'   => $this->api->getToken(),
                ]
            )
        );

        //Create page structure
        foreach ($events as $event) {
            $sql = 'SELECT  hE.idEvenement,
                    hE.titre, hE.idSource,
                    hE.idTypeStructure,
                    hE.idTypeEvenement,
                    hE.description,
                    hE.dateDebut,
                    hE.dateFin,
                    hE.dateDebut,
                    hE.dateFin,
                    tE.nom AS nomTypeEvenement,
                    tS.nom AS nomTypeStructure,
                    s.nom AS nomSource,
                    u.nom AS nomUtilisateur,
                    u.prenom as prenomUtilisateur,
                    tE.groupe,
                    hE.ISMH,
                    hE.MH,
                    date_format(hE.dateCreationEvenement,"'._('%e/%m/%Y à %kh%i').'") as dateCreationEvenement,
                    hE.isDateDebutEnviron as isDateDebutEnviron,
                    u.idUtilisateur as idUtilisateur,
                    hE.numeroArchive as numeroArchive

                    FROM historiqueEvenement hE
                    LEFT JOIN source s      ON s.idSource = hE.idSource
                    LEFT JOIN typeStructure tS  ON tS.idTypeStructure = hE.idTypeStructure
                    LEFT JOIN typeEvenement tE  ON tE.idTypeEvenement = hE.idTypeEvenement
                    LEFT JOIN utilisateur u     ON u.idUtilisateur = hE.idUtilisateur
                    WHERE hE.idEvenement = '.mysql_real_escape_string($event['idEvenementAssocie']).'
            ORDER BY hE.idHistoriqueEvenement DESC';

            $res = $this->e->connexionBdd->requete($sql);

            $event = mysql_fetch_assoc($res);

            if (!empty($event['titre'])) {
                $title = $event['titre'];
            } elseif ($event['dateDebut'] != '0000-00-00') {
                $title = substr($event['dateDebut'], 0, 4);
            } else {
                $title = 'Biographie';
            }
            $title = stripslashes($title);
            $content .= '=='.$title.'=='.PHP_EOL;
        }

        $relatedPeople = $person->getRelatedPeople($id);
        if (!empty($relatedPeople)) {
            $relatedPeopleContent = '==Personnes liées=='.PHP_EOL;
        } else {
            $relatedPeopleContent = '';
        }
        foreach ($relatedPeople as $relatedId) {
            $relatedPerson = new \ArchiPersonne($relatedId);
            $relatedPeopleContent .= '* [[Personne:'.$relatedPerson->prenom.' '.$relatedPerson->nom.'|'.
                $relatedPerson->prenom.' '.$relatedPerson->nom.']]'.PHP_EOL;
        }

        $content .= $relatedPeopleContent;

        $references = PHP_EOL.'==Références=='.PHP_EOL.'<references />'.PHP_EOL;
        $content .= $references;

        $this->savePage($pageName, $content, 'Sections importées depuis Archi-Wiki');

        foreach ($events as $section => $event) {
            $req = 'SELECT idHistoriqueEvenement
                    FROM historiqueEvenement
                    WHERE idEvenement='.mysql_real_escape_string($event['idEvenementAssocie']).'
                    order by dateCreationEvenement ASC';
            $res = $this->e->connexionBdd->requete($req);

            while ($fetch = mysql_fetch_assoc($res)) {
                $sql = 'SELECT  hE.idEvenement,
                        hE.titre, hE.idSource,
                        hE.idTypeStructure,
                        hE.idTypeEvenement,
                        hE.description,
                        hE.dateDebut,
                        hE.dateFin,
                        hE.dateDebut,
                        hE.dateFin,
                        tE.nom AS nomTypeEvenement,
                        tS.nom AS nomTypeStructure,
                        s.nom AS nomSource,
                        u.nom AS nomUtilisateur,
                        u.prenom as prenomUtilisateur,
                        tE.groupe,
                        hE.ISMH ,
                        hE.MH,
                        date_format(hE.dateCreationEvenement,"'._('%e/%m/%Y à %kh%i').'") as dateCreationEvenement,
                        hE.isDateDebutEnviron as isDateDebutEnviron,
                        u.idUtilisateur as idUtilisateur,
                        hE.numeroArchive as numeroArchive

                        FROM historiqueEvenement hE
                        LEFT JOIN source s      ON s.idSource = hE.idSource
                        LEFT JOIN typeStructure tS  ON tS.idTypeStructure = hE.idTypeStructure
                        LEFT JOIN typeEvenement tE  ON tE.idTypeEvenement = hE.idTypeEvenement
                        LEFT JOIN utilisateur u     ON u.idUtilisateur = hE.idUtilisateur
                        WHERE hE.idHistoriqueEvenement = '.mysql_real_escape_string($fetch['idHistoriqueEvenement']).'
                ORDER BY hE.idHistoriqueEvenement DESC';

                $eventInfo = mysql_fetch_assoc($this->e->connexionBdd->requete($sql));

                $user = $this->u->getArrayInfosFromUtilisateur($eventInfo['idUtilisateur']);

                //Login as user
                if (!empty($user['nom'])) {
                    $this->login($user['prenom'].' '.$user['nom']);
                } else {
                    $this->login('aw2mw bot');
                }


                $content = '';

                if (!empty($eventInfo['titre'])) {
                    $title = $eventInfo['titre'];
                } elseif (!empty($eventInfo['nomTypeEvenement'])) {
                    $title = $eventInfo['nomTypeEvenement'];
                } else {
                    $title = 'Biographie';
                }

                if ($eventInfo['idSource'] > 0) {
                    $sourceName = $this->s->getSourceLibelle($eventInfo['idSource']);
                    $title .= '<ref>[[Source:'.$sourceName.'|'.$sourceName.']]</ref>';
                }
                if (!empty($eventInfo['numeroArchive'])) {
                    $sourceName = $this->s->getSourceLibelle(24);
                    $title .= '<ref>[[Source:'.$sourceName.'|'.$sourceName.']] - Cote '.
                        $event['numeroArchive'].'</ref>';
                }

                $title = ucfirst(stripslashes($title));
                $content .= '=='.$title.'=='.PHP_EOL;

                $html = $this->convertHtml(
                    (string) $this->bbCode->convertToDisplay(['text' => $eventInfo['description']])
                );


                $content .= trim($html).PHP_EOL.PHP_EOL;
                $this->api->postRequest(
                    new Api\SimpleRequest(
                        'edit',
                        [
                            'title'   => $pageName,
                            'md5'     => md5($content),
                            'text'    => $content,
                            'section' => $section + 1,
                            'bot'     => true,
                            'summary' => 'Révision du '.$eventInfo['dateCreationEvenement'].
                                ' importée depuis Archi-Wiki',
                            'token' => $this->api->getToken(),
                        ]
                    )
                );
                $sections[$section + 1] = $content;
            }

            $linkedEvents = $person->getEvenementsLies($id, $eventInfo['dateDebut'], 3000);
            if (!empty($linkedEvents)) {
                $html = '=== Adresses liées ==='.PHP_EOL;
            }
            foreach ($linkedEvents as $linkedEvent) {
                $req = "
                    SELECT titre, dateDebut, dateFin, idTypeEvenement
                    FROM historiqueEvenement
                    WHERE idEvenement = '".$linkedEvent."'
                    ORDER BY idHistoriqueEvenement DESC
                ";

                $resEvent = $config->connexionBdd->requete($req);
                $linkedEventInfo = mysql_fetch_object($resEvent);

                $linkedEventAddress = $this->a->getIntituleAdresseFrom(
                    $linkedEvent,
                    'idEvenement',
                    [
                        'noHTML'                   => true,
                        'noQuartier'               => true,
                        'noSousQuartier'           => true,
                        'noVille'                  => true,
                        'displayFirstTitreAdresse' => true,
                        'setSeparatorAfterTitle'   => '_',
                    ]
                );

                if (!empty($linkedEventAddress)) {
                    $req = '
                            SELECT  idAdresse
                            FROM _adresseEvenement
                            WHERE idEvenement = '.
                            $this->e->getIdEvenementGroupeAdresseFromIdEvenement($linkedEvent);
                    $resAddress = $config->connexionBdd->requete($req);
                    $fetchAddress = mysql_fetch_object($resAddress);
                    if (isset($fetchAddress->idAdresse)) {
                        $linkedEventIdAddress = $fetchAddress->idAdresse;
                    }
                }

                $linkedEventImg = $this->a->getUrlImageFromEvenement($linkedEvent, 'mini');
                if ($linkedEventImg['url'] == $config->getUrlImage('', 'transparent.gif')) {
                    $linkedEventImg = $this->a->getUrlImageFromAdresse($linkedEventIdAddress, 'mini');
                }
                $html .= '{{Adresse liée
                    |adresse='.$this->getAddressName($linkedEventIdAddress).PHP_EOL;
                $reqImage = 'SELECT idImage FROM historiqueImage
                    WHERE idHistoriqueImage = '.mysql_real_escape_string($linkedEventImg['idHistoriqueImage']).'
                    ORDER BY idHistoriqueImage DESC LIMIT 1';
                $resImage = $config->connexionBdd->requete($reqImage);
                $imageInfo = mysql_fetch_object($resImage);
                if (isset($imageInfo->idImage)) {
                    $command = $this->getApplication()->find('export:image');
                    $command->run(
                        new ArrayInput(['id' => $imageInfo->idImage]),
                        $this->output
                    );
                    $filename = $this->getImageName($imageInfo->idImage);
                    $html .= '|photo='.$filename.PHP_EOL;
                }
                if ($linkedEventInfo->dateDebut != '0000-00-00') {
                    if ($linkedEventInfo->dateFin != '0000-00-00') {
                        $linkedDate = $linkedEventInfo->dateFin;
                    } else {
                        $linkedDate = $linkedEventInfo->dateDebut;
                    }
                    $html .= '|date='.$config->date->toFrench($linkedDate).PHP_EOL;
                }
                $html .= '}}'.PHP_EOL;
            }
            $this->api->postRequest(
                new Api\SimpleRequest(
                    'edit',
                    [
                        'title'   => $pageName,
                        'md5'     => md5($html),
                        'text'    => $html,
                        'section' => $section + 1,
                        'bot'     => true,
                        'summary' => 'Importation des adresses liées depuis Archi-Wiki',
                        'token'   => $this->api->getToken(),
                    ]
                )
            );
            $sections[$section + 1] .= $html;
        }

        $sections[] = $relatedPeopleContent;
        $sections[] = $references;

        //Login with bot
        $this->login('aw2mw bot');

        $content = implode('', $sections);

        //Replace <u/> with ===
        $content = $this->replaceSubtitles($content);

        $this->savePage($pageName, $content, 'Conversion des titres de section');
    }
}
