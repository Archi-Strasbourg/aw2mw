<?php

namespace AW2MW;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportRouteCommand extends ExportCommand
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
            ->setName('export:route')
            ->setDescription('Export one specific route')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'Route ID'
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

        $resParcours = $this->a->getMysqlParcours(array('sqlWhere'=>"AND idParcours='".$id."'"));
        $route = mysql_fetch_assoc($resParcours);

        $pageName = 'Parcours:'.$route['libelleParcours'];
        $output->writeln('<info>Exporting "'.$pageName.'"…</info>');

        $reqEtapes = "SELECT idEtape,idEvenementGroupeAdresse,commentaireEtape,position
            FROM etapesParcoursArt WHERE idParcours='".$id."' ORDER BY position ASC";
        $resEtapes = $this->a->connexionBdd->requete($reqEtapes);

        $html = '{| class="wikitable"';
        while ($stage = mysql_fetch_assoc($resEtapes)) {
            $addressName = $this->getAddressName(
                $this->a->getIdAdresseFromIdEvenementGroupeAdresse($stage['idEvenementGroupeAdresse']),
                $stage['idEvenementGroupeAdresse']
            );
            $html .= '|-'.PHP_EOL;
            $html .= '|[[Adresse:'.$addressName.'|'.$addressName.']]'.PHP_EOL;
            $html .= '|'.$this->convertHtml(
                (string) $this->bbCode->convertToDisplay(['text' => $stage['commentaireEtape']])
            ).PHP_EOL;
        }
        $html .= '|}';

        $this->loginManager->login('aw2mw bot');

        $this->pageSaver->savePage(
            $pageName,
            $html,
            'Parcours importé depuis Archi-Wiki'
        );
    }
}
