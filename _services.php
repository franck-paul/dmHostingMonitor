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
     * @param     core     <b>dcCore</b>     dcCore instance
     * @param     get     <b>array</b>     cleaned $_GET
     */
    public static function ping($core, $get)
    {
        $rsp      = new xmlTag('check');
        $rsp->ret = true;

        return $rsp;
    }
}
