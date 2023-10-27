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

use Dotclear\App;
use Dotclear\Core\Process;
use Exception;

class Install extends Process
{
    public static function init(): bool
    {
        return self::status(My::checkContext(My::INSTALL));
    }

    public static function process(): bool
    {
        if (!self::status()) {
            return false;
        }

        try {
            $old_version = App::version()->getVersion(My::id());
            // Rename preferences workspace
            if (version_compare((string) $old_version, '2.1', '<') && App::auth()->prefs()->exists('dmhostingmonitor')) {
                App::auth()->prefs()->delWorkspace(My::id());
                App::auth()->prefs()->renWorkspace('dmhostingmonitor', My::id());
            }

            // Default prefs for hosting monitor
            $preferences = My::prefs();
            if ($preferences) {
                $preferences->put('activated', false, App::userWorkspace()::WS_BOOL, 'Activate Hosting Monitor', false, true);
                $preferences->put('show_hd_info', true, App::userWorkspace()::WS_BOOL, 'Show hard-disk information', false, true);
                $preferences->put('max_hd_size', 0, App::userWorkspace()::WS_INT, 'Size of allocated hard-disk (in Mb)', false, true);
                $preferences->put('show_db_info', true, App::userWorkspace()::WS_BOOL, 'Show database information', false, true);
                $preferences->put('max_db_size', 0, App::userWorkspace()::WS_INT, 'Size of allocated database file (in Mb)', false, true);
                $preferences->put('first_threshold', 80, App::userWorkspace()::WS_INT, '1st alert threshold (in %)', false, true);
                $preferences->put('second_threshold', 90, App::userWorkspace()::WS_INT, '2nd alert threshold (in %)', false, true);
                $preferences->put('large', true, App::userWorkspace()::WS_BOOL, 'Large display', false, true);
                $preferences->put('ping', true, App::userWorkspace()::WS_BOOL, 'Check server status', false, true);
                $preferences->put('interval', 300, App::userWorkspace()::WS_INT, 'Interval between two refresh', false, true);
                $preferences->put('show_gauges', false, App::userWorkspace()::WS_BOOL, 'Show gauges instead of bar graph', false, true);
            }
        } catch (Exception $exception) {
            App::error()->add($exception->getMessage());
        }

        return true;
    }
}
