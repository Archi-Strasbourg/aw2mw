<?php
require_once 'vendor/autoload.php';
use AW2MW\ExportAllCommand;
use AW2MW\ExportAddressCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new ExportAllCommand());
$application->add(new ExportAddressCommand());
$application->run();
