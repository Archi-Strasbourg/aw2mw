<?php

namespace AW2MW;

use Mediawiki\Api;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends SymfonyCommand
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
    protected $input;

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
}
