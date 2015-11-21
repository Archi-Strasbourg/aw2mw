<?php
require_once 'vendor/autoload.php';
use AW2MW\ExportAllCommand;
use AW2MW\ExportAddressCommand;
use Symfony\Component\Console\Application;
define('CONFIG_FILE', __DIR__.'/config.php');
$application = new Application();
$application->add(new ExportAllCommand());
$application->add(new ExportAddressCommand());
$application->run();
