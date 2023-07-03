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
    protected static $init = false; /** @deprecated since 2.27 */
    public static function init(): bool
    {
        static::$init = My::checkContext(My::INSTALL);

        return static::$init;
    }

    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        try {
            $old_version = dcCore::app()->getVersion(My::id());
            if (version_compare((string) $old_version, '2.1', '<')) {
                // Rename preferences workspace
                if (dcCore::app()->auth->user_prefs->exists('dmhostingmonitor')) {
                    dcCore::app()->auth->user_prefs->delWorkspace(My::id());
                    dcCore::app()->auth->user_prefs->renWorkspace('dmhostingmonitor', My::id());
                }
            }

            // Default prefs for hosting monitor
            $preferences = dcCore::app()->auth->user_prefs->get(My::id());

            $preferences->put('activated', false, 'boolean', 'Activate Hosting Monitor', false, true);
            $preferences->put('show_hd_info', true, 'boolean', 'Show hard-disk information', false, true);
            $preferences->put('max_hd_size', 0, 'integer', 'Size of allocated hard-disk (in Mb)', false, true);
            $preferences->put('show_db_info', true, 'boolean', 'Show database information', false, true);
            $preferences->put('max_db_size', 0, 'integer', 'Size of allocated database file (in Mb)', false, true);
            $preferences->put('first_threshold', 80, 'integer', '1st alert threshold (in %)', false, true);
            $preferences->put('second_threshold', 90, 'integer', '2nd alert threshold (in %)', false, true);
            $preferences->put('large', true, 'boolean', 'Large display', false, true);
            $preferences->put('ping', true, 'boolean', 'Check server status', false, true);
            $preferences->put('interval', 300, 'integer', 'Interval between two refresh', false, true);
            $preferences->put('show_gauges', false, 'boolean', 'Show gauges instead of bar graph', false, true);
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }

        return true;
    }
}
