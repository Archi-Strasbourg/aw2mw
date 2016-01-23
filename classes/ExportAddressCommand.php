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
     * @param  InputInterface  $input  Input
     * @param  OutputInterface $output Output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //Instantiate objects
        $config = Config::getInstance();
        $a = new \archiAdresse();
        $e = new \archiEvenement();
        $bbCode = new \bbCodeObject();

        $address = $a->getArrayAdresseFromIdAdresse($input->getArgument('id'));

        $output->writeln('<info>Exporting '.$address['nom'].'…</info>');

        $groupInfo = mysql_fetch_assoc($a->getIdEvenementsFromAdresse($input->getArgument('id')));

        $events = $e->getArrayIdEvenement($groupInfo['idEvenementGroupeAdresse']);

        $content = '';

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
            //dump($id, $html, $content); die;
        }

        $api = new Api\MediawikiApi($config->apiUrl);
        $password = password_hash(
            'Test1'.$config->userSecret,
            PASSWORD_BCRYPT,
            array('salt'=>$config->salt)
        );
        $api->login(new Api\ApiUser('Test1', $password));
        $services = new Api\MediawikiFactory($api);
        $page = $services->newPageGetter()->getFromTitle($address['nom']);
        $revision = new DataModel\Revision(
            new DataModel\Content($content),
            new DataModel\PageIdentifier(new DataModel\Title($address['nom']))
        );
        $services->newRevisionSaver()->save(
            $revision,
            new DataModel\EditInfo('Importé depuis Archi-Wiki', false, true)
        );
    }
}
