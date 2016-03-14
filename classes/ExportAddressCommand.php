<?php
namespace AW2MW;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
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
        $config = Config::getInstance();
        $a = new \archiAdresse();
        $e = new \archiEvenement();
        $u = new \archiUtilisateur();
        $bbCode = new \bbCodeObject();
        $api = new Api\MediawikiApi($config->apiUrl);
        $services = new Api\MediawikiFactory($api);

        $address = $a->getArrayAdresseFromIdAdresse($input->getArgument('id'));
        if (!$address) {
            $output->writeln('<error>Adresse introuvable</error>');
            return;
        }
        $city = $a->getInfosVille($address['idVille']);

        $pageName = strip_tags(
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
        $api->login(new Api\ApiUser($config->admin['login'], $config->admin['password']));

        //Delete article if it already exists
        $page = $services->newPageGetter()->getFromTitle($pageName);
        if ($page->getPageIdentifier()->getId() > 0) {
            $services->newPageDeleter()->delete($page);
        }

        $groupInfo = mysql_fetch_assoc($a->getIdEvenementsFromAdresse($input->getArgument('id')));

        $events = $e->getArrayIdEvenement($groupInfo['idEvenementGroupeAdresse']);

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


        //Login as bot
        $password = password_hash(
            'aw2mw bot'.$config->userSecret,
            PASSWORD_BCRYPT,
            array('salt'=>$config->salt)
        );
        try {
            $api->login(new Api\ApiUser('aw2mw bot', $password));
        } catch (Api\UsageException $error) {
            $services->newUserCreator()->create(
                'aw2mw bot',
                $password
            );
            $api->login(new Api\ApiUser('aw2mw bot', $password));
        }

        $revision = new DataModel\Revision(
            new DataModel\Content($content),
            new DataModel\PageIdentifier(new DataModel\Title($pageName))
        );
        $services->newRevisionSaver()->save(
            $revision,
            new DataModel\EditInfo('Sections importées depuis Archi-Wiki', true, true)
        );

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
                $username = $user['prenom'].' '.$user['nom'];
                $password = password_hash(
                    $username.$config->userSecret,
                    PASSWORD_BCRYPT,
                    array('salt'=>$config->salt)
                );
                try {
                    $api->login(new Api\ApiUser($username, $password));
                } catch (Api\UsageException $error) {
                    //No email for now
                    $services->newUserCreator()->create(
                        $username,
                        $password
                    );
                    $api->login(new Api\ApiUser($username, $password));
                }


                $content = '';
                if (!empty($event['titre'])) {
                    $title = $event['titre'];
                } else {
                    $title = substr($event['dateDebut'], 0, 4);
                }
                $title = stripslashes($title);
                $content .= '=='.$title.'=='.PHP_EOL;
                exec(
                    'echo '.
                    escapeshellarg(
                        $bbCode->convertToDisplay(array('text'=>$event['description']))
                    ). ' | html2wiki --dialect MediaWiki',
                    $html
                );
                $html = implode(PHP_EOL, $html);

                //Don't use <br>
                $html = str_replace('<br />', PHP_EOL, $html);

                //Trim each line
                $html = implode(PHP_EOL, array_map('trim', explode(PHP_EOL, $html)));

                $content .= $html.PHP_EOL.PHP_EOL;
                $api->postRequest(
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
                            'token'=>$api->getToken()
                        )
                    )
                );
            }
        }

    }
}
