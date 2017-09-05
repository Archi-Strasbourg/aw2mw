<?php

namespace AW2MW;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportAllAddressCommand extends ExportCommand
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
            ->setName('export:address:all')
            ->setDescription('Export every address')
            ->addOption(
                'list',
                null,
                InputOption::VALUE_NONE,
                'Only list addresses'
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

        $reqAddress = '
			SELECT idEvenementGA, idAdresse
			FROM recherche
            GROUP BY idAdresse, idEvenementGA';

        $resAddress = $this->a->connexionBdd->requete($reqAddress);
        while ($address = mysql_fetch_assoc($resAddress)) {
            if ($address['idAdresse'] > 0) {
                $basePageName = $this->getAddressName($address['idAdresse'], $address['idEvenementGA']);

                $pageName = 'Adresse:'.$basePageName;

                if ($this->input->getOption('list')) {
                    fputcsv($output->getStream(), [$address['idAdresse'], $address['idEvenementGA'], $pageName]);
                } else if ($this->services->newPageGetter()->getFromTitle($pageName)->getId() == 0) {
                    try {
                        $command = $this->getApplication()->find('export:address');
                        $command->run(
                            new ArrayInput(['id' => $address['idAdresse'], 'groupId' => $address['idEvenementGA']]),
                            $output
                        );
                    } catch (\Exception $e) {
                        $output->writeln('<error>Couldn\'t export ID '.$address['idAdresse'].'/'.$address['idEvenementGA'].' </error>');
                    }
                }
            }
        }
    }
}
