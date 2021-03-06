<?php

namespace AW2MW;

use Mediawiki\Api;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportPersonCommand extends ExportCommand
{
    protected $pageName;
    protected $person;
    protected $id;
    protected $sections;

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
            )->addOption(
                'noimage',
                null,
                InputOption::VALUE_NONE,
                "Don't upload images"
            );
    }

    private function exportEventImages(array $event, $section)
    {
        $reqImages = "
            SELECT hi1.idImage, hi1.description
            FROM _evenementImage ei
            LEFT JOIN historiqueImage hi1 ON hi1.idImage = ei.idImage
            LEFT JOIN historiqueImage hi2 ON hi2.idImage = hi1.idImage
            WHERE ei.idEvenement = '".mysql_real_escape_string($event['idEvenementAssocie'])."'
            GROUP BY hi1.idImage ,  hi1.idHistoriqueImage
            HAVING hi1.idHistoriqueImage = max(hi2.idHistoriqueImage)
            ORDER BY ei.position, hi1.idHistoriqueImage
            ";

        $resImages = $this->i->connexionBdd->requete($reqImages);
        $images = [];
        while ($fetchImages = mysql_fetch_assoc($resImages)) {
            $images[] = $fetchImages;
        }

        if (!empty($images)) {
            $this->sections[$section + 1] .= $this->createGallery($images);
        }
        $this->api->postRequest(
            new Api\SimpleRequest(
                'edit',
                [
                    'title'   => $this->pageName,
                    'md5'     => md5($this->sections[$section + 1]),
                    'text'    => $this->sections[$section + 1],
                    'section' => $section + 1,
                    'bot'     => true,
                    'summary' => 'Images importées depuis Archi-Wiki',
                    'token'   => $this->api->getToken(),
                ]
            )
        );
    }

    private function exportEventLinkedAddresses(array $eventInfo, $section, array $nextEvent = null)
    {
        global $config;

        if (isset($nextEvent)) {
            $req = "
    			SELECT dateDebut
    			FROM evenements
    			WHERE idEvenement = '".mysql_real_escape_string($nextEvent['idEvenementAssocie'])."'
    			ORDER BY idEvenement DESC LIMIT 1
    			";

            $res = $this->e->connexionBdd->requete($req);
            $date2 = mysql_fetch_object($res)->dateDebut;
        } else {
            $date2 = 3000;
        }

        $linkedEvents = $this->person->getEvenementsLies($this->id, $eventInfo['dateDebut'], $date2);
        $html = '';
        if (!empty($linkedEvents)) {
            $html .= '=== Adresses liées ==='.PHP_EOL;
        }
        $linkedAddresses = [];
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
                $groupId = $this->e->getIdEvenementGroupeAdresseFromIdEvenement($linkedEvent);
                $req = '
                        SELECT  idAdresse
                        FROM _adresseEvenement
                        WHERE idEvenement = '.
                        mysql_real_escape_string($groupId);
                $resAddress = $config->connexionBdd->requete($req);
                $fetchAddress = mysql_fetch_object($resAddress);
                if (isset($fetchAddress->idAdresse)) {
                    $linkedEventIdAddress = $fetchAddress->idAdresse;
                }
                if (in_array($linkedEventIdAddress, $linkedAddresses)) {
                    //Avoid duplicates
                    continue;
                }
                $linkedAddresses[] = $linkedEventIdAddress;

                $linkedEventImg = $this->a->getUrlImageFromAdresse(
                    $linkedEventIdAddress,
                    'mini',
                    [
                        'idEvenementGroupeAdresse' => $groupId,
                    ]
                );
                $html .= '{{Adresse liée'.PHP_EOL.
                    '|adresse='.$this->getAddressName($linkedEventIdAddress, $groupId).PHP_EOL;
                if (!empty($linkedEventImg['idHistoriqueImage'])) {
                    $reqImage = 'SELECT idImage FROM historiqueImage
                        WHERE idHistoriqueImage = '.mysql_real_escape_string($linkedEventImg['idHistoriqueImage']).'
                        ORDER BY idHistoriqueImage DESC LIMIT 1';
                    $resImage = $config->connexionBdd->requete($reqImage);
                    $imageInfo = mysql_fetch_object($resImage);
                    if (isset($imageInfo->idImage)) {
                        if (!$this->input->getOption('noimage')) {
                            $command = $this->getApplication()->find('export:image');
                            $command->run(
                                new ArrayInput(['id' => $imageInfo->idImage]),
                                $this->output
                            );
                        }
                        $filename = $this->getImageName($imageInfo->idImage);
                        $html .= '|photo='.$filename.PHP_EOL;
                    }
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
        }
        $this->sections[$section + 1] .= $html;
        $this->api->postRequest(
            new Api\SimpleRequest(
                'edit',
                [
                    'title'   => $this->pageName,
                    'md5'     => md5($this->sections[$section + 1]),
                    'text'    => $this->sections[$section + 1],
                    'section' => $section + 1,
                    'bot'     => true,
                    'summary' => 'Importation des adresses liées depuis Archi-Wiki',
                    'token'   => $this->api->getToken(),
                ]
            )
        );
    }

    private function exportEvent(array $event, $section, array $nextEvent = null)
    {
        global $config;
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
                $this->loginManager->login($user['prenom'].' '.$user['nom']);
            } else {
                $this->loginManager->login('aw2mw bot');
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
                $sourceName = Source::getSourceName($eventInfo['idSource']);
                $title .= '<ref>[[Source:'.$sourceName.'|'.$sourceName.']]</ref>';
            }
            if (!empty($eventInfo['numeroArchive'])) {
                $sourceName = Source::getSourceName(24);
                $title .= '<ref>[[Source:'.$sourceName.'|'.$sourceName.']] - Cote '.
                    $eventInfo['numeroArchive'].'</ref>';
            }

            $title = ucfirst(stripslashes($title));
            $content .= '== '.$title.' =='.PHP_EOL;

            $html = $this->convertHtml(
                (string) $this->bbCode->convertToDisplay(['text' => $eventInfo['description']])
            );

            $content .= trim($html).PHP_EOL.PHP_EOL;
            $this->api->postRequest(
                new Api\SimpleRequest(
                    'edit',
                    [
                        'title'   => $this->pageName,
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
            $this->sections[$section + 1] = $content;
        }

        $this->exportEventImages($event, $section);

        $this->exportEventLinkedAddresses($eventInfo, $section, $nextEvent);
    }

    private function getSectionTitle($event)
    {
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

        return '== '.$title.' =='.PHP_EOL;
    }

    private function getRelatedPeople()
    {
        $relatedPeople = $this->person->getRelatedPeople($this->id);
        if (!empty($relatedPeople)) {
            $relatedPeopleContent = '== Personnes liées =='.PHP_EOL;
        } else {
            $relatedPeopleContent = '';
        }
        foreach ($relatedPeople as $relatedId) {
            $relatedPerson = new \ArchiPersonne($relatedId);
            $job = $this->getJobName($relatedPerson->idMetier);
            $relatedPeopleContent .= '* [[Personne:'.$relatedPerson->prenom.' '.$relatedPerson->nom.'|'.
                $relatedPerson->prenom.' '.$relatedPerson->nom;
            if (isset($job)) {
                $relatedPeopleContent .= ' ('.$job.')';
            }
            $relatedPeopleContent .= ']]'.PHP_EOL;
        }

        return $relatedPeopleContent;
    }

    private function getIntro()
    {
        global $config;
        $reqImage = "
            SELECT idImage
            FROM _personneImage
            WHERE idPersonne = '".mysql_real_escape_string($this->id)."'
        ";

        $resImage = $config->connexionBdd->requete($reqImage);

        $intro = '{{Infobox personne'.PHP_EOL;
        $intro .= '|nom='.$this->person->nom.PHP_EOL;
        $intro .= '|prenom='.$this->person->prenom.PHP_EOL;
        if ($this->person->dateNaissance != '0000-00-00') {
            $intro .= '|date_naissance='.$this->person->dateNaissance.PHP_EOL;
        }
        if ($this->person->dateDeces != '0000-00-00') {
            $intro .= '|date_décès='.$this->person->dateDeces.PHP_EOL;
        }
        if ($fetch = mysql_fetch_object($resImage)) {
            if ($fetch->idImage > 0) {
                try {
                    $command = $this->getApplication()->find('export:image');
                    $command->run(
                        new ArrayInput(['id' => $fetch->idImage]),
                        $this->output
                    );
                    $filename = $this->getImageName($fetch->idImage);
                    $intro .= '|photo_principale='.$filename.PHP_EOL;
                } catch (\Exception $e) {
                    $this->output->writeln('<error>Couldn\'t export image '.$fetch->idImage.'</error>');
                }
            }
        }
        $job = $this->getJobName($this->person->idMetier);
        if (isset($job)) {
            $intro .= '|métier1='.$job.PHP_EOL;
        }
        $intro .= '}}'.PHP_EOL;

        return $intro;
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
        $this->id = $input->getArgument('id');
        @$this->person = new \ArchiPersonne($this->id);
        if (!isset($this->person->nom) || empty(trim($this->person->nom))) {
            $this->output->writeln('<error>Personne introuvable</error>');

            return;
        }

        $this->pageName = 'Personne:'.$this->person->prenom.' '.$this->person->nom;
        $this->output->writeln('<info>Exporting "'.$this->pageName.'"…</info>');

        $this->loginManager->loginAsAdmin();
        $this->pageSaver->deletePage($this->pageName);

        $this->loginManager->login('aw2mw bot');

        $events = $this->person->getEvents($this->id);

        $intro = $this->getIntro();

        $this->sections = [];
        $this->sections[0] = $content = $intro;

        $this->api->postRequest(
            new Api\SimpleRequest(
                'edit',
                [
                    'title'   => $this->pageName,
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
            $content .= $this->getSectionTitle($event);
        }

        $relatedPeopleContent = $this->getRelatedPeople();

        $content .= $relatedPeopleContent;

        $references = PHP_EOL.'== Références =='.PHP_EOL.'<references />'.PHP_EOL;
        $content .= $references;

        $this->pageSaver->savePage($this->pageName, $content, 'Sections importées depuis Archi-Wiki');

        foreach ($events as $section => $event) {
            if (isset($events[$section + 1])) {
                $this->exportEvent($event, $section, $events[$section + 1]);
            } else {
                $this->exportEvent($event, $section);
            }
        }

        $this->sections[] = $relatedPeopleContent;
        $this->sections[] = $references;

        //Login with bot
        $this->loginManager->login('aw2mw bot');

        $content = implode('', $this->sections);

        //Replace <u/> with ===
        $content = $this->replaceSubtitles($content);
        $this->pageSaver->savePage($this->pageName, $content, 'Conversion des titres de section');

        $content = $this->replaceSourceLists($content);
        $this->pageSaver->savePage($this->pageName, $content, 'Conversion des listes de sources');

        //$content = '<translate>'.PHP_EOL.$content.PHP_EOL.'</translate>';
        //$this->pageSaver->savePage($this->pageName, $content, 'Ajout des balises de traduction');
    }
}
