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

abstract class ExportCommand extends Command
{

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

    protected function setup()
    {
        //Instantiate objects
        $this->config = Config::getInstance();
        $this->a = new \archiAdresse();
        $this->e = new \archiEvenement();
        $this->u = new \archiUtilisateur();
        $this->s = new \ArchiSource();
        $this->bbCode = new \bbCodeObject();
        $this->api = new Api\MediawikiApi($this->config->apiUrl);
        $this->services = new Api\MediawikiFactory($this->api);
        $this->revisionSaver = $this->services->newRevisionSaver();
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
        $process = new Process(
            'echo '.
            escapeshellarg(
                $html
            ). ' | html2wiki --dialect MediaWiki'
        );
        $process->run();
        $html = $process->getOutput();

        //Don't use <br>
        $html = str_replace('<br />', PHP_EOL, $html);

        //Trim each line
        $html = implode(PHP_EOL, array_map('trim', explode(PHP_EOL, $html)));

        //Convert sources
        $html = preg_replace('/\s*\(?source\s*:([^)]+)\)?/i', '<ref>$1</ref>'.PHP_EOL, $html);

        return $html;
    }
}