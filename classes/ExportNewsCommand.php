<?php

namespace AW2MW;

use Mediawiki\Api;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportNewsCommand extends ExportCommand
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
            ->setName('export:news')
            ->setDescription('Export one specific news')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'News ID'
            )->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Force reupload'
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

        $id = $input->getArgument('id');
        $home = new \ArchiAccueil();

        $req = "SELECT idActualite, photoIllustration, date, texte,  titre, sousTitre, urlFichier, fichierPdf
            FROM actualites WHERE idActualite='".$id."'";
        $res = $home->connexionBdd->requete($req);
        if (mysql_num_rows($res) > 0) {
            $news = mysql_fetch_assoc($res);
        } else {
            throw new \Exception("Can't find this news");
        }
        $pageName = 'Actualité:'.$news['titre'];

        $output->writeln('<info>Exporting "'.$pageName.'"…</info>');

        //Login as bot
        $this->loginManager->login('aw2mw bot');

        $filename = 'Actualité '.$news['titre'];
        $filename = str_replace('/', '-', $filename);
        $filename = str_replace('.', '-', $filename);
        $filename .= '.jpg';

        $params = [
            'filename' => $filename,
            'token'    => $this->api->getToken('edit'),
            'url'      => 'http://www.archi-wiki.org/images/actualites/'.$news['idActualite'].'/'.$news['photoIllustration'],
        ];
        if ($input->getOption('force')) {
            $params['ignorewarnings'] = true;
        }

        $output->writeln('<info>Exporting "File:'.$filename.'"…</info>');
        $this->api->postRequest(
            new Api\SimpleRequest(
                'upload',
                $params,
                []
            )
        );

        $html = '[[Fichier:'.$filename.'|thumb]]';
        $html .= $this->convertHtml($news['texte']);
        $html = str_replace('[http://www.archi-wiki.org/profil-31-11005.html Fabien Romary]', '[[Utilisateur:Digito/me_contacter|Fabien Romary]]', $html);

        $this->pageSaver->savePage(
            $pageName,
            $html,
            'Actualité importée depuis Archi-Wiki'
        );
    }
}
