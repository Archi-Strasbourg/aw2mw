<?php

namespace AW2MW;

use Chain\Chain;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\ProcessBuilder;

abstract class ExportCommand extends Command
{
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
     * @param string $content
     */
    protected function replaceSubtitles($content)
    {
        return preg_replace('/\n<u>(.+)(\s*:)?<\/u>(\s*:)?\s*/i', PHP_EOL.'=== $1 ==='.PHP_EOL, $content);
    }

    /**
     * @param string $content
     */
    protected function replaceSourceLists($content)
    {
        $sources = '';
        preg_match_all('/^\s*\(?sources\s*:([^\<\=]*)\)?/im', $content, $sourceLists, PREG_SET_ORDER);
        if (is_array($sourceLists)) {
            foreach ($sourceLists as $sourceList) {
                if (!empty($sourceList)) {
                    $sources .= PHP_EOL.str_replace(
                        ','.PHP_EOL,
                        PHP_EOL,
                        trim(
                            str_replace(
                                ' - ',
                                PHP_EOL.'* ',
                                $sourceList[1]
                            )
                        )
                    );
                    $content = str_replace($sourceList[0], '', $content);
                }
            }
        }
        if (!empty($sources)) {
            $content .= '== Sources =='.$sources;
        }

        return $content;
    }

    /**
     * @param string $content
     *
     * @return string
     */
    protected function replaceRelatedLinks($content, array $events)
    {
        $externalLinks = '';
        preg_match_all(
            '/^===\s*Lien(?:s)? externe(?:s)?\s*===\n((?:(?:-.*)\n)*)/im',
            $content,
            $linkLists,
            PREG_SET_ORDER
        );
        if (is_array($linkLists)) {
            foreach ($linkLists as $linkList) {
                if (!empty($linkList)) {
                    $externalLinks .= trim(preg_replace('/^\s*-\s/', PHP_EOL.'* ', $linkList[1]));
                    $content = str_replace($linkList[0], '', $content);
                }
            }
        }

        $internalLinks = '';
        preg_match_all(
            '/^===\s*Lien(?:s)? interne(?:s)?\s*===\n((?:(?:-.*)\n)*)/im',
            $content,
            $linkLists,
            PREG_SET_ORDER
        );
        foreach ($linkLists as $linkList) {
            if (!empty($linkList)) {
                $internalLinks .= trim(preg_replace('/^\s*-\s/', PHP_EOL.'* ', $linkList[1]));
                $content = str_replace($linkList[0], '', $content);
            }
        }

        foreach ($events as $event) {
            $req = 'SELECT distinct idEvenementGroupeAdresse
    			FROM _evenementAdresseLiee
    			WHERE idEvenement='.mysql_real_escape_string($event);
            $res = $this->e->connexionBdd->requete($req);
            while ($fetch = mysql_fetch_assoc($res)) {
                $addressName = $this->getAddressName(
                    $this->a->getIdAdresseFromIdEvenementGroupeAdresse($fetch['idEvenementGroupeAdresse'])
                );
                $internalLinks .= PHP_EOL.'* [[Adresse:'.$addressName.'|'.$addressName.']]';
            }
        }

        if (!empty($externalLinks) || !empty($internalLinks)) {
            $content .= '== Annexes =='.PHP_EOL;
            if (!empty($internalLinks)) {
                $content .= '=== Liens internes ==='.PHP_EOL.$internalLinks;
            }
            if (!empty($externalLinks)) {
                $content .= '=== Liens externes ==='.PHP_EOL.$externalLinks;
            }
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
            ProcessBuilder::create(['echo', stripslashes($html)])
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

        //Convert relative URLs
        preg_match_all(
            '#\[(?!http)(.+)\s(.+)\]#iU',
            $html,
            $matches,
            PREG_SET_ORDER
        );
        if (is_array($matches)) {
            foreach ($matches as $match) {
                $html = str_replace($match[0], '[http://archi-wiki.org/'.$match[1].' '.$match[2].']', $html);
            }
        }

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

    protected function createGallery($images, $addLinkedAddresses = true, $convertDesc = true)
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

            if ($convertDesc) {
                $description = trim(
                    str_replace(
                        PHP_EOL,
                        ' ',
                        strip_tags(
                            $this->convertHtml(
                                (string) $this->bbCode->convertToDisplay(['text' => $image['description']])
                            )
                        )
                    )
                );
            } else {
                $description = $image['description'];
            }

            if ($addLinkedAddresses) {
                $reqPriseDepuis = 'SELECT ai.idAdresse,  ai.idEvenementGroupeAdresse
                    FROM _adresseImage ai
                    WHERE ai.idImage = '.$image['idImage']."
                    AND ai.prisDepuis='1'
                ";
                $resPriseDepuis = $this->i->connexionBdd->requete($reqPriseDepuis);
                $linkedAdresses = [];
                while ($fetchPriseDepuis = mysql_fetch_assoc($resPriseDepuis)) {
                    $addressName = $this->getAddressName($fetchPriseDepuis['idAdresse']);
                    $linkedAdresses[] = '[[Adresse:'.$addressName.'|'.$addressName.']]';
                }
                if (!empty($linkedAdresses)) {
                    if (!empty($description)) {
                        $description .= '<br/><br/>';
                    }
                    $description .= 'Pris depuis '.implode(', ', $linkedAdresses);
                }
            }
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

    protected function getAddressName($id, $groupId = null)
    {
        if (!isset($groupId)) {
            $groupId = $this->a->getIdEvenementGroupeAdresseFromIdAdresse($id);
        }
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
                    'idEvenementGroupeAdresse' => $groupId,
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
}
