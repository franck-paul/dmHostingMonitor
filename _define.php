<?php
/**
 * @brief dmHostingMonitor, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Franck Paul and contributors
 *
 * @copyright Franck Paul carnet.franck.paul@gmail.com
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('DC_RC_PATH')) {return;}

$this->registerModule(
    "Hosting Monitor Dashboard Module",        // Name
    "Display server information on dashboard", // Description
    "Franck Paul",                             // Author
    '0.8.1',                                   // Version
    [
        'requires'    => [['core', '2.14']],
        'permissions' => 'admin',                                     // Permissions
        'support'     => 'https://open-time.net/?q=dmHostingMonitor', // Support URL
        'type'        => 'plugin'                                    // Type
    ]
);
