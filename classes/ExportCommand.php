<?php
namespace AW2MW;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;
use Mediawiki\Api;
use Mediawiki\DataModel;
use AW2MW\Config;
use Chain\Chain;

abstract class ExportCommand extends Command
{

    /**
     * Configure command
     *
     * @return void
     */
    protected function configure()
    {
        $this->addOption(
            'prod',
            null,
            InputOption::VALUE_NONE,
            'Use production server'
        );
    }

    protected function login($username)
    {
        $password = password_hash(
            $username.$this->config->userSecret,
            PASSWORD_BCRYPT,
            array('salt'=>$this->config->salt)
        );
        try {
            $this->api->login(new Api\ApiUser($username, $password));
        } catch (Api\UsageException $error) {
            //No email for now
            $this->services->newUserCreator()->create(
                $username,
                $password
            );
            $this->api->login(new Api\ApiUser($username, $password));
        }
    }

    protected function deletePage($pageName)
    {
        //Delete article if it already exists
        $page = $this->services->newPageGetter()->getFromTitle($pageName);
        if ($page->getPageIdentifier()->getId() > 0) {
            $this->services->newPageDeleter()->delete($page);
        }
    }

    protected function savePage($pageName, $content, $note)
    {
        $this->revisionSaver->save(
            new DataModel\Revision(
                new DataModel\Content($content),
                new DataModel\PageIdentifier(new DataModel\Title($pageName))
            ),
            new DataModel\EditInfo($note, true, true)
        );
    }

    protected function convertDate($startDate, $endDate, $approximately)
    {
        $date = '';
        if ($approximately=='1') {
            $date .= "environ ";
        }
        if (substr($startDate, 5)=="00-00") {
            $datetime=substr($startDate, 0, 4);
        } else {
            $datetime = $startDate;
        }
        if ($startDate!='0000-00-00') {
            $date .= $this->e->date->toFrenchAffichage($datetime);
        }
        if ($endDate!='0000-00-00') {
            if (strlen($this->e->date->toFrench($endDate))<=4) {
                $date .= ' à '.$this->e->date->toFrenchAffichage($endDate);
            } else {
                $date .= ' au '.$this->e->date->toFrenchAffichage($endDate);
            }
        }
        return $date;
    }

    protected function setup(InputInterface $input, OutputInterface $output)
    {
        //Instantiate objects
        $this->config = Config::getInstance($input->getOption('prod'));
        $this->a = new \archiAdresse();
        $this->e = new \archiEvenement();
        $this->u = new \archiUtilisateur();
        $this->i = new \archiImage();
        $this->s = new \ArchiSource();
        $this->bbCode = new \bbCodeObject();
        $this->api = new Api\MediawikiApi($this->config->apiUrl);
        $this->services = new Api\MediawikiFactory($this->api);
        $this->revisionSaver = $this->services->newRevisionSaver();
        $this->fileUploader = $this->services->newFileUploader();
        $this->output = $output;
    }

    protected function loginAsAdmin()
    {
        $this->api->login(new Api\ApiUser($this->config->admin['login'], $this->config->admin['password']));
    }

    protected function replaceSubtitles($content)
    {
        return preg_replace('/<u>(.+)<\/u>(\s*:)?\s*/i', '===$1==='.PHP_EOL, $content);
    }

    protected function convertHtml($html)
    {
        $chain = new Chain(
            ProcessBuilder::create(array('echo', $html))
        );
        $chain->add(
            '|',
            ProcessBuilder::create(
                array('html2wiki', '--dialect', 'MediaWiki')
            )
        );
        $process = $chain->getProcess();
        $process->run();
        $html = $process->getOutput();

        //Don't use <br>
        $html = str_replace('<br />', PHP_EOL, $html);

        //Trim each line
        $html = implode(PHP_EOL, array_map('trim', explode(PHP_EOL, $html)));

        //Convert sources
        $html = preg_replace('/\s*\(?source\s*:([^)]+)\)?/i', '<ref>$1</ref>', $html);

        //Replace old domain
        $html = str_replace('archi-strasbourg.org', 'archi-wiki.org', $html);

        //Convert URLs
        preg_match_all(
            '#\[http:\/\/www.archi-wiki.org\/adresse-(.+)-([0-9]+)\.html\?[a-zA-z0-9=&\#]+\s([\s\w°]+)\]#i',
            $html,
            $matches,
            PREG_SET_ORDER
        );
        foreach ($matches as $match) {
            $html = str_replace($match[0], '[[Adresse:'.$this->getAddressName($match[2]).'|'.$match[3].']]', $html);
        }

        return $html;
    }

    protected function getAddressName($id)
    {
        $addressInfo = $this->a->getArrayAdresseFromIdAdresse($id);
        $return = strip_tags(
            $this->a->getIntituleAdresseFrom(
                $id,
                'idAdresse',
                array(
                    'noHTML'=>true, 'noQuartier'=>true, 'noSousQuartier'=>true, 'noVille'=>true,
                    'displayFirstTitreAdresse'=>true,
                    'setSeparatorAfterTitle'=>'#'
                )
            )
        ).' ('.$addressInfo['nomVille'].')';
        $return = explode('#', $return);
        return $return[0];
    }

    protected function getImageName($id)
    {
        $addressInfo = $this->a->getArrayAdresseFromIdAdresse($this->i->getIdAdresseFromIdImage($id));
        if ($addressInfo['numero'] == 0) {
            $addressInfo['numero'] = '';
        }
        return trim(
            $addressInfo['numero'].' '.$addressInfo['prefixeRue'].' '.
            $addressInfo['nomRue'].' '.$addressInfo['nomVille'].' '.$id.'.jpg'
        );
    }
}
