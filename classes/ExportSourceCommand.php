<?php

namespace AW2MW;

use Mediawiki\Api;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportSourceCommand extends ExportCommand
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
            ->setName('export:source')
            ->setDescription('Export one specific source')
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
        return preg_replace('/<u>(.+)<\/u>\s*:\s*/i', '=== $1 ==='.PHP_EOL, $content);
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

        $origPageName = $this->getSourceName($id);
        $pageName = 'Source:'.$origPageName;

        $output->writeln('<info>Exporting "'.$pageName.'"…</info>');

        $html = $this->s->afficheDescriptionSource($id);
        $html = preg_replace('#<h2>.+</h2>#', '', $html);
        $html = $this->convertHtml($html);
        $html = $this->replaceSubtitles($html);

        //Login as bot
        $this->login('aw2mw bot');

        $oldPath = 'http://www.archi-wiki.org/images/logosSources/'.$id.'_original.jpg';
        $headers = get_headers($oldPath, 1);
        if ($headers[0] == 'HTTP/1.1 200 OK') {
            $filename = 'Source '.$origPageName;
            $filename = str_replace('/', '-', $filename);
            $filename = str_replace('.', '-', $filename);
            $filename .= '.jpg';

            $params = [
                'filename' => $filename,
                'token'    => $this->api->getToken('edit'),
                'url'      => $oldPath,
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
        } else {
            $filename = '';
        }
        $html = '{{Infobox source'.PHP_EOL.
            '|image='.$filename.''.PHP_EOL.
            '|type='.$this->getSourceType($id).PHP_EOL.
            '}}'.PHP_EOL.
            $html;
        $html .= PHP_EOL.'{{Liste utilisations source}}';

        $html = '<translate>'.PHP_EOL.$html.PHP_EOL.'</translate>';

        $this->savePage($pageName, $html, 'Source importée depuis Archi-Wiki');
    }
}
