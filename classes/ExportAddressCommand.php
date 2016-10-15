<?php

namespace AW2MW;

use Mediawiki\Api;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExportAddressCommand extends ExportCommand
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
            ->setName('export:address')
            ->setDescription('Export one specific address')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'Address ID'
            );
    }

    private function createGallery($images)
    {
        $return = '<gallery>'.PHP_EOL;
        foreach ($images as $image) {
            $command = $this->getApplication()->find('export:image');
            $command->run(
                new ArrayInput(['id' => $image['idImage']]),
                $this->output
            );
            $filename = $this->getImageName($image['idImage']);
            $description = str_replace(
                PHP_EOL,
                ' ',
                strip_tags(
                    $this->convertHtml(
                        (string) $this->bbCode->convertToDisplay(['text' => $image['description']])
                    )
                )
            );
            $return .= 'File:'.$filename.'|'.$description.PHP_EOL;
        }
        $return .= '</gallery>'.PHP_EOL;

        return $return;
    }

    /**
     * @param string $pageName
     */
    private function exportEvents($events, $pageName, $address)
    {
        $content = '';
        $infobox = [];
        $sections = [];

        $this->output->writeln('<info>Exporting "'.$pageName.'"…</info>');

        $isNews = $this->services->newPageGetter()->getFromTitle($pageName)->getTitle()->getNs() == 4100;

        $this->loginAsAdmin();

        $this->deletePage($pageName);

        //Create page structure
        foreach ($events as $id) {
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
                    WHERE hE.idEvenement = '.mysql_real_escape_string($id).'
            ORDER BY hE.idHistoriqueEvenement DESC';

            $res = $this->e->connexionBdd->requete($sql);

            $event = mysql_fetch_assoc($res);

            if (!empty($event['titre'])) {
                $title = $event['titre'];
            } else {
                $title = substr($event['dateDebut'], 0, 4);
            }
            $title = stripslashes($title);
            $title = trim($title, '.');
            $content .= '=='.$title.'=='.PHP_EOL;

            $rep = $this->e->connexionBdd->requete('
                    SELECT  p.idPersonne, m.nom as metier, p.nom, p.prenom
                    FROM _evenementPersonne _eP
                    LEFT JOIN personne p ON p.idPersonne = _eP.idPersonne
                    LEFT JOIN metier m ON m.idMetier = p.idMetier
                    WHERE _eP.idEvenement='.mysql_real_escape_string($id).'
                    ORDER BY p.nom DESC');
            $people = [];
            while ($res = mysql_fetch_object($rep)) {
                $people[] = $res;
            }

            $info = [
                'type'      => '',
                'structure' => '',
                'date'      => '',
                'people'    => [
                    'architecte' => '',
                ],
            ];

            $info['type'] = str_replace('(Nouveautés)', '', $event['nomTypeEvenement']);
            $info['structure'] = $event['nomTypeStructure'];
            $info['date'] = [
                'pretty' => $this->convertDate($event['dateDebut'], $event['dateFin'], $event['isDateDebutEnviron']),
                'start'  => $event['dateDebut'],
                'end'    => $event['dateFin'],
            ];

            foreach ($people as $person) {
                if (isset($info['people'][$person->metier]) && !empty($info['people'][$person->metier])) {
                    $info['people'][$person->metier] .= ';'.$person->prenom.' '.$person->nom;
                } else {
                    $info['people'][$person->metier] = $person->prenom.' '.$person->nom;
                }
            }

            $infobox[] = $info;
        }

        if (!$isNews) {
            $reqPhotos = '
                SELECT hi1.idHistoriqueImage, hi1.idImage as idImage,
                hi1.dateUpload, ai.idAdresse, hi1.description,
                ae.idEvenement as idEvenementGroupeAdresseCourant
                FROM historiqueImage hi2,  historiqueImage hi1
                LEFT JOIN _adresseImage ai ON ai.idImage = hi1.idImage
                LEFT JOIN _adresseEvenement ae ON ae.idAdresse = ai.idAdresse
                WHERE hi2.idImage = hi1.idImage
                AND ai.idAdresse = '.mysql_real_escape_string($address['idAdresse'])."
                AND ai.prisDepuis='1'
                GROUP BY hi1.idImage,  hi1.idHistoriqueImage
                HAVING hi1.idHistoriqueImage = max(hi2.idHistoriqueImage)
            ";

            $resPhotos = $this->i->connexionBdd->requete($reqPhotos);

            $otherImagesInfo = [];
            $otherImages = '';
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
                $otherImagesInfo[] = $fetchPhotos;
            }
            if (!empty($otherImagesInfo)) {
                $otherImages = PHP_EOL.'==Vues prises depuis cette adresse=='.PHP_EOL.
                    $this->createGallery($otherImagesInfo);
                $content .= $otherImages;
            }

            //Add References section
            $references = PHP_EOL.'==Références=='.PHP_EOL.'<references />'.PHP_EOL;
            $content .= $references;
        }


        //Login as bot
        $this->login('aw2mw bot');

        $this->savePage($pageName, $content, 'Sections importées depuis Archi-Wiki');

        if (!$isNews) {
            $intro = '{{Infobox adresse'.PHP_EOL;

            $idEvenementGroupeAdresse = $this->a->getIdEvenementGroupeAdresseFromIdAdresse($address['idAdresse']);

            $reqAdresseDuGroupeAdresse = "
                SELECT ha1.idAdresse as idAdresse,ha1.numero as numero,
                ha1.idRue as idRue, IF(ha1.idIndicatif='0','',i.nom)
                as nomIndicatif, ha1.idQuartier as idQuartier, ha1.idSousQuartier as idSousQuartier
                FROM historiqueAdresse ha2, historiqueAdresse ha1
                LEFT JOIN _adresseEvenement ae ON ae.idAdresse = ha1.idAdresse
                LEFT JOIN indicatif i ON i.idIndicatif = ha1.idIndicatif
                WHERE ha2.idAdresse = ha1.idAdresse
                AND ae.idEvenement ='".mysql_real_escape_string($idEvenementGroupeAdresse)."'

                GROUP BY ha1.idAdresse, ha1.idHistoriqueAdresse
                HAVING ha1.idHistoriqueAdresse = max(ha2.idHistoriqueAdresse)
                ORDER BY ha1.numero,ha1.idRue
            ";

            $resAdresseDuGroupeAdresse = $this->a->connexionBdd->requete($reqAdresseDuGroupeAdresse);

            if (mysql_num_rows($resAdresseDuGroupeAdresse) > 1) {
                $txtAdresses = '';
                $arrayNumero = [];
                while ($fetchAdressesGroupeAdresse = mysql_fetch_assoc($resAdresseDuGroupeAdresse)) {
                    $isAdresseCourante = false;
                    if ($address['idAdresse'] == $fetchAdressesGroupeAdresse['idAdresse']) {
                        $isAdresseCourante = true;
                    }

                    if ($fetchAdressesGroupeAdresse['idRue'] == '0' || $fetchAdressesGroupeAdresse['idRue'] == '') {
                        if ($fetchAdressesGroupeAdresse['idQuartier'] != ''
                            && $fetchAdressesGroupeAdresse['idQuartier'] != '0'
                        ) {
                            $arrayNumero[$this->a->getIntituleAdresseFrom(
                                $fetchAdressesGroupeAdresse['idAdresse'],
                                'idAdresse',
                                ['noSousQuartier' => true, 'noQuartier' => false, 'noVille' => true]
                            )][] =
                                [
                                    'indicatif'         => $fetchAdressesGroupeAdresse['nomIndicatif'],
                                    'numero'            => $fetchAdressesGroupeAdresse['numero'],
                                    'isAdresseCourante' => $isAdresseCourante,
                                ];
                        }

                        if ($fetchAdressesGroupeAdresse['idSousQuartier'] != ''
                            && $fetchAdressesGroupeAdresse['idSousQuartier'] != '0'
                        ) {
                            $arrayNumero[$this->a->getIntituleAdresseFrom(
                                $fetchAdressesGroupeAdresse['idAdresse'],
                                'idAdresse',
                                ['noSousQuartier' => false, 'noQuartier' => true, 'noVille' => true]
                            )][] =
                                [
                                    'indicatif'         => $fetchAdressesGroupeAdresse['nomIndicatif'],
                                    'numero'            => $fetchAdressesGroupeAdresse['numero'],
                                    'isAdresseCourante' => $isAdresseCourante,
                                ];
                        }
                    } else {
                        $arrayNumero[$this->a->getIntituleAdresseFrom(
                            $fetchAdressesGroupeAdresse['idRue'],
                            'idRueWithNoNumeroAuthorized',
                            ['noSousQuartier' => true, 'noQuartier' => true, 'noVille' => true]
                        )][] =
                            [
                                'indicatif'         => $fetchAdressesGroupeAdresse['nomIndicatif'],
                                'numero'            => $fetchAdressesGroupeAdresse['numero'],
                                'isAdresseCourante' => $isAdresseCourante,
                            ];
                    }
                }

                // affichage adresses regroupees
                foreach ($arrayNumero as $intituleRue => $arrayInfosNumero) {
                    foreach ($arrayInfosNumero as $indice => $infosNumero) {
                        if ($infosNumero['numero'] == '' || $infosNumero['numero'] == '0') {
                            //rien
                        } else {
                            $txtAdresses .= $infosNumero['numero'].$infosNumero['indicatif'].'-';
                        }
                    }

                    $txtAdresses = trim($txtAdresses, '-');

                    $txtAdresses .= $intituleRue.', ';
                }
                $txtAdresses = trim($txtAdresses, ', ');
            }

            $intro .= '|pays = '.$address['nomPays'].PHP_EOL;
            $intro .= '|ville = '.$address['nomVille'].PHP_EOL;
            $resAddressGroup = $this->a->getAdressesFromEvenementGroupeAdresses(
                $this->a->getIdEvenementGroupeAdresseFromIdAdresse($address['idAdresse'])
            );
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
            foreach ($infobox as $i => $info) {
                if (substr($info['date']['start'], 5) == '00-00') {
                    $info['date']['start'] = substr($info['date']['start'], 0, 4);
                }
                if (substr($info['date']['end'], 5) == '00-00') {
                    $info['date']['end'] = substr($info['date']['end'], 0, 4);
                }
                if ($info['date']['start'] == '0000') {
                    $info['date']['start'] = '';
                }
                if ($info['date']['end'] == '0000') {
                    $info['date']['end'] = '';
                }
                $intro .= '|date'.($i + 1).'_afficher = '.$info['date']['pretty'].PHP_EOL;
                $intro .= '|date'.($i + 1).'_début = '.$info['date']['start'].PHP_EOL;
                $intro .= '|date'.($i + 1).'_fin = '.$info['date']['end'].PHP_EOL;
                if ($i > 0 && $info['structure'] == $infobox[$i - 1]['structure']) {
                    $info['structure'] = '';
                }
                $intro .= '|structure'.($i + 1).' = '.$info['structure'].PHP_EOL;
                $intro .= '|type'.($i + 1).' = '.strtolower($info['type']).PHP_EOL;
                foreach ($info['people'] as $job => $name) {
                    $intro .= '|'.$job.($i + 1).' = '.$name.PHP_EOL;
                }
            }
            $mainImageInfo = $this->i->getArrayInfosImagePrincipaleFromIdGroupeAdresse(
                [
                    'idEvenementGroupeAdresse' => $this->a->getIdEvenementGroupeAdresseFromIdAdresse(
                        $address['idAdresse']
                    ),
                    'format'                   => 'grand',
                ]
            );
            if (!$mainImageInfo['trouve']) {
                $mainImageInfo = $this->a->getUrlImageFromAdresse($address['idAdresse']);
                $reqImages = "
                    SELECT idImage
                    FROM historiqueImage
                    WHERE idHistoriqueImage = '".mysql_real_escape_string($mainImageInfo['idHistoriqueImage'])."'
                    ";

                $resImages = $this->i->connexionBdd->requete($reqImages);
                $mainImageInfo = mysql_fetch_assoc($resImages);
            }
            $command = $this->getApplication()->find('export:image');
            $command->run(
                new ArrayInput(['id' => $mainImageInfo['idImage']]),
                $this->output
            );
            $filename = $this->getImageName($mainImageInfo['idImage']);
            $intro .= '|photo = '.$filename.PHP_EOL;
            $intro .= '}}'.PHP_EOL.PHP_EOL;
            $sections[0] = $intro;

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
        }

        foreach ($events as $section => $id) {
            $req = 'SELECT idHistoriqueEvenement
                    FROM historiqueEvenement
                    WHERE idEvenement='.$id.' order by dateCreationEvenement ASC';
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

                $event = mysql_fetch_assoc($this->e->connexionBdd->requete($sql));

                $user = $this->u->getArrayInfosFromUtilisateur($event['idUtilisateur']);

                //Login as user
                $this->login($user['prenom'].' '.$user['nom']);


                $content = '';
                $date = $this->convertDate($event['dateDebut'], $event['dateFin'], $event['isDateDebutEnviron']);

                if (!empty($event['titre'])) {
                    $title = $event['titre'];
                } else {
                    $title = str_replace('(Nouveautés)', '', $event['nomTypeEvenement']);
                }

                if ($event['idSource'] > 0) {
                    $sourceName = $this->escapeSourceName($this->s->getSourceLibelle($event['idSource']));
                    $title .= '<ref>[[Source::Source:'.$sourceName.'|'.$sourceName.']]</ref>';
                }
                if (!empty($event['numeroArchive'])) {
                    $sourceName = $this->escapeSourceName($this->s->getSourceLibelle(24));
                    $title .= '<ref>[[Source::Source:'.$sourceName.'|'.$sourceName.']] - Cote '.
                        $event['numeroArchive'].'</ref>';
                }

                $title = ucfirst(stripslashes($title));
                $content .= '=='.$title.'=='.PHP_EOL;

                if ($isNews) {
                    $content .= '{{Infobox actualité'.PHP_EOL.
                    '|date = '.$date.PHP_EOL.
                    '}}'.PHP_EOL;
                }

                $html = $this->convertHtml(
                    (string) $this->bbCode->convertToDisplay(['text' => $event['description']])
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
                            'summary' => 'Révision du '.$event['dateCreationEvenement'].' importée depuis Archi-Wiki',
                            'token'   => $this->api->getToken(),
                        ]
                    )
                );
                $sections[$section + 1] = $content;
            }
            $reqImages = "
                SELECT hi1.idImage, hi1.description
                FROM _evenementImage ei
                LEFT JOIN historiqueImage hi1 ON hi1.idImage = ei.idImage
                LEFT JOIN historiqueImage hi2 ON hi2.idImage = hi1.idImage
                WHERE ei.idEvenement = '".mysql_real_escape_string($id)."'
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
                $sections[$section + 1] .= $this->createGallery($images);
            }
            $this->api->postRequest(
                new Api\SimpleRequest(
                    'edit',
                    [
                        'title'   => $pageName,
                        'md5'     => md5($sections[$section + 1]),
                        'text'    => $sections[$section + 1],
                        'section' => $section + 1,
                        'bot'     => true,
                        'summary' => 'Images importées depuis Archi-Wiki',
                        'token'   => $this->api->getToken(),
                    ]
                )
            );
        }

        if (!$isNews) {
            $sections[] = $otherImages;
            $sections[] = $references;
        }


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
        		WHERE c.idEvenementGroupeAdresse = '".
                    $this->a->getIdEvenementGroupeAdresseFromIdAdresse($address['idAdresse'])."'
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
            $this->login($comment['prenom'].' '.$comment['nom']);
            $this->api->postRequest(
                new Api\SimpleRequest(
                    'commentsubmit',
                    [
                        'pageID'      => $pageID,
                        'parentID'    => 0,
                        'commentText' => $this->convertHtml(
                            (string) $this->bbCode->convertToDisplay(['text' => $comment['commentaire']])
                        ),
                    ]
                )
            );
            //This is to make sure comments are posted in the right order
            sleep(1);
        }

        //Login with bot
        $this->login('aw2mw bot');

        $content = implode('', $sections);

        //Replace <u/> with ===
        $content = $this->replaceSubtitles($content);

        $this->savePage($pageName, $content, 'Conversion des titres de section');
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

        $address = $this->a->getArrayAdresseFromIdAdresse($input->getArgument('id'));
        if (!$address) {
            $this->output->writeln('<error>Adresse introuvable</error>');

            return;
        }
        $city = $this->a->getInfosVille($address['idVille']);

        $basePageName = $this->getAddressName($input->getArgument('id'));

        $pageName = 'Adresse:'.$basePageName;

        $groupInfo = mysql_fetch_assoc($this->a->getIdEvenementsFromAdresse($input->getArgument('id')));

        $events = [];
        $newsEvents = [];

        $requete = '
            SELECT DISTINCT evt.idEvenement, pe.position
            FROM evenements evt
            LEFT JOIN _evenementEvenement ee on ee.idEvenement = '.
                mysql_real_escape_string($groupInfo['idEvenementGroupeAdresse']).
            '
            LEFT JOIN positionsEvenements pe on pe.idEvenement = ee.idEvenementAssocie
            WHERE evt.idEvenement = ee.idEvenementAssocie
            ORDER BY pe.position ASC
            ';
        $result = $this->e->connexionBdd->requete($requete);
        $arrayIdEvenement = [];
        while ($res = mysql_fetch_assoc($result)) {
            $allEvents[] = $res['idEvenement'];
        }

        foreach ($allEvents as $id) {
            $rep = $this->e->connexionBdd->requete('
                    SELECT  p.idPersonne
                    FROM _evenementPersonne _eP
                    LEFT JOIN personne p ON p.idPersonne = _eP.idPersonne
                    LEFT JOIN metier m ON m.idMetier = p.idMetier
                    WHERE _eP.idEvenement='.mysql_real_escape_string($id).'
                    ORDER BY p.nom DESC');
            $people = [];
            while ($res = mysql_fetch_object($rep)) {
                $people[] = $res;
            }
            if (!empty($people)) {
                $events[] = $id;
            } else {
                $newsEvents[] = $id;
            }
        }

        $this->exportEvents($events, $pageName, $address);
        $this->exportEvents($newsEvents, 'Actualités_adresse:'.$basePageName, $address);
    }
}
