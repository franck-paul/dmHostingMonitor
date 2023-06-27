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

class Backend extends dcNsProcess
{
    protected static $init = false; /** @deprecated since 2.27 */
    public static function init(): bool
    {
        static::$init = My::checkContext(My::BACKEND);

        // dead but useful code, in order to have translations
        __('Hosting Monitor Dashboard Module') . __('Display server information on dashboard');

        return static::$init;
    }

    public static function process(): bool
    {
        if (!static::$init) {
            return false;
        }

        dcCore::app()->addBehaviors([
            // Admin page behaviours
            'adminPageHTMLHead' => [BackendBehaviors::class, 'adminPageHTMLHead'],

            // Dashboard behaviours
            'adminDashboardHeaders'    => [BackendBehaviors::class, 'adminDashboardHeaders'],
            'adminDashboardContentsV2' => [BackendBehaviors::class, 'adminDashboardContents'],

            'adminAfterDashboardOptionsUpdate' => [BackendBehaviors::class, 'adminAfterDashboardOptionsUpdate'],
            'adminDashboardOptionsFormV2'      => [BackendBehaviors::class, 'adminDashboardOptionsForm'],
        ]);

        return true;
    }
}
