<?php
namespace AW2MW;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Mediawiki\Api;
use Mediawiki\DataModel;
use AW2MW\Config;

class ExportAddressCommand extends ExportCommand
{
    /**
     * Configure command
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('export:address')
            ->setDescription('Export one specific address')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'Address ID'
            );
    }

    /**
     * Execute command
     *
     * @param InputInterface  $input  Input
     * @param OutputInterface $output Output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::setup();

        $address = $this->a->getArrayAdresseFromIdAdresse($input->getArgument('id'));
        if (!$address) {
            $output->writeln('<error>Adresse introuvable</error>');
            return;
        }
        $city = $this->a->getInfosVille($address['idVille']);

        $basePageName = strip_tags(
            $this->a->getIntituleAdresseFrom(
                $input->getArgument('id'),
                'idAdresse',
                array(
                    'noHTML'=>true, 'noQuartier'=>true, 'noSousQuartier'=>true, 'noVille'=>true,
                    'displayFirstTitreAdresse'=>true,
                    'setSeparatorAfterTitle'=>'_'
                )
            )
        ).'_('.$address['nomVille'].')';

        $pageName = 'Adresse:'.$basePageName;

        $output->writeln('<info>Exporting "'.$pageName.'"…</info>');

        $this->loginAsAdmin();

        $this->deletePage($pageName);

        $groupInfo = mysql_fetch_assoc($this->a->getIdEvenementsFromAdresse($input->getArgument('id')));

        $events = array();
        $requete ="
            SELECT evt.idEvenement, pe.idEvenement, pe.position
            FROM evenements evt
            LEFT JOIN _evenementEvenement ee on ee.idEvenement = ".
                mysql_real_escape_string($groupInfo['idEvenementGroupeAdresse']).
            "
            LEFT JOIN positionsEvenements pe on pe.idEvenement = ee.idEvenementAssocie
            WHERE evt.idEvenement = ee.idEvenementAssocie
            ORDER BY pe.position ASC
            ";
        $result = $this->e->connexionBdd->requete($requete);
        $arrayIdEvenement = array();
        while ($res = mysql_fetch_assoc($result)) {
            $events[] = $res['idEvenement'];
        }

        $content = '';

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
                    hE.ISMH ,
                    hE.MH,
                    date_format(hE.dateCreationEvenement,"'._("%e/%m/%Y à %kh%i").'") as dateCreationEvenement,
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
            $content .= '=='.$title.'=='.PHP_EOL;
        }

        //Add References section
        $references = PHP_EOL.'==Références=='.PHP_EOL.'<references />';
        $content .= $references;


        //Login as bot
        $this->login('aw2mw bot');

        $this->savePage($pageName, $content, 'Sections importées depuis Archi-Wiki');

        $sections = array();

        foreach ($events as $section => $id) {
            $req = "SELECT idHistoriqueEvenement
                    FROM historiqueEvenement
                    WHERE idEvenement=".$id." order by dateCreationEvenement ASC";
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
                        date_format(hE.dateCreationEvenement,"'._("%e/%m/%Y à %kh%i").'") as dateCreationEvenement,
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
                $date = '';
                if ($event['isDateDebutEnviron']=='1') {
                    $date .= "environ ";
                }
                if (substr($event['dateDebut'], 5)=="00-00") {
                    $datetime=substr($event['dateDebut'], 0, 4);
                } else {
                    $datetime = $event['dateDebut'];
                }
                if ($event['dateDebut']!='0000-00-00') {
                    $date .= $this->e->date->toFrenchAffichage($datetime);
                }
                if ($event['dateFin']!='0000-00-00') {
                    if (strlen($this->e->date->toFrench($event['dateFin']))<=4) {
                        $date .= ' à '.$this->e->date->toFrenchAffichage($event['dateFin']);
                    } else {
                        $date .= ' au '.$this->e->date->toFrenchAffichage($event['dateFin']);
                    }
                }
                if (!empty($event['titre'])) {
                    $title = $event['titre'];
                } elseif ($event['dateDebut']!='0000-00-00') {
                    $title = $date;
                } else {
                    $title = $event['nomTypeEvenement'];
                }

                if ($event['idSource'] > 0) {
                    $sourceName = $this->s->getSourceLibelle($event['idSource']);
                    $title .= '<ref>[[Source:'.$sourceName.'|'.$sourceName.']]</ref>';
                }
                if (!empty($event['numeroArchive'])) {
                    $sourceName = $this->s->getSourceLibelle(24);
                    $title .= '<ref>[[Source:'.$sourceName.'|'.$sourceName.']] - Cote '.
                        $event['numeroArchive'].'</ref>';
                }

                $title = ucfirst(stripslashes($title));
                $content .= '=='.$title.'=='.PHP_EOL;

                if (count($date) == 4) {
                    $content .= '|année = '.$date;
                } else {
                    $content .= '|date = '.$date;
                }
                $content .= '}}';

                $html = $this->convertHtml(
                    $this->bbCode->convertToDisplay(array('text'=>$event['description']))
                );


                $content .= trim($html).PHP_EOL;
                $this->api->postRequest(
                    new Api\SimpleRequest(
                        'edit',
                        array(
                            'title'=>$pageName,
                            'md5'=>md5($content),
                            'text'=>$content,
                            'section'=>$section + 1,
                            'bot'=>true,
                            'summary'=>'Révision du '.$event['dateCreationEvenement'].' importée depuis Archi-Wiki',
                            'timestamp'=>0,
                            'token'=>$this->api->getToken()
                        )
                    )
                );
                $sections[$section] = $content;
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
            $images = array();
            while ($fetchImages = mysql_fetch_assoc($resImages)) {
                $images[] = $fetchImages;
            }

            if (!empty($images)) {
                $sections[$section] .= '<gallery>'.PHP_EOL;
                foreach ($images as $image) {
                    $command = $this->getApplication()->find('export:image');
                    $command->run(
                        new ArrayInput(array('id'=>$image['idImage'])),
                        $output
                    );
                    $filename = $image['idImage'].'-import.jpg';
                    $sections[$section] .= 'File:'.$filename.'|'.$image['description'].PHP_EOL;
                }
                $sections[$section] .= '</gallery>'.PHP_EOL;
            }
            $this->api->postRequest(
                new Api\SimpleRequest(
                    'edit',
                    array(
                        'title'=>$pageName,
                        'md5'=>md5($sections[$section]),
                        'text'=>$sections[$section],
                        'section'=>$section + 1,
                        'bot'=>true,
                        'summary'=>'Images importées depuis Archi-Wiki',
                        'timestamp'=>0,
                        'token'=>$this->api->getToken()
                    )
                )
            );
        }

        $sections[] = $references;

        //Login with bot
        $this->login('aw2mw bot');

        $content = implode('', $sections);

        //Replace <u/> with ===
        $content = $this->replaceSubtitles($content);

        $this->savePage($pageName, $content, 'Conversion des titres de section');

    }
}
