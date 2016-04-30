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

class ExportUserCommand extends ExportCommand
{
    /**
     * Configure command
     *
     * @return void
     */
    protected function configure()
    {
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
     * Execute command
     *
     * @param InputInterface  $input  Input
     * @param OutputInterface $output Output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::setup($output);

        $id = $input->getArgument('id');
        $user = $this->u->getArrayInfosFromUtilisateur($id);
        $pageName = 'Utilisateur:'.$user['prenom'].' '.$user['nom'];

        $output->writeln('<info>Exporting "'.$pageName.'"…</info>');

        $content = '';

        $this->loginAsAdmin();
        $this->deletePage($pageName);

        //Login as user
        $this->login($user['prenom'].' '.$user['nom']);

        $oldPath = 'http://www.archi-wiki.org/images/avatar/'.$id.'/original.jpg';
        $headers = get_headers($oldPath, 1);
        if ($headers[0] == 'HTTP/1.1 200 OK') {
            $filename = 'Avatar '.$user['prenom'].' '.$user['nom'].'.jpg';

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
        } else {
            $filename = '';
        }

        $this->savePage(
            $pageName,
            '{{Infobox utilisateur
            |site='.$user['urlSiteWeb'].'
            |avatar='.$filename.'
            }}',
            "Profil importé depuis Archi-Wiki"
        );
    }
}
