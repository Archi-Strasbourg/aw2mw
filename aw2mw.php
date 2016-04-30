<?php
require_once 'vendor/autoload.php';
use AW2MW\ExportAllCommand;
use AW2MW\ExportAddressCommand;
use AW2MW\ExportSourceCommand;
use AW2MW\ExportImageCommand;
use AW2MW\ExportUserCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new ExportAllCommand());
$application->add(new ExportAddressCommand());
$application->add(new ExportSourceCommand());
$application->add(new ExportImageCommand());
$application->add(new ExportUserCommand());
$application->run();
