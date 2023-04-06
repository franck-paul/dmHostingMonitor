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
declare(strict_types=1);

namespace Dotclear\Plugin\dmHostingMonitor;

use dcCore;
use dcNsProcess;
use Exception;

class Install extends dcNsProcess
{
    public static function init(): bool
    {
        static::$init = defined('DC_CONTEXT_ADMIN')
            && My::phpCompliant()
            && dcCore::app()->newVersion(My::id(), dcCore::app()->plugins->moduleInfo(My::id(), 'version'));

        return static::$init;
    }

    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        try {
            // Default prefs for last comments
            $settings = dcCore::app()->auth->user_prefs->dmhostingmonitor;

            $settings->put('activated', false, 'boolean', 'Activate Hosting Monitor', false, true);
            $settings->put('show_hd_info', true, 'boolean', 'Show hard-disk information', false, true);
            $settings->put('max_hd_size', 0, 'integer', 'Size of allocated hard-disk (in Mb)', false, true);
            $settings->put('show_db_info', true, 'boolean', 'Show database information', false, true);
            $settings->put('max_db_size', 0, 'integer', 'Size of allocated database file (in Mb)', false, true);
            $settings->put('first_threshold', 80, 'integer', '1st alert threshold (in %)', false, true);
            $settings->put('second_threshold', 90, 'integer', '2nd alert threshold (in %)', false, true);
            $settings->put('large', true, 'boolean', 'Large display', false, true);
            $settings->put('ping', true, 'boolean', 'Check server status', false, true);

            return true;
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }

        return true;
    }
}
