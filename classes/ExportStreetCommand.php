<?php

namespace AW2MW;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportStreetCommand extends ExportCommand
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
            ->setName('export:street')
            ->setDescription('Export one specific street')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'Street ID'
            )->addOption(
                'noparent',
                null,
                InputOption::VALUE_NONE,
                "Don't import parent categories"
            );
    }

    private function exportSubdistrict($id)
    {
        $req = "
            SELECT nom, idQuartier
            FROM sousQuartier
            WHERE idSousQuartier = '".mysql_real_escape_string($id)."'
            ";

        $res = $this->a->connexionBdd->requete($req);
        $subdistrict = mysql_fetch_assoc($res);

        $district = $this->exportDistrict($subdistrict['idQuartier']);

        $subdistrict['ville'] = $district['ville'];
        if ($subdistrict['nom'] == 'autre') {
            $subdistrict['nom'] .= ' ('.$district['origNom'].') ('.$subdistrict['ville'].')';
        } else {
            $subdistrict['nom'] .= ' ('.$subdistrict['ville'].')';
        }

        if (!$this->input->getOption('noparent')) {
            $pageName = 'Catégorie:'.$subdistrict['nom'];
            $this->output->writeln('<info>Exporting "'.$pageName.'"…</info>');

            $html = '{{Infobox sous-quartier}}'.PHP_EOL.
                '[[Catégorie:'.$district['nom'].']]';
            $this->pageSaver->savePage($pageName, $html, 'Sous-quartier importé depuis Archi-Wiki');
        }

        return $subdistrict;
    }

    private function exportDistrict($id)
    {
        $req = "
            SELECT nom, idVille
            FROM quartier
            WHERE idQuartier = '".mysql_real_escape_string($id)."'
            ";

        $res = $this->a->connexionBdd->requete($req);
        $district = mysql_fetch_assoc($res);

        $city = $this->exportCity($district['idVille']);

        $district['ville'] = $city['nom'];
        $district['origNom'] = $district['nom'];
        $district['nom'] .= ' ('.$district['ville'].')';

        if (!$this->input->getOption('noparent')) {
            $pageName = 'Catégorie:'.$district['nom'];
            $this->output->writeln('<info>Exporting "'.$pageName.'"…</info>');

            $html = '{{Infobox quartier}}'.PHP_EOL.
                '[[Catégorie:'.$city['nom'].']]';
            $this->pageSaver->savePage($pageName, $html, 'Quartier importé depuis Archi-Wiki');
        }

        return $district;
    }

    private function exportCity($id)
    {
        $req = "
            SELECT nom, idPays
            FROM ville
            WHERE idVille = '".mysql_real_escape_string($id)."'
            ";

        $res = $this->a->connexionBdd->requete($req);
        $city = mysql_fetch_assoc($res);
        $city['ville'] = $city['nom'];

        $country = $this->exportCountry($city['idPays']);

        if (!$this->input->getOption('noparent')) {
            $pageName = 'Catégorie:'.$city['nom'];
            $this->output->writeln('<info>Exporting "'.$pageName.'"…</info>');

            $html = '{{Infobox ville}}'.PHP_EOL.
                '[[Catégorie:'.$country['nom'].']]';
            $this->pageSaver->savePage($pageName, $html, 'Ville importée depuis Archi-Wiki');
        }

        return $city;
    }

    private function exportCountry($id)
    {
        $req = "
            SELECT nom
            FROM pays
            WHERE idPays = '".mysql_real_escape_string($id)."'
            ";

        $res = $this->a->connexionBdd->requete($req);
        $country = mysql_fetch_assoc($res);

        if (!$this->input->getOption('noparent')) {
            $pageName = 'Catégorie:'.$country['nom'];
            $this->output->writeln('<info>Exporting "'.$pageName.'"…</info>');

            $html = '{{Infobox pays}}'.PHP_EOL.
                '[[Catégorie:Pays]]';
            $this->pageSaver->savePage($pageName, $html, 'Pays importé depuis Archi-Wiki');
        }

        return $country;
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
        $this->output = $output;

        $id = $input->getArgument('id');
        $reqStreet = "
            SELECT idSousQuartier, nom, prefixe
            FROM rue
            WHERE idRue = '".mysql_real_escape_string($id)."'
            ";

        $resStreet = $this->a->connexionBdd->requete($reqStreet);
        $street = mysql_fetch_assoc($resStreet);
        $street['nom'] = trim($street['nom']);

        if (!empty($street) && !empty($street['nom'])) {
            //Login as bot
            $this->loginManager->login('aw2mw bot');

            $origName = $street['nom'];

            $subdistrict = $this->exportSubdistrict($street['idSousQuartier']);
            $street['nom'] .= ' ('.$subdistrict['ville'].')';
            $pageName = 'Catégorie:'.$street['prefixe'].' '.$street['nom'];
            $pageName = str_replace("l' ", "l'", $pageName);
            $pageName = str_replace("d' ", "d'", $pageName);

            $output->writeln('<info>Exporting "'.$pageName.'"…</info>');

            $html = '{{Infobox rue'.PHP_EOL.
                '|sous-quartier='.$subdistrict['nom'].PHP_EOL.
                '|nom_court='.$origName.PHP_EOL.
                '|complement='.$street['prefixe'].PHP_EOL.
                '|ville='.$subdistrict['ville'].PHP_EOL.
                '}}'.PHP_EOL;

            $this->pageSaver->savePage($pageName, $html, 'Rue importée depuis Archi-Wiki');
        } else {
            $output->writeln('<error>Can\'t find this street: '.$id.'</error>');
        }
    }
}
