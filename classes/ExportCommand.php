<?php

namespace AW2MW;

use Chain\Chain;
use Mediawiki\Api;
use Mediawiki\DataModel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;

abstract class ExportCommand extends Command
{
    protected $config;
    protected $a;
    protected $e;
    protected $u;
    protected $i;
    protected $s;
    protected $bbCode;
    protected $api;
    protected $services;
    protected $revisionSaver;
    protected $fileUploader;
    protected $output;

    /**
     * Configure command.
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

    /**
     * @param string $username
     */
    protected function login($username)
    {
        $password = password_hash(
            $username.$this->config->userSecret,
            PASSWORD_BCRYPT,
            ['salt' => $this->config->salt]
        );
        try {
            $this->api->login(new Api\ApiUser($username, $password));
        } catch (Api\UsageException $error) {
            try {
                //No email for now
                $this->services->newUserCreator()->create(
                    $username,
                    $password
                );
                $this->api->login(new Api\ApiUser($username, $password));
            } catch (Api\UsageException $error) {
                $this->login('aw2mw bot');
            }
        }
    }

    /**
     * @param string $pageName
     */
    protected function deletePage($pageName)
    {
        //Delete article if it already exists
        $page = $this->services->newPageGetter()->getFromTitle($pageName);
        if ($page->getPageIdentifier()->getId() > 0) {
            $this->services->newPageDeleter()->delete($page);
        }
    }

