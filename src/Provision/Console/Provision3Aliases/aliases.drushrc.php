<?php

/**
 * @file aliases.drushrc.php
 *
 * This file is used as a shim between Provision4 and Aegir Provision 7.x-3.x.
 *
 * It is used to generate "drush aliases" from the Provision 4 contexts.
 *
 * When you run bin/drush in the provision folder, it will load all of these aliases.
 *
 * This only works if drushrc.php is configured to look in this folder for aliases.
 *
 * This is done on `composer install` using  Aegir\Provision\Console\ComposerScripts:
 *
 * We copy a small drushrc.php file into the vendor/drush/drush folder so we can
 * force configuration on every bin/drush command.
 *
 * The reason we need a shim is that Provision 3.x has a lot of drush commands
 * that will take some time to migrate to Symfony Console commands.
 *
 * For now we will run both side by side.
 *
 * When all Provision commands are converted, we will bump to 4.1.x
 *
 */


# Load Provision4 Autoloader
include dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/autoload.php';

# Loop through all Provision4 Contexts
foreach (Aegir\Provision\Provision::getProvision()->getAllContexts() as $context) {

    # Load all context properties as an alias.
    $aliases[$context->name] = $context->getProperties();

    # @TODO: Refactor the alias data so it is compatible with Provision 3.x API.

}