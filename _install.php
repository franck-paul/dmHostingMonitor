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
if (!defined('DC_CONTEXT_ADMIN')) {
    return;
}

if (!dcCore::app()->newVersion(basename(__DIR__), dcCore::app()->plugins->moduleInfo(basename(__DIR__), 'version'))) {
    return;
}

try {
    // Default prefs for last comments
    dcCore::app()->auth->user_prefs->dmhostingmonitor->put('activated', false, 'boolean', 'Activate Hosting Monitor', false, true);
    dcCore::app()->auth->user_prefs->dmhostingmonitor->put('show_hd_info', true, 'boolean', 'Show hard-disk information', false, true);
    dcCore::app()->auth->user_prefs->dmhostingmonitor->put('max_hd_size', 0, 'integer', 'Size of allocated hard-disk (in Mb)', false, true);
    dcCore::app()->auth->user_prefs->dmhostingmonitor->put('show_db_info', true, 'boolean', 'Show database information', false, true);
    dcCore::app()->auth->user_prefs->dmhostingmonitor->put('max_db_size', 0, 'integer', 'Size of allocated database file (in Mb)', false, true);
    dcCore::app()->auth->user_prefs->dmhostingmonitor->put('first_threshold', 80, 'integer', '1st alert threshold (in %)', false, true);
    dcCore::app()->auth->user_prefs->dmhostingmonitor->put('second_threshold', 90, 'integer', '2nd alert threshold (in %)', false, true);
    dcCore::app()->auth->user_prefs->dmhostingmonitor->put('large', true, 'boolean', 'Large display', false, true);
    dcCore::app()->auth->user_prefs->dmhostingmonitor->put('ping', true, 'boolean', 'Check server status', false, true);

    return true;
} catch (Exception $e) {
    dcCore::app()->error->add($e->getMessage());
}

return false;
