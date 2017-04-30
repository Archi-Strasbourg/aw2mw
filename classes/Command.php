<?php

namespace AW2MW;

use Mediawiki\Api;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Command extends SymfonyCommand
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
    protected $pageSaver;
    protected $loginManager;
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
        $this->pageSaver = new PageSaver($this->services);
        $this->fileUploader = $this->services->newFileUploader();
        $this->loginManager = new LoginManager($this->api, $this->config);
        $this->input = $input;
        $this->output = $output;
    }
}
