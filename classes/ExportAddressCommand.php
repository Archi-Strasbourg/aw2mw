<?php
namespace AW2MW;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Mediawiki\Api;
use Mediawiki\DataModel;
use AW2MW\Config;

class ExportAddressCommand extends Command
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

    private function login($username)
    {
        $password = password_hash(
            $username.$this->config->userSecret,
            PASSWORD_BCRYPT,
            array('salt'=>$this->config->salt)
        );
        try {
            $this->api->login(new Api\ApiUser($username, $password));
        } catch (Api\UsageException $error) {
            //No email for now
            $this->services->newUserCreator()->create(
                $username,
                $password
            );
            $this->api->login(new Api\ApiUser($username, $password));
        }
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
        //Instantiate objects
        $this->config = Config::getInstance();
        $a = new \archiAdresse();
        $e = new \archiEvenement();
        $u = new \archiUtilisateur();
        $s = new \ArchiSource();
        $bbCode = new \bbCodeObject();
        $this->api = new Api\MediawikiApi($this->config->apiUrl);
        $this->services = new Api\MediawikiFactory($this->api);

        $address = $a->getArrayAdresseFromIdAdresse($input->getArgument('id'));
        if (!$address) {
            $output->writeln('<error>Adresse introuvable</error>');
            return;
        }
        $city = $a->getInfosVille($address['idVille']);

        $pageName = 'Adresse:'.strip_tags(
            $a->getIntituleAdresseFrom(
                $input->getArgument('id'),
                'idAdresse',
                array(
                    'noHTML'=>true, 'noQuartier'=>true, 'noSousQuartier'=>true, 'noVille'=>true,
                    'displayFirstTitreAdresse'=>true,
                    'setSeparatorAfterTitle'=>'_'
                )
            )
        ).'_('.$address['nomVille'].')';

        $output->writeln('<info>Exporting "'.$pageName.'"…</info>');

        //Login as admin
        $this->api->login(new Api\ApiUser($this->config->admin['login'], $this->config->admin['password']));

        //Delete article if it already exists
        $page = $this->services->newPageGetter()->getFromTitle($pageName);
        if ($page->getPageIdentifier()->getId() > 0) {
            $this->services->newPageDeleter()->delete($page);
        }

        $groupInfo = mysql_fetch_assoc($a->getIdEvenementsFromAdresse($input->getArgument('id')));

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
        $result = $e->connexionBdd->requete($requete);
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

            $res = $e->connexionBdd->requete($sql);

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

        $pageIdentifier = new DataModel\PageIdentifier(new DataModel\Title($pageName));
        $revision = new DataModel\Revision(
            new DataModel\Content($content),
            $pageIdentifier
        );
        $revisionSaver = $this->services->newRevisionSaver();
        $revisionSaver->save(
            $revision,
            new DataModel\EditInfo('Sections importées depuis Archi-Wiki', true, true)
        );

        $sections = array();

        foreach ($events as $section => $id) {
            $req = "SELECT idHistoriqueEvenement
                    FROM historiqueEvenement
                    WHERE idEvenement=".$id." order by dateCreationEvenement ASC";
            $res = $e->connexionBdd->requete($req);

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

                $event = mysql_fetch_assoc($e->connexionBdd->requete($sql));

                $user = $u->getArrayInfosFromUtilisateur($event['idUtilisateur']);

                //Login as user
                $this->login($user['prenom'].' '.$user['nom']);


                $content = '';
                if (!empty($event['titre'])) {
                    $title = $event['titre'];
                } else {
                    $title = '';
                    if ($event['isDateDebutEnviron']=='1') {
                        $title .= "environ ";
                    }
                    if (substr($event['dateDebut'], 5)=="00-00") {
                        $datetime=substr($event['dateDebut'], 0, 4);
                    } else {
                        $datetime = $event['dateDebut'];
                    }
                    if ($event['dateDebut']!='0000-00-00') {
                        $title .= $e->date->toFrenchAffichage($datetime);
                    }
                    if ($event['dateFin']!='0000-00-00') {
                        if (strlen($e->date->toFrench($event['dateFin']))<=4) {
                            $title .= ' à '.$e->date->toFrenchAffichage($event['dateFin']);
                        } else {
                            $title .= ' au '.$e->date->toFrenchAffichage($event['dateFin']);
                        }
                    }
                }
                if ($event['idSource'] > 0) {
                    $sourceName = $s->getSourceLibelle($event['idSource']);
                    $title .= '<ref>[[Source:'.$sourceName.'|'.$sourceName.']]</ref>';
                }
                $title = ucfirst(stripslashes($title));
                $content .= '=='.$title.'=='.PHP_EOL;
                $process = new Process(
                    'echo '.
                    escapeshellarg(
                        $bbCode->convertToDisplay(array('text'=>$event['description']))
                    ). ' | html2wiki --dialect MediaWiki'
                );
                $process->run();
                $html =$process->getOutput();

                //Don't use <br>
                $html = str_replace('<br />', PHP_EOL, $html);

                //Trim each line
                $html = implode(PHP_EOL, array_map('trim', explode(PHP_EOL, $html)));

                //Convert sources
                $html = preg_replace('/\s*\(?source\s*:([^)]+)\)?/i', '<ref>$1</ref>'.PHP_EOL, $html);

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
        }

        $sections[] = $references;

        //Login with bot
        $this->login('aw2mw bot');

        //Replace <u/> with ===
        $content = implode('', $sections);
        $content = preg_replace('/<u>(.+)<\/u>(\s*:)?\s*/i', '===$1==='.PHP_EOL, $content);
        $revisionSaver->save(
            new DataModel\Revision(
                new DataModel\Content($content),
                $pageIdentifier
            ),
            new DataModel\EditInfo('Conversion des titres de section', true, true)
        );
    }
}
