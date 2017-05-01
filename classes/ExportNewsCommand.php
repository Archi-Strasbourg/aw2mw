<?php

namespace AW2MW;

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

        $req = "SELECT idActualite,  date, texte,  titre, sousTitre, urlFichier, fichierPdf
            FROM actualites WHERE idActualite='".$id."'";
        $res = $home->connexionBdd->requete($req);
        if (mysql_num_rows($res) > 0) {
            $news = mysql_fetch_assoc($res);
        } else {
            throw new \Exception("Can't find this news");
        }
        $pageName = 'Actualité:'.$news['titre'];

        $output->writeln('<info>Exporting "'.$pageName.'"…</info>');

        $this->loginManager->login('aw2mw bot');

        $this->pageSaver->savePage(
            $pageName,
            $this->convertHtml($news['texte']),
            'Actualité importée depuis Archi-Wiki'
        );
    }
}
