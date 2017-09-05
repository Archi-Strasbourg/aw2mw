<?php

namespace AW2MW;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FixAddressDuplicatesCommand extends ExportCommand
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
            ->setName('fix:address:duplicates')
            ->setDescription('Find and rename duplicate addresses');
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
        $addresses = [];
        while ($address = mysql_fetch_assoc($resAddress)) {
            if ($address['idAdresse'] > 0) {
                $basePageName = $this->getAddressName($address['idAdresse'], $address['idEvenementGA']);

                $addresses[$basePageName][$address['idEvenementGA']] = (int) $address['idAdresse'];
            }
        }

        $this->loginManager->loginAsAdmin();

        $duplicates = [];
        foreach ($addresses as $title => $address) {
            if (count($address) > 1) {
                $i = 1;
                $this->output->writeln('<info>Deleting "'.$title.'"…</info>');
                $this->pageSaver->deletePage($title);
                foreach ($address as $groupId => $id) {
                    try {
                        $command = $this->getApplication()->find('export:address');
                        $command->run(
                            new ArrayInput(['id' => $id, 'groupId' => $groupId, 'title' => $title.' '.$i]),
                            $output
                        );
                    } catch (\Exception $e) {
                        $output->writeln('<error>Couldn\'t export ID '.$id.'/'.$groupId.' </error>');
                    }
                    $i++;
                }
            }
        }
    }
}
