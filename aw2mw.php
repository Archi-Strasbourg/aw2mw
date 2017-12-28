<?php

require_once 'vendor/autoload.php';
use AW2MW\ExportAddressCommand;
use AW2MW\ExportAllAddressCommand;
use AW2MW\ExportAllNewsCommand;
use AW2MW\ExportAllPersonCommand;
use AW2MW\ExportAllPersonCommentsCommand;
use AW2MW\ExportAllRouteCommand;
use AW2MW\ExportAllSourceCommand;
use AW2MW\ExportAllStreetCommand;
use AW2MW\ExportAllUserCommand;
use AW2MW\ExportEventCommand;
use AW2MW\ExportImageCommand;
use AW2MW\ExportNewsCommand;
use AW2MW\ExportPersonCommand;
use AW2MW\ExportRouteCommand;
use AW2MW\ExportSourceCommand;
use AW2MW\ExportStreetCommand;
use AW2MW\ExportUserCommand;
use AW2MW\FixAddressDuplicatesCommand;
use AW2MW\FixSourceRedirectsCommand;
use AW2MW\FixUserPreferenceCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new ExportAllStreetCommand());
$application->add(new ExportAllSourceCommand());
$application->add(new ExportAllUserCommand());
$application->add(new ExportAllRouteCommand());
$application->add(new ExportAllNewsCommand());
$application->add(new ExportAllAddressCommand());
$application->add(new ExportAllPersonCommand());
$application->add(new ExportAllPersonCommentsCommand());
$application->add(new ExportAddressCommand());
$application->add(new ExportSourceCommand());
$application->add(new ExportImageCommand());
$application->add(new ExportUserCommand());
$application->add(new ExportPersonCommand());
$application->add(new ExportStreetCommand());
$application->add(new ExportEventCommand());
$application->add(new ExportNewsCommand());
$application->add(new ExportRouteCommand());
$application->add(new FixAddressDuplicatesCommand());
$application->add(new FixSourceRedirectsCommand());
$application->add(new FixUserPreferenceCommand());
if (isset($_SERVER['argv'])) {
    $application->run();
}
