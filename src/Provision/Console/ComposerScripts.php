<?php

/**
 * @file ComposerScripts.php
 *
 * This file is not meant to be loaded by provision as it requires Composer classes.
 *
 * Currently it only contains one "script": writeDrushRcForVendorDrushDrush()
 *
 * This is used on composer install to copy a drushrc.php file into vendor/drush/drush.
 *
 * This drushrc.php file is used to include the provision 3.x drush commands.
 *
 */

namespace Aegir\Provision\Console;

use Composer\Script\Event;
use Composer\Installer\PackageEvent;

class ComposerScripts {

    /**
     * @param \Composer\Script\Event $event
     *
     * @throws \Exception
     */
    public static function writeDrushRcForVendorDrushDrush(Event $event)
    {
        $root_dir = dirname(dirname(dirname(dirname(__FILE__)))) . '/vendor/drush/drush';
        if (file_exists($root_dir) && is_writable($root_dir)) {
            $drushrc = <<<PHP
<?php

// Includes the Aegir Provision 3.x drush commands when running bin/drush.
\$options['include'] = array(
    dirname(dirname(dirname(__FILE__))) . '/aegir/provision',
);

// Dynamically loads Provision4 context data into aegir/provision 7.x-3.x drush aliases!
\$options['alias-path'] = array(
    dirname(dirname(dirname(dirname(__FILE__)))) . '/src/Provision/Console/Provision3Aliases',
);

PHP;
            if (file_put_contents($root_dir . '/drushrc.php', $drushrc)) {
                print "Wrote drushrc.php to $root_dir \n";
            }
        }
        else {
            throw new \Exception("Directory $root_dir does not exist or is not writable. Provision cannot load it's commands.");
        }
    }
}