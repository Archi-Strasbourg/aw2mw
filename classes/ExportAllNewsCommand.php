<?php

namespace AW2MW;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExportAllNewsCommand extends ExportCommand
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
            ->setName('export:news:all')
            ->setDescription('Export every news');
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
        $req = '
            SELECT idActualite
            FROM actualites
            ';

        $res = $this->a->connexionBdd->requete($req);
        while ($news = mysql_fetch_assoc($res)) {
            try {
                $command = $this->getApplication()->find('export:news');
                $command->run(
                    new ArrayInput(['id' => $news['idActualite']]),
                    $output
                );
            } catch (\Exception $e) {
                $output->writeln('<error>Couldn\'t export ID '.$news['idActualite'].': '.$e->getMessage().'</error>');
            }
        }
    }
}
