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

class ExportPersonCommand extends ExportCommand
{
    /**
     * Configure command
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
     * Execute command
     *
     * @param InputInterface  $input  Input
     * @param OutputInterface $output Output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::setup($input, $output);
        global $config;
        $config = new \Config();
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

        $content = '';

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
                    date_format(hE.dateCreationEvenement,"'._("%e/%m/%Y à %kh%i").'") as dateCreationEvenement,
                    hE.isDateDebutEnviron as isDateDebutEnviron,
                    u.idUtilisateur as idUtilisateur,
                    hE.numeroArchive as numeroArchive

                    FROM historiqueEvenement hE
                    LEFT JOIN source s      ON s.idSource = hE.idSource
                    LEFT JOIN typeStructure tS  ON tS.idTypeStructure = hE.idTypeStructure
                    LEFT JOIN typeEvenement tE  ON tE.idTypeEvenement = hE.idTypeEvenement
                    LEFT JOIN utilisateur u     ON u.idUtilisateur = hE.idUtilisateur
                    WHERE hE.idEvenement = '.mysql_real_escape_string($event["idEvenementAssocie"]).'
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

        $references = PHP_EOL.'==Références=='.PHP_EOL.'<references />'.PHP_EOL;
        $content .= $references;

        $this->savePage($pageName, $content, 'Sections importées depuis Archi-Wiki');

        foreach ($events as $section => $event) {
            $req = "SELECT idHistoriqueEvenement
                    FROM historiqueEvenement
                    WHERE idEvenement=".mysql_real_escape_string($event["idEvenementAssocie"])."
                    order by dateCreationEvenement ASC";
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
                if (!empty($user['nom'])) {
                    $this->login($user['prenom'].' '.$user['nom']);
                } else {
                    $this->login('aw2mw bot');
                }


                $content = '';
                $date = $this->convertDate($event['dateDebut'], $event['dateFin'], $event['isDateDebutEnviron']);

                if (!empty($event['titre'])) {
                    $title = $event['titre'];
                } elseif (!empty($event['nomTypeEvenement'])) {
                    $title = $event['nomTypeEvenement'];
                } else {
                    $title = 'Biographie';
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

                $html = $this->convertHtml(
                    $this->bbCode->convertToDisplay(array('text'=>$event['description']))
                );


                $content .= trim($html).PHP_EOL.PHP_EOL;
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
                            'token'=>$this->api->getToken()
                        )
                    )
                );
                $sections[$section + 1] = $content;
            }
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
