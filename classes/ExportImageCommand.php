<?php

namespace AW2MW;

use Mediawiki\Api;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportImageCommand extends ExportCommand
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
            ->setName('export:image')
            ->setDescription('Export one specific image')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'Source ID'
            )->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Force reupload'
            );
    }

    protected function replaceSubtitles($content)
    {
        return preg_replace('/<u>(.+)<\/u>\s*:\s*/i', '===$1==='.PHP_EOL, $content);
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
        global $config;
        $config = new \ArchiConfig();

        $id = $input->getArgument('id');

        $reqImages = "
            SELECT hi1.idImage,  hi1.idHistoriqueImage,  hi1.nom, hi1.auteur,
                hi1.description,  hi1.dateUpload,  hi1.dateCliche, hi1.idUtilisateur,
                hi1.licence, hi1.tags
            FROM _evenementImage ei
            LEFT JOIN historiqueImage hi1 ON hi1.idImage = ei.idImage
            LEFT JOIN historiqueImage hi2 ON hi2.idImage = hi1.idImage
            WHERE hi1.idImage = '".mysql_real_escape_string($id)."'
            GROUP BY hi1.idImage ,  hi1.idHistoriqueImage
            HAVING hi1.idHistoriqueImage = max(hi2.idHistoriqueImage)
            ORDER BY ei.position, hi1.idHistoriqueImage
            ";

        $resImages = $this->i->connexionBdd->requete($reqImages);
        $image = mysql_fetch_assoc($resImages);

        $origImage = mysql_fetch_assoc(
            $this->i->connexionBdd->requete(
                "SELECT idUtilisateur, dateUpload
                FROM historiqueImage
                WHERE idImage = '".mysql_real_escape_string($id)."'"
            )
        );
        $user = $this->u->getArrayInfosFromUtilisateur($origImage['idUtilisateur']);

        $after2008 = false;
        if (!empty($user)) {
            $this->login($user['prenom'].' '.$user['nom']);
        } else {
            $after2008 = new \DateTime($origImage['dateUpload']) > new \DateTime('2008-04-01');
            if ($after2008) {
                $this->login('aw2mw bot');
            } else {
                $this->login('Fabien Romary');
            }
        }

        $filename = $this->getImageName($image['idImage']);
        $imagePage = $this->services->newPageGetter()->getFromTitle('File:'.$filename);
        $output->writeln('<info>Exporting "File:'.$filename.'"…</info>');
        if ($imagePage->getPageIdentifier()->getId() == 0 || $input->getOption('force')) {
            $oldPath = 'http://www.archi-wiki.org/photos--'.$image['dateUpload'].
                '-'.$image['idHistoriqueImage'].'-originaux.jpg';

            $params = [
                'filename' => $filename,
                'token'    => $this->api->getToken('edit'),
                'url'      => $oldPath,
            ];
            if ($input->getOption('force')) {
                $params['ignorewarnings'] = true;
            }

            $this->api->postRequest(
                new Api\SimpleRequest(
                    'upload',
                    $params,
                    []
                )
            );
        }
        if (empty($image['auteur'])) {
            if (!empty($user)) {
                $image['auteur'] = '[[Utilisateur:'.$user['prenom'].' '.$user['nom'].'|'.
                    $user['prenom'].' '.$user['nom'].']]';
            } else {
                if ($after2008) {
                    $image['auteur'] = '';
                } else {
                    $image['auteur'] = '[[Utilisateur:Fabien Romary|Fabien Romary]]';
                }
            }
        }
        if ($image['dateCliche'] == '0000-00-00') {
            $image['dateCliche'] = '';
        }
        if (substr($image['dateCliche'], 5) == '00-00') {
            $image['dateCliche'] = substr($image['dateCliche'], 0, 4);
        }
        $licence = $this->i->getLicence($image['idImage']);
        $this->login('aw2mw bot');
        $description = $this->convertHtml(
            (string) $this->bbCode->convertToDisplay(['text' => $image['description']])
        );
        $this->savePage(
            'File:'.$filename,
            '{{Infobox image
            |description='.$description.'
            |date='.$image['dateCliche'].'
            |auteur='.$image['auteur'].'
            |licence = {{Modèle:'.$licence['name'].'}}
            |tags = '.$image['tags'].'
            }}',
            "Description de l'image importée depuis Archi-Wiki"
        );
    }
}
