<?php

require_once 'vendor/autoload.php';
use AW2MW\ExportAddressCommand;
use AW2MW\ExportAllCommand;
use AW2MW\ExportAllSourceCommand;
use AW2MW\ExportAllStreetCommand;
use AW2MW\ExportAllUserCommand;
use AW2MW\ExportEventCommand;
use AW2MW\ExportImageCommand;
use AW2MW\ExportNewsCommand;
use AW2MW\ExportPersonCommand;
use AW2MW\ExportSourceCommand;
use AW2MW\ExportStreetCommand;
use AW2MW\ExportUserCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new ExportAllCommand());
$application->add(new ExportAllStreetCommand());
$application->add(new ExportAllSourceCommand());
$application->add(new ExportAllUserCommand());
$application->add(new ExportAddressCommand());
$application->add(new ExportSourceCommand());
$application->add(new ExportImageCommand());
$application->add(new ExportUserCommand());
$application->add(new ExportPersonCommand());
$application->add(new ExportStreetCommand());
$application->add(new ExportEventCommand());
$application->add(new ExportNewsCommand());
if (isset($_SERVER['argv'])) {
    $application->run();
}
