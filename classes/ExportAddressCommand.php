<?php

namespace AW2MW;

use Mediawiki\Api;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportAddressCommand extends ExportCommand
{
    const CONSTRUCTION_EVENTS_TYPE = [
        'Construction', 'Rénovation', 'Transformation', 'Démolition', 'Extension', 'Ravalement',
    ];

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
            )->addOption(
                'noimage',
                null,
                InputOption::VALUE_NONE,
                "Don't upload images"
            );
    }

    /**
     * @param string $pageName
     */
    private function exportEvents($events, $pageName, $address)
    {
        global $config;

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

            $event = mysql_fetch_assoc($res);

            if (!empty($event['titre'])) {
                $title = $event['titre'];
            } else {
                $title = substr($event['dateDebut'], 0, 4);
            }
            $title = stripslashes($title);
            $title = trim($title, '.');
            $content .= '== '.$title.' =='.PHP_EOL;

            $people = $this->getPeople($id);

            $info = [
                'type'      => '',
                'structure' => '',
                'date'      => '',
                'people'    => [],
            ];

            $info['type'] = str_replace('(Nouveautés)', '', $event['nomTypeEvenement']);
            $info['structure'] = $event['nomTypeStructure'];
            $info['date'] = [
                'pretty' => $this->convertDate($event['dateDebut'], $event['dateFin'], $event['isDateDebutEnviron']),
                'start'  => $event['dateDebut'],
                'end'    => $event['dateFin'],
            ];
            if ($event['ISMH'] > 0) {
                $info['ismh'] = true;
            }
            if ($event['MH'] > 0) {
                $info['mh'] = true;
            }
            $info['courant'] = $event['courantArchitectural'];

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
            //Vues sur
            $reqPhotos = '
                SELECT hi1.idHistoriqueImage, hi1.idImage as idImage,
                hi1.dateUpload, ai.idAdresse, hi1.description,
                ae.idEvenement as idEvenementGroupeAdresseCourant
                FROM historiqueImage hi2,  historiqueImage hi1
                LEFT JOIN _adresseImage ai ON ai.idImage = hi1.idImage
                LEFT JOIN _adresseEvenement ae ON ae.idAdresse = ai.idAdresse
                WHERE hi2.idImage = hi1.idImage
                AND ai.idAdresse = '.mysql_real_escape_string($address['idAdresse'])."
                AND ai.vueSur='1'
                GROUP BY hi1.idImage,  hi1.idHistoriqueImage
                HAVING hi1.idHistoriqueImage = max(hi2.idHistoriqueImage)
            ";
            $resPhotos = $this->i->connexionBdd->requete($reqPhotos);

            $otherImagesInfo = [];
            $otherImages = '';
            $linkedImages = $this->e->getArrayCorrespondancesIdImageVuesSurAndEvenementByDateFromGA(
                $this->a->getIdEvenementGroupeAdresseFromIdAdresse($address['idAdresse'])
            );
            while ($fetchPhotos = mysql_fetch_assoc($resPhotos)) {
                foreach ($linkedImages as $linkedImageGroup) {
                    foreach ($linkedImageGroup as $linkedImage) {
                        if ($linkedImage['idImage'] == $fetchPhotos['idImage']) {
                            continue 3;
                        }
                    }
                }
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
                    $this->createGallery($otherImagesInfo, false);
                $content .= $otherImages;
            }

            //Vues prises depuis
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
                $content .= $imagesFrom;
            }
        }

        //Add References section
        $references = PHP_EOL.'== Références =='.PHP_EOL.'<references />'.PHP_EOL;
        $content .= $references;

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
            $j = $k = $l = 0;
            foreach ($infobox as $i => $info) {
                if (in_array($info['type'], self::CONSTRUCTION_EVENTS_TYPE)) {
                    $j++;
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
                    $intro .= '|date'.$j.'_afficher = '.$info['date']['pretty'].PHP_EOL;
                    $intro .= '|date'.$j.'_début = '.$info['date']['start'].PHP_EOL;
                    $intro .= '|date'.$j.'_fin = '.$info['date']['end'].PHP_EOL;
                    if ($i > 0 && $info['structure'] == $infobox[$i - 1]['structure']) {
                        $info['structure'] = '';
                    }
                    $intro .= '|structure'.$j.' = '.$info['structure'].PHP_EOL;
                    $intro .= '|type'.$j.' = '.strtolower($info['type']).PHP_EOL;
                    $intro .= '|courant'.$j.' = '.strtolower($info['courant']).PHP_EOL;
                    foreach ($info['people'] as $job => $name) {
                        $intro .= '|'.$job.$j.' = '.$name.PHP_EOL;
                    }
                }
                if (isset($info['ismh'])) {
                    $k++;
                    $intro .= '|ismh'.$k.'='.$info['date']['pretty'].PHP_EOL;
                }
                if (isset($info['mh'])) {
                    $l++;
                    $intro .= '|mh'.$l.'='.$info['date']['pretty'].PHP_EOL;
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
                    $sourceName = $this->getSourceName($event['idSource']);
                    $title .= '<ref>{{source|'.$sourceName.'}}</ref>';
                }
                if (!empty($event['numeroArchive'])) {
                    $sourceName = $this->getSourceName(24);
                    $title .= '<ref>{{source|'.$sourceName.'}} - Cote '.
                        $event['numeroArchive'].'</ref>';
                }

                $title = ucfirst(stripslashes($title));
                $content .= '== '.$title.' =='.PHP_EOL;

                $people = [];
                foreach ($this->getPeople($id) as $person) {
                    if (isset($people[$person->metier]) && !empty($people[$person->metier])) {
                        $people[$person->metier] .= ';'.$person->prenom.' '.$person->nom;
                    } else {
                        $people[$person->metier] = $person->prenom.' '.$person->nom;
                    }
                }
                $content .= '{{Infobox actualité'.PHP_EOL.
                    '|date = '.$date.PHP_EOL;
                foreach ($people as $job => $person) {
                    $content .= '|'.$job.' = '.$person.PHP_EOL;
                }
                if ($event['ISMH'] > 0) {
                    '|ismh = oui'.PHP_EOL;
                }
                if ($event['MH'] > 0) {
                    '|mh = oui'.PHP_EOL;
                }
                $content .= '}}'.PHP_EOL;

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
            $sections[] = $imagesFrom;
        }
        $sections[] = $references;

        if (!$isNews) {
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
                            'token'       => $this->api->getToken(),
                        ]
                    )
                );
                //This is to make sure comments are posted in the right order
                sleep(1);
            }
        }

        //Login with bot
        $this->login('aw2mw bot');

        $content = implode('', $sections);

        //Replace <u/> with ===
        $content = $this->replaceSubtitles($content);
        $this->savePage($pageName, $content, 'Conversion des titres de section');

        $content = $this->replaceSourceLists($content);
        $this->savePage($pageName, $content, 'Conversion des listes de sources');

        $content = $this->replaceRelatedLinks($content, $events);
        $this->savePage($pageName, $content, 'Conversion des annexes');

        //$content = '<translate>'.PHP_EOL.$content.PHP_EOL.'</translate>';
        //$this->savePage($pageName, $content, 'Ajout des balises de traduction');
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

        $basePageName = $this->getAddressName($this->input->getArgument('id'));

        $pageName = 'Adresse:'.$basePageName;

        $groupInfo = mysql_fetch_assoc($this->a->getIdEvenementsFromAdresse($this->input->getArgument('id')));

        if (!$groupInfo) {
            throw new \Exception("Can't find this address");
        }

        $events = [];
        $newsEvents = [];

        $requete = '
            SELECT DISTINCT evt.idEvenement, pe.position, te.nom as type, ISMH, MH
            FROM evenements evt
            LEFT JOIN _evenementEvenement ee on ee.idEvenement = '.
                mysql_real_escape_string($groupInfo['idEvenementGroupeAdresse']).
            '
            LEFT JOIN positionsEvenements pe on pe.idEvenement = ee.idEvenementAssocie
            LEFT JOIN typeEvenement te on te.idTypeEvenement = evt.idTypeEvenement
            WHERE evt.idEvenement = ee.idEvenementAssocie
            ORDER BY pe.position ASC
            ';
        $result = $this->e->connexionBdd->requete($requete);
        $arrayIdEvenement = [];
        while ($res = mysql_fetch_assoc($result)) {
            $isNews = true;
            if (mysql_num_rows($result) <= 5) {
                $isNews = false;
            } else {
                if (in_array($res['type'], self::CONSTRUCTION_EVENTS_TYPE)) {
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

        $this->exportEvents($events, $pageName, $address);
        $this->exportEvents($newsEvents, 'Actualités_adresse:'.$basePageName, $address);
    }

    private function getPeople($id)
    {
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

        return $people;
    }
}
