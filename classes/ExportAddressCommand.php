<?php

namespace AW2MW;

use Mediawiki\Api;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportAddressCommand extends AbstractEventCommand
{
    protected $allEvents;

    /**
     * Configure command.
     *
     * @return void
     */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('export:address')
            ->setDescription('Export one specific address')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'Address ID'
            )->addArgument(
                'groupId',
                InputArgument::OPTIONAL,
                'Address group ID'
            )->addOption(
                'noimage',
                null,
                InputOption::VALUE_NONE,
                "Don't upload images"
            );
    }

    private function getEventInfo($id)
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
                hE.numeroArchive as numeroArchive,
                cA.nom as courantArchitectural

                FROM historiqueEvenement hE
                LEFT JOIN source s      ON s.idSource = hE.idSource
                LEFT JOIN typeStructure tS  ON tS.idTypeStructure = hE.idTypeStructure
                LEFT JOIN typeEvenement tE  ON tE.idTypeEvenement = hE.idTypeEvenement
                LEFT JOIN utilisateur u     ON u.idUtilisateur = hE.idUtilisateur
                LEFT JOIN _evenementCourantArchitectural _eCA ON _eCA.idEvenement = hE.idEvenement
                LEFT JOIN courantArchitectural cA ON cA.idCourantArchitectural = _eCA.idCourantArchitectural
                WHERE hE.idEvenement = '.mysql_real_escape_string($id).'
        ORDER BY hE.idHistoriqueEvenement DESC';

        $res = $this->e->connexionBdd->requete($sql);

        return mysql_fetch_assoc($res);
    }

    private function getEventTitle($event)
    {
        if (!empty($event['titre'])) {
            $title = $event['titre'];
        } else {
            $title = substr($event['dateDebut'], 0, 4);
        }
        $title = stripslashes($title);
        $title = trim($title, '.');

        return $title;
    }

    private function getAutresPhotosVuesSurAdresse($listeAdresses = [], $format = 'mini', $params = [])
    {
        $idAdresseCourante = 0;

        $sqlOneImage = '';
        if (isset($params['getOneIdImageFromEvenement']) && $params['getOneIdImageFromEvenement'] == true) {
            $sqlOneImage = "AND ai.idImage='".$params['idImage']."' AND ee.idEvenementAssocie=".$params['idEvenement'].' ';
        }

        $sqlListeAdresses = '';
        if (isset($listeAdresses) && !isset($params['getOneIdImageFromEvenement'])) {
            $sqlListeAdresses = 'AND ai.idAdresse IN ('.implode(',  ', $listeAdresses).') ';
        }

        $sqlNoDisplayIdImages = '';
        if (isset($params['noDiplayIdImage']) && count($params['noDiplayIdImage']) > 0) {
            $sqlNoDisplayIdImages = 'AND ai.idImage NOT IN ('.implode(',  ', $params['noDiplayIdImage']).')';
        }

        $sqlGroupeAdresse = '';
        if (isset($params['idEvenementGroupeAdresse']) && $params['idEvenementGroupeAdresse'] != '0') {
            $sqlGroupeAdresse = "AND ai.idEvenementGroupeAdresse = '".$params['idEvenementGroupeAdresse']."'";
        }

        $idEvenementGroupeAdresseEvenementAffiche = '';
        $divIdEvenementGroupeAdresseEvenementAffiche = '';
        if (isset($params['idGroupeAdresseEvenementAffiche'])) {
            $idEvenementGroupeAdresseEvenementAffiche = $params['idGroupeAdresseEvenementAffiche'];
            $divIdEvenementGroupeAdresseEvenementAffiche = '_'.$params['idGroupeAdresseEvenementAffiche'];
        }

        // recherche des photos :
        $reqPhotos = "
                        SELECT hi1.idHistoriqueImage, hi1.idImage as idImage,  hi1.dateUpload, ai.idAdresse, hi1.description, ae.idEvenement as idEvenementGroupeAdresseCourant
                        FROM historiqueImage hi2,  historiqueImage hi1
                        LEFT JOIN _adresseImage ai ON ai.idImage = hi1.idImage
                        LEFT JOIN _adresseEvenement ae ON ae.idAdresse = ai.idAdresse
                        LEFT JOIN _evenementEvenement ee ON ee.idEvenement = ae.idEvenement
                        WHERE hi2.idImage = hi1.idImage
                        $sqlListeAdresses
                        $sqlOneImage
                        $sqlNoDisplayIdImages
                        $sqlGroupeAdresse
                        AND ai.vueSur='1'
                        GROUP BY hi1.idImage,  hi1.idHistoriqueImage
                        HAVING hi1.idHistoriqueImage = max(hi2.idHistoriqueImage)
        ";

        $resPhotos = $this->i->connexionBdd->requete($reqPhotos);

        return $resPhotos;
    }

    private function getOtherImages(array $address, $groupId)
    {
        $resAdressesCourantes = $this->a->getAdressesFromEvenementGroupeAdresses($groupId);
        $listeAdressesFromEvenement = [];
        while ($fetchAdressesCourantes = mysql_fetch_assoc($resAdressesCourantes)) {
            $listeAdressesFromEvenement[] = $fetchAdressesCourantes['idAdresse'];
        }

        $otherImagesInfo = [];
        $otherImages = '';
        $resPhotos = $this->getAutresPhotosVuesSurAdresse(
            $listeAdressesFromEvenement, 'moyen',
            ['idEvenementGroupeAdresse'=> $groupId, 'idGroupeAdresseEvenementAffiche'=>$groupId]
        );
        while ($fetchPhotos = mysql_fetch_assoc($resPhotos)) {
            $reqPriseDepuis = 'SELECT ai.idAdresse,  ai.idEvenementGroupeAdresse
                                FROM _adresseImage ai
                                WHERE ai.idImage = '.$fetchPhotos['idImage']."
                                AND ai.prisDepuis='1'
            ";
            $resPriseDepuis = $this->a->connexionBdd->requete($reqPriseDepuis);
            $fetchPhotos['description'] = 'Pris depuis';
            $otherAddresses = [];
            while ($otherAddress = mysql_fetch_assoc($resPriseDepuis)) {
                $otherAddress = $this->a->getArrayAdresseFromIdAdresse($otherAddress['idAdresse']);
                $otherAddressName = $this->getAddressName($otherAddress['idAdresse']);
                $otherAddresses[] = ' [[Adresse:'.$otherAddressName.'|'.$otherAddressName.']]';
            }
            $fetchPhotos['description'] .= implode(', ', $otherAddresses);
            $otherImagesInfo[] = $fetchPhotos;
        }
        if (!empty($otherImagesInfo)) {
            $otherImages = PHP_EOL.'== Autres vues sur cette adresse =='.PHP_EOL.
                $this->createGallery($otherImagesInfo, false, false);
        }

        return $otherImages;
    }

    private function getAutresPhotosPrisesDepuisAdresse($listeAdresses = [], $format = 'mini', $params = [])
    {
        $sqlGroupeAdresse = '';
        if (isset($params['idEvenementGroupeAdresse']) && $params['idEvenementGroupeAdresse'] != '0') {
            $sqlGroupeAdresse = "AND ai.idEvenementGroupeAdresse = '".$params['idEvenementGroupeAdresse']."'";
        }

        $idAdresseCourante = 0;
        if (isset($this->variablesGet['archiIdAdresse']) && $this->variablesGet['archiIdAdresse'] != '') {
            $idAdresseCourante = $this->variablesGet['archiIdAdresse'];
        }

        // recherche des photos :
        $reqPhotos = '
                        SELECT hi1.idHistoriqueImage, hi1.idImage as idImage,  hi1.dateUpload, ai.idAdresse, hi1.description, ae.idEvenement as idEvenementGroupeAdresseCourant
                        FROM historiqueImage hi2,  historiqueImage hi1
                        LEFT JOIN _adresseImage ai ON ai.idImage = hi1.idImage
                        LEFT JOIN _adresseEvenement ae ON ae.idAdresse = ai.idAdresse
                        WHERE hi2.idImage = hi1.idImage
                        AND ai.idAdresse IN ('.implode(',  ', $listeAdresses).")
                        AND ai.prisDepuis='1'
                        $sqlGroupeAdresse
                        GROUP BY hi1.idImage,  hi1.idHistoriqueImage
                        HAVING hi1.idHistoriqueImage = max(hi2.idHistoriqueImage)
        ";

        $resPhotos = $this->i->connexionBdd->requete($reqPhotos);

        return $resPhotos;
    }

    private function getImagesFrom(array $address, $groupId)
    {
        $resAdressesCourantes = $this->a->getAdressesFromEvenementGroupeAdresses($groupId);
        $listeAdressesFromEvenement = [];
        while ($fetchAdressesCourantes = mysql_fetch_assoc($resAdressesCourantes)) {
            $listeAdressesFromEvenement[] = $fetchAdressesCourantes['idAdresse'];
        }

        $resPhotos = $this->getAutresPhotosPrisesDepuisAdresse(
            $listeAdressesFromEvenement, 'moyen',
            ['idEvenementGroupeAdresse'=> $groupId, 'idGroupeAdresseEvenementAffiche'=>$groupId]
        );
        $imagesFromInfo = [];
        $imagesFrom = '';
        while ($fetchPhotos = mysql_fetch_assoc($resPhotos)) {
            $reqPriseDepuis = 'SELECT ai.idAdresse,  ai.idEvenementGroupeAdresse
                                FROM _adresseImage ai
                                WHERE ai.idImage = '.$fetchPhotos['idImage']."
                                AND ai.vueSur='1'
            ";
            $resPriseDepuis = $this->a->connexionBdd->requete($reqPriseDepuis);
            $fetchPhotos['description'] = 'Vue sur';
            $otherAddresses = [];
            while ($otherAddress = mysql_fetch_assoc($resPriseDepuis)) {
                $otherAddress = $this->a->getArrayAdresseFromIdAdresse($otherAddress['idAdresse']);
                $otherAddressName = $this->getAddressName($otherAddress['idAdresse']);
                $otherAddresses[] = ' [[Adresse:'.$otherAddressName.'|'.$otherAddressName.']]';
            }
            $fetchPhotos['description'] .= implode(', ', $otherAddresses);
            $imagesFromInfo[] = $fetchPhotos;
        }
        if (!empty($imagesFromInfo)) {
            $imagesFrom = PHP_EOL.'== Vues prises depuis cette adresse =='.PHP_EOL.
                $this->createGallery($imagesFromInfo, false);
        }

        return $imagesFrom;
    }

    /**
     * @param string $pageName
     */
    private function exportInfobox(array $address, array $infobox, $pageName, $groupId)
    {
        $intro = '{{Infobox adresse'.PHP_EOL;

        $txtAdresses = Address::getFullAddressName($address);

        $intro .= '|pays = '.$address['nomPays'].PHP_EOL;
        $intro .= '|ville = '.$address['nomVille'].PHP_EOL;
        $resAddressGroup = $this->a->getAdressesFromEvenementGroupeAdresses($groupId);
        $addresses = [];
        while ($fetchAddressGroup = mysql_fetch_assoc($resAddressGroup)) {
            $addresses[] = $fetchAddressGroup;
        }
        foreach ($addresses as $i => $subAddress) {
            $intro .= '|complément_rue'.($i + 1).' = '.$subAddress['prefixeRue'].PHP_EOL;
            $intro .= '|rue'.($i + 1).' = '.$subAddress['nomRue'].PHP_EOL;
            if ($subAddress['numero'] > 0) {
                $intro .= '|numéro'.($i + 1).' = '.$subAddress['numero'].PHP_EOL;
            }
            if ($subAddress['longitude'] > 0 && $subAddress['latitude'] > 0) {
                $intro .= '|longitude'.($i + 1).' = '.$subAddress['longitude'].PHP_EOL;
                $intro .= '|latitude'.($i + 1).' = '.$subAddress['latitude'].PHP_EOL;
            }
        }
        if (isset($txtAdresses)) {
            $intro .= '|nom_complet = '.$txtAdresses.PHP_EOL;
        }

        $intro .= Infobox::getNumberedInfo($infobox);

        $mainImageInfo = $this->i->getArrayInfosImagePrincipaleFromIdGroupeAdresse(
            [
                'idEvenementGroupeAdresse' => $groupId,
                'format'                   => 'grand',
            ]
        );
        if (!$mainImageInfo['trouve']) {
            $mainImageInfo = $this->a->getUrlImageFromAdresse(
                $address['idAdresse'],
                'grand',
                ['idEvenementGroupeAdresse'=> $groupId]
            );
            $reqImages = "
                SELECT idImage
                FROM historiqueImage
                WHERE idHistoriqueImage = '".mysql_real_escape_string($mainImageInfo['idHistoriqueImage'])."'
                ";

            $resImages = $this->i->connexionBdd->requete($reqImages);
            $mainImageInfo = mysql_fetch_assoc($resImages);
        }
        if (!$this->input->getOption('noimage')) {
            $command = $this->getApplication()->find('export:image');
            $command->run(
                new ArrayInput(['id' => $mainImageInfo['idImage']]),
                $this->output
            );
        }
        $filename = $this->getImageName($mainImageInfo['idImage']);
        $intro .= '|photo_principale = '.$filename.PHP_EOL;
        $intro .= '}}'.PHP_EOL.PHP_EOL;
        $this->sections[0] = $intro;

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

        return $intro;
    }

    /**
     * @param string $pageName
     */
    private function exportComments(array $events, array $address, $pageName, $groupId)
    {
        $comments = [];

        foreach ($events as $section => $id) {
            $reqEventsComments = "SELECT c.idCommentairesEvenement as idCommentaire, c.nom as nom,
            c.prenom as prenom,c.email as email,DATE_FORMAT(c.date,'"._('%d/%m/%Y à %kh%i')."') as dateF,
            c.commentaire as commentaire,c.idUtilisateur as idUtilisateur
                     ,date_format( c.date, '%Y%m%d%H%i%s' ) AS dateTri
                    FROM commentairesEvenement c
                    LEFT JOIN utilisateur u ON u.idUtilisateur = c.idUtilisateur
                    WHERE c.idEvenement = '".$id."'
                    AND CommentaireValide=1
                    ORDER BY DateTri ASC
                    ";
            $resEventsComments = $this->a->connexionBdd->requete($reqEventsComments);
            while ($comment = mysql_fetch_assoc($resEventsComments)) {
                $comments[] = $comment;
            }
        }

        $reqComments = "SELECT c.idCommentaire as idCommentaire, c.nom as nom, c.prenom as prenom,
        c.email as email,DATE_FORMAT(c.date,'"._('%d/%m/%Y à %kh%i')."') as dateF,
        c.commentaire as commentaire,c.idUtilisateur as idUtilisateur, u.urlSiteWeb as urlSiteWeb
                 ,date_format( c.date, '%Y%m%d%H%i%s' ) AS dateTri
                FROM commentaires c
                LEFT JOIN utilisateur u ON u.idUtilisateur = c.idUtilisateur
                WHERE c.idEvenementGroupeAdresse = '".$groupId."'
                        AND CommentaireValide=1
                        ORDER BY DateTri ASC
                        ";

        $resComments = $this->a->connexionBdd->requete($reqComments);
        $pageID = $this->services->newPageGetter()->getFromTitle($pageName)->getID();
        while ($comment = mysql_fetch_assoc($resComments)) {
            $comments[] = $comment;
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
    }

    /**
     * @param string $pageName
     */
    private function exportEvents(array $events, $pageName, array $address, $groupId)
    {
        global $config;

        $content = '';
        $infobox = [];
        $this->sections = [];

        $this->output->writeln('<info>Exporting "'.$pageName.'"…</info>');

        $isNews = $this->services->newPageGetter()->getFromTitle($pageName)
            ->getPageIdentifier()->getTitle()->getNs() == 4100;

        $this->loginManager->loginAsAdmin();

        $this->pageSaver->deletePage($pageName);

        //Create page structure
        foreach ($events as $id) {
            $event = $this->getEventInfo($id);
            $content .= '== '.$this->getEventTitle($event).' =='.PHP_EOL;
            $infobox[] = Infobox::getInfoboxInfo($event);
        }

        if (!$isNews) {
            //Vues sur
            $otherImages = $this->getOtherImages($address, $groupId);
            $content .= $otherImages;

            //Vues prises depuis
            $imagesFrom = $this->getImagesFrom($address, $groupId);
            $content .= $imagesFrom;
        }

        //Add References section
        $references = PHP_EOL.'== Références =='.PHP_EOL.'<references />'.PHP_EOL;
        $content .= $references;

        //Login as bot
        $this->loginManager->login('aw2mw bot');

        $this->pageSaver->savePage($pageName, $content, 'Sections importées depuis Archi-Wiki');

        if (!$isNews) {
            $this->sections[0] = $this->exportInfobox($address, $infobox, $pageName, $groupId);
        }

        foreach ($events as $section => $id) {
            $this->exportEvent($id, $section, $pageName);
        }

        if (!$isNews) {
            $this->sections[] = $otherImages;
            $this->sections[] = $imagesFrom;
        }
        $this->sections[] = $references;

        if (!$isNews) {
            $this->exportComments($events, $address, $pageName, $groupId);
        }

        //Login with bot
        $this->loginManager->login('aw2mw bot');

        $content = implode('', $this->sections);

        //Replace <u/> with ===
        $content = $this->replaceSubtitles($content);
        $this->pageSaver->savePage($pageName, $content, 'Conversion des titres de section');

        $content = $this->replaceSourceLists($content);
        $this->pageSaver->savePage($pageName, $content, 'Conversion des listes de sources');

        $content = $this->replaceRelatedLinks($content, $events);
        $this->pageSaver->savePage($pageName, $content, 'Conversion des annexes');

        //$content = '<translate>'.PHP_EOL.$content.PHP_EOL.'</translate>';
        //$this->pageSaver->savePage($pageName, $content, 'Ajout des balises de traduction');
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
        $this->allEvents = [];

        global $config;
        $config = new \ArchiConfig();

        $address = $this->a->getArrayAdresseFromIdAdresse($this->input->getArgument('id'));
        if (!$address) {
            $this->output->writeln('<error>Adresse introuvable</error>');

            return;
        }

        $groupId = $this->input->getArgument('groupId');
        if (!isset($groupId)) {
            $groupInfo = mysql_fetch_assoc($this->a->getIdEvenementsFromAdresse($this->input->getArgument('id')));

            if (!$groupInfo) {
                throw new \Exception("Can't find this address");
            }
            $groupId = $groupInfo['idEvenementGroupeAdresse'];
        }

        $basePageName = $this->getAddressName($this->input->getArgument('id'), $groupId);

        $pageName = 'Adresse:'.$basePageName;

        $events = [];
        $newsEvents = [];

        $requete = '
            SELECT DISTINCT evt.idEvenement, pe.position, te.nom as type, ISMH, MH
            FROM evenements evt
            LEFT JOIN _evenementEvenement ee on ee.idEvenement = '.
                mysql_real_escape_string($groupId).
            '
            LEFT JOIN positionsEvenements pe on pe.idEvenement = ee.idEvenementAssocie
            LEFT JOIN typeEvenement te on te.idTypeEvenement = evt.idTypeEvenement
            WHERE evt.idEvenement = ee.idEvenementAssocie
            ORDER BY pe.position ASC
            ';
        $result = $this->e->connexionBdd->requete($requete);
        while ($res = mysql_fetch_assoc($result)) {
            $isNews = true;
            if (mysql_num_rows($result) <= 5) {
                $isNews = false;
            } else {
                if (in_array($res['type'], Infobox::CONSTRUCTION_EVENTS_TYPE)) {
                    $isNews = false;
                } elseif ($res['ISMH'] > 0 || $res['MH'] > 0) {
                    $isNews = false;
                } else {
                    $rep = $this->e->connexionBdd->requete('
                            SELECT  p.idPersonne
                            FROM _evenementPersonne _eP
                            LEFT JOIN personne p ON p.idPersonne = _eP.idPersonne
                            LEFT JOIN metier m ON m.idMetier = p.idMetier
                            WHERE _eP.idEvenement='.mysql_real_escape_string($res['idEvenement']).'
                            ORDER BY p.nom DESC');
                    $resPerson = mysql_fetch_object($rep);
                    if ($resPerson) {
                        $isNews = false;
                    }
                }
            }
            if ($isNews) {
                $newsEvents[] = $res['idEvenement'];
            } else {
                $events[] = $res['idEvenement'];
            }
            $this->allEvents[] = $res['idEvenement'];
        }

        $this->exportEvents($events, $pageName, $address, $groupId);
        $this->exportEvents($newsEvents, 'Actualités_adresse:'.$basePageName, $address, $groupId);
    }
}
