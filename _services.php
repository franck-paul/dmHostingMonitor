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

class dmHostingMonitorRest
{
    /**
     * Serve method to ping current server.
     *
     * @param      array   $get    The get
     *
     * @return     xmlTag  The xml tag.
     */
    public static function ping($get)
    {
        return [
            'ret' => true,
        ];
    }
}
