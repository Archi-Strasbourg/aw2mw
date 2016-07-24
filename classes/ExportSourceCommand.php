<?php
namespace AW2MW;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Mediawiki\Api;
use Mediawiki\DataModel;
use AW2MW\Config;

class ExportSourceCommand extends ExportCommand
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
        return preg_replace('/<u>(.+)<\/u>\s*:\s*/i', '===$1==='.PHP_EOL, $content);
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

        $id = $input->getArgument('id');
        $origPageName = $this->s->getSourceLibelle($id);
        $pageName = 'Source:'.$origPageName;

        $output->writeln('<info>Exporting "'.$pageName.'"…</info>');

        $html = $this->s->afficheDescriptionSource($id);
        $html = preg_replace('#<h2>.+</h2>#', '', $html);
        $html = $this->convertHtml($html);
        $html = $this->replaceSubtitles($html);

        $this->loginAsAdmin();
        $this->deletePage($pageName);

        //Login as bot
        $this->login('aw2mw bot');

        $oldPath = 'http://www.archi-wiki.org/images/logosSources/'.$id.'_original.jpg';
        $headers = get_headers($oldPath, 1);
        if ($headers[0] == 'HTTP/1.1 200 OK') {
            $filename = 'Source '.$origPageName.'.jpg';

            $params = array(
                'filename'=>$filename,
                'token'=>$this->api->getToken('edit'),
                'url'=>$oldPath
            );
            if ($input->getOption('force')) {
                $params['ignorewarnings'] = true;
            }

            $output->writeln('<info>Exporting "File:'.$filename.'"…</info>');
            $this->api->postRequest(
                new Api\SimpleRequest(
                    'upload',
                    $params,
                    array()
                )
            );
            $html = '[[File:'.$filename.'|thumb]]'.PHP_EOL.$html;
        }

        $this->savePage($pageName, $html, 'Source importée depuis Archi-Wiki');
    }
}
