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
            )->addOption(
                'noimage',
                null,
                InputOption::VALUE_NONE,
                "Don't upload images"
            );
    }

    private function exportImage(InputInterface $input, OutputInterface $output, $filename, $url)
    {
        if (!$this->input->getOption('noimage')) {
            $params = [
                'filename' => $filename,
                'token'    => $this->api->getToken('edit'),
                'url'      => $url,
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
        }
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
        $home = new \ArchiAccueil();

        $req = "SELECT idActualite, photoIllustration, date, texte,  titre, sousTitre, urlFichier, fichierPdf
            FROM actualites WHERE idActualite='".$id."'";
        $res = $home->connexionBdd->requete($req);
        if (mysql_num_rows($res) > 0) {
            $news = mysql_fetch_assoc($res);
        } else {
            throw new \Exception("Can't find this news");
        }
        if (empty($news['texte'])) {
            throw new \Exception('Empty news');
        }
        $pageName = 'Actualité:'.stripslashes($news['titre']);

        $output->writeln('<info>Exporting "'.$pageName.'"…</info>');

        //Login as bot
        $this->loginManager->login('aw2mw bot');

        $html = '{{En-tête actualité'.PHP_EOL.
            '|date='.$news['date'].PHP_EOL.
            '}}'.PHP_EOL;

        if (!empty($news['photoIllustration'])) {
            $filename = 'Actualité '.stripslashes($news['titre']);
            $filename = str_replace('/', '-', $filename);
            $filename = str_replace('.', '-', $filename);
            $filename = str_replace(':', '-', $filename);
            $filename .= '.jpg';

            $this->exportImage(
                $input,
                $output,
                $filename,
                'http://www.archi-wiki.org/images/actualites/'.
                    $news['idActualite'].'/'.$news['photoIllustration']
            );

            $html .= '[[Fichier:'.$filename.'|thumb]]'.PHP_EOL;
        }

        $news['texte'] = str_replace(
            '###cheminImages###',
            $this->a->getUrlImage().'actualites/'.$news['idActualite'].'/',
            $news['texte']
        );

        $html .= $this->convertHtml($news['texte']);

        //Replace signature
        preg_match_all(
            '#\[http:\/\/(www\.)?archi-wiki.org\/profil-31(-([0-9]+))?\.html (Fabien Romary|Romary Fabien)\]#iU',
            $html,
            $matches,
            PREG_SET_ORDER
        );
        if (is_array($matches)) {
            foreach ($matches as $match) {
                $html = str_replace($match[0], '[[Utilisateur:Digito/me_contacter|Fabien Romary]]', $html);
            }
        }

        //Import images in text
        preg_match_all(
            '#\[\[Image:(([^\|]+)(\|.+)?)\]\]#iU',
            $html,
            $matches,
            PREG_SET_ORDER
        );
        if (is_array($matches)) {
            foreach ($matches as $match) {
                $filename = 'Actualité '.stripslashes($news['titre']);
                $filename = str_replace('/', '-', $filename);
                $filename = str_replace('.', '-', $filename);
                $filename = str_replace(':', '-', $filename);
                $filename .= ' - '.str_replace('%20', ' ', $match[2]);
                preg_match(
                    '#[\"\']([^\"\']+)('.preg_quote(str_replace('%20', ' ', $match[2])).'|'.
                        preg_quote($match[2]).')[\"\']#iU',
                    stripslashes($news['texte']),
                    $imageMatches
                );
                if (filter_var($imageMatches[1], FILTER_VALIDATE_URL) == false) {
                    $imageUrl = 'http://archi-wiki.org/'.$imageMatches[1].$match[2];
                } else {
                    $imageUrl = $imageMatches[1].$match[2];
                }
                $imageUrl = str_replace('https', 'http', $imageUrl);
                try {
                    $this->exportImage(
                        $input,
                        $output,
                        $filename,
                        $imageUrl
                    );
                    if (isset($match[3])) {
                        $attributes = $match[3];
                    } else {
                        $attributes = '';
                    }
                    $html = str_replace($match[0], '[[Image:'.$filename.$attributes.']]', $html);
                } catch (Api\UsageException $e) {
                    $output->writeln("<error>Couldn't import image ".$match[2].':'.PHP_EOL.$e->getMessage().'</error>');
                }
            }
        }

        $this->pageSaver->savePage(
            $pageName,
            $html,
            'Actualité importée depuis Archi-Wiki'
        );
    }
}
