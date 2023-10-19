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
$this->registerModule(
    'Hosting Monitor Dashboard Module',
    'Display server information on dashboard',
    'Franck Paul',
    '3.0',
    [
        'requires' => [
            ['core', '2.28'],
            ['dmHelper', '2.0'],
        ],
        'permissions' => dcCore::app()->auth->makePermissions([
            dcAuth::PERMISSION_ADMIN,
        ]),
        'type'     => 'plugin',
        'priority' => 1001,     // Must be higher than dmHelper priority which should be 1000 (default)
        'settings' => [
            'pref' => '#user-favorites.dmhostingmonitor',
        ],

        'details'    => 'https://open-time.net/?q=dmHostingMonitor',
        'support'    => 'https://github.com/franck-paul/dmHostingMonitor',
        'repository' => 'https://raw.githubusercontent.com/franck-paul/dmHostingMonitor/master/dcstore.xml',
    ]
);
