<?php

namespace AW2MW;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixSourceRedirectsCommand extends ExportCommand
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
            ->setName('fix:source:redirects')
            ->setDescription('Create redirects with old name format');
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

        //Login as bot
        $this->loginManager->login('aw2mw bot');

        $reqSource = '
            SELECT idSource
            FROM source
            ';

        $resSource = $this->a->connexionBdd->requete($reqSource);
        while ($source = mysql_fetch_assoc($resSource)) {
            if ($source['idSource'] > 0) {
                try {
                    $origPageName = 'Source:'.Source::getSourceName($source['idSource'], false);
                    $newPageName = 'Source:'.Source::getSourceName($source['idSource'], true);

                    $this->output->writeln('<info>Redirect "'.$origPageName.'" to "'.$newPageName.'"</info>');

                    $this->pageSaver->savePage(
                        $origPageName,
                        '#REDIRECT[['.$newPageName.']]',
                        'Redirection vers le nouveau format de nom'
                    );
                } catch (\Exception $e) {
                    $output->writeln('<error>Couldn\'t export ID '.$source['idSource'].' </error>');
                }
            }
        }
    }
}
