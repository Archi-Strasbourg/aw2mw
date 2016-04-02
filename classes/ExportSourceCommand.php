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
        $this
            ->setName('export:source')
            ->setDescription('Export one specific source')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'Source ID'
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
        parent::setup();

        $id = $input->getArgument('id');
        $pageName = 'Source:'.$this->s->getSourceLibelle($id);

        $output->writeln('<info>Exporting "'.$pageName.'"…</info>');

        $html = $this->convertHtml(
            $this->s->afficheDescriptionSource(
                $id
            )
        );
        $html = $this->replaceSubtitles($html);

        $this->loginAsAdmin();
        $this->deletePage($pageName);

        //Login as bot
        $this->login('aw2mw bot');
        $this->savePage($pageName, $html, 'Source importée depuis Archi-Wiki');
    }
}