    /**
     * @param string $note
     * @param string $pageName
     * @param string $content
     */
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
        if ($approximately == '1') {
            $date .= 'environ ';
        }
        if (substr($startDate, 5) == '00-00') {
            $datetime = substr($startDate, 0, 4);
        } else {
            $datetime = $startDate;
        }
        if ($startDate != '0000-00-00') {
            $date .= $this->e->date->toFrenchAffichage($datetime);
        }
        if ($endDate != '0000-00-00') {
            if (strlen($this->e->date->toFrench($endDate)) <= 4) {
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
        $this->input = $input;
        $this->output = $output;
    }

    protected function loginAsAdmin()
    {
        $this->api->login(new Api\ApiUser($this->config->admin['login'], $this->config->admin['password']));
    }

    /**
     * @param string $content
     */
    protected function replaceSubtitles($content)
    {
        return preg_replace('/\n<u>(.+)(\s*:)<\/u>(\s*:)?\s*/i', PHP_EOL.'=== $1 ==='.PHP_EOL, $content);
    }

    protected function replaceSourceLists($content)
    {
        $sources = '';
        preg_match_all('/^\s*\(?sources\s*:(.*)\)?/im', $content, $sourceLists, PREG_SET_ORDER);
        foreach ($sourceLists as $sourceList) {
            if (!empty($sourceList)) {
                $sources .= str_replace(' - ', PHP_EOL.'* ', $sourceList[1]);
                $content = str_replace($sourceList[0], '', $content);
            }
        }
        if (!empty($sources)) {
            $content .= '== Sources =='.$sources;
        }

        return $content;
    }

    /**
     * @param string $html
     */
    protected function convertHtml($html)
    {
        global $config;
        $chain = new Chain(
            ProcessBuilder::create(['echo', $html])
        );
        $chain->add(
            '|',
            ProcessBuilder::create(
                ['html2wiki', '--dialect', 'MediaWiki']
            )
        );
        $process = $chain->getProcess();
        $process->run();
        $html = $process->getOutput();

        //Don't use <br>
        $html = preg_replace('/(\<br \/\>)+/', '<br />', $html);
        $html = str_replace('<br />', PHP_EOL.PHP_EOL, $html);

        //Trim each line
        $html = implode(PHP_EOL, array_map('trim', explode(PHP_EOL, $html)));

        //Convert sources
        $html = preg_replace('/\s*\(?source\s*:([^)^\n]+)\)?/i', '<ref>$1</ref>', $html);

        //Replace old domain
        $html = str_replace('archi-strasbourg.org', 'archi-wiki.org', $html);

        //Convert URLs
        preg_match_all(
            '#\[http:\/\/(www\.)?archi-wiki.org\/adresse-(.+)-([0-9]+)\.html(\?[\w=&\#]+)?\s(.+)\]#iU',
            $html,
            $matches,
            PREG_SET_ORDER
        );
        if (is_array($matches)) {
            foreach ($matches as $match) {
                $html = str_replace($match[0], '[[Adresse:'.$this->getAddressName($match[3]).'|'.$match[5].']]', $html);
            }
        }

        preg_match_all(
            '#\[http:\/\/(www\.)?archi-wiki.org\/\?archiAffichage=adresseDetail&archiIdAdresse=([0-9]+)\s(.+)\]#iU',
            $html,
            $matches,
            PREG_SET_ORDER
        );
        if (is_array($matches)) {
            foreach ($matches as $match) {
                $html = str_replace($match[0], '[[Adresse:'.$this->getAddressName($match[2]).'|'.$match[3].']]', $html);
            }
        }

        preg_match_all(
            '#\[http:\/\/(www\.)?archi-wiki.org\/personnalite-(.+)-([0-9]+)\.html(\?[\w=&\#]+)?\s(.+)\]#iU',
            $html,
            $matches,
            PREG_SET_ORDER
        );
        if (is_array($matches)) {
            foreach ($matches as $match) {
                @$person = new \ArchiPersonne($match[3]);
                $html = str_replace(
                    $match[0],
                    '[[Personne:'.$person->prenom.' '.$person->nom.'|'.$match[5].']]',
                    $html
                );
            }
        }

        return $html;
    }

    protected function createGallery($images)
    {
        $return = '<gallery>'.PHP_EOL;
        foreach ($images as $image) {
            if (!$this->input->getOption('noimage')) {
                $command = $this->getApplication()->find('export:image');
                $command->run(
                    new ArrayInput(['id' => $image['idImage']]),
                    $this->output
                );
            }
            $filename = $this->getImageName($image['idImage']);
            $description = str_replace(
                PHP_EOL,
                ' ',
                strip_tags(
                    $this->convertHtml(
                        (string) $this->bbCode->convertToDisplay(['text' => $image['description']])
                    )
                )
            );
            $return .= 'File:'.$filename.'|'.$description.PHP_EOL;
        }
        $return .= '</gallery>'.PHP_EOL;

        return $return;
    }

    protected function getJobName($id)
    {
        $reqJob = 'SELECT nom
            FROM `metier`
            WHERE `idMetier` ='.mysql_real_escape_string($id);
        $resJob = $this->a->connexionBdd->requete($reqJob);
        if ($fetch = mysql_fetch_object($resJob)) {
            return $fetch->nom;
        }
    }

    protected function getSourceType($id)
    {
        $reqTypeSource = '
            SELECT tS.nom
            FROM source s
            LEFT JOIN typeSource tS USING (idTypeSource)
            WHERE idSource = '.mysql_real_escape_string($id).' LIMIT 1
        ';
        $resTypeSource = $this->s->connexionBdd->requete($reqTypeSource);
        $typeSource = mysql_fetch_assoc($resTypeSource);

        return $typeSource['nom'];
    }

    protected function getSourceName($id)
    {
        $origPageName = $this->escapeSourceName($this->s->getSourceLibelle($id));
        if (empty($origPageName)) {
            throw new Exception('Empty source name (ID '.$id.')');
        }

        return $origPageName.' ('.$this->getSourceType($id).')';
    }

    protected function getAddressName($id)
    {
        $addressInfo = $this->a->getArrayAdresseFromIdAdresse($id);
        $return = strip_tags(
            $this->a->getIntituleAdresseFrom(
                $id,
                'idAdresse',
                [
                    'noHTML'                   => true,
                    'noQuartier'               => true,
                    'noSousQuartier'           => true,
                    'noVille'                  => true,
                    'displayFirstTitreAdresse' => true,
                    'setSeparatorAfterTitle'   => '#',
                    'idEvenementGroupeAdresse' => $this->a->getIdEvenementGroupeAdresseFromIdAdresse($id),
                ]
            )
        ).' ('.$addressInfo['nomVille'].')';
        $return = explode('#', $return);
        $name = $return[0];
        if (strpos($name, '('.$addressInfo['nomVille'].')') === false) {
            //If the address has a name, we need to manually add the city
            $name .= ' ('.$addressInfo['nomVille'].')';
        }
        $name = str_replace("l' ", "l'", $name);
        $name = str_replace("d' ", "d'", $name);
        $name = trim($name, '.');

        return $name;
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

    /**
     * @param string $name
     */
    protected function escapeSourceName($name)
    {
        $name = stripslashes($name);
        $name = str_replace('"', '', $name);
        $name = urldecode($name);
        $name = trim($name);

        return $name;
    }
}
