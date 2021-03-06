<?php

namespace AW2MW;

use Mediawiki\Api;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportUserCommand extends ExportCommand
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
            ->setName('export:user')
            ->setDescription('Export one specific user')
            ->addArgument(
                'id',
                InputArgument::REQUIRED,
                'User ID'
            )->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Force reupload'
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

        $id = $input->getArgument('id');
        $user = $this->u->getArrayInfosFromUtilisateur($id);
        $user['prenom'] = stripslashes($user['prenom']);
        $user['nom'] = stripslashes($user['nom']);
        $pageName = 'Utilisateur:'.$user['prenom'].' '.$user['nom'];

        $output->writeln('<info>Exporting "'.$pageName.'"…</info>');

        //Login as user
        $this->loginManager->login($user['prenom'].' '.$user['nom']);

        $oldPath = 'http://www.archi-wiki.org/images/avatar/'.$id.'/original.jpg';
        $headers = get_headers($oldPath, 1);
        if ($headers[0] == 'HTTP/1.1 200 OK') {
            $filename = 'Avatar '.$user['prenom'].' '.$user['nom'].'.jpg';

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

        $this->pageSaver->savePage(
            $pageName,
            '{{Infobox utilisateur'.PHP_EOL.
            '|site='.$user['urlSiteWeb'].PHP_EOL.
            '|avatar='.$filename.PHP_EOL.
            '}}',
            'Profil importé depuis Archi-Wiki'
        );
    }
}
