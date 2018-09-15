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

if (!defined('DC_CONTEXT_ADMIN')) {return;}

// dead but useful code, in order to have translations
__('Hosting Monitor Dashboard Module') . __('Display server information on dashboard');

// Dashboard behaviours
$core->addBehavior('adminDashboardHeaders', ['dmHostingMonitorBehaviors', 'adminDashboardHeaders']);
$core->addBehavior('adminDashboardContents', ['dmHostingMonitorBehaviors', 'adminDashboardContents']);

$core->addBehavior('adminAfterDashboardOptionsUpdate', ['dmHostingMonitorBehaviors', 'adminAfterDashboardOptionsUpdate']);
$core->addBehavior('adminDashboardOptionsForm', ['dmHostingMonitorBehaviors', 'adminDashboardOptionsForm']);

# BEHAVIORS
class dmHostingMonitorBehaviors
{
    private static function readableSize($size)
    {
        switch (true) {
            case ($size > 1000000000000):
                $size /= 1000000000000;
                $suffix = __('TB');
                break;
            case ($size > 1000000000):
                $size /= 1000000000;
                $suffix = __('GB');
                break;
            case ($size > 1000000):
                $size /= 1000000;
                $suffix = __('MB');
                break;
            case ($size > 1000):
                $size /= 1000;
                $suffix = __('KB');
                break;
            default:
                $suffix = __('B');
        }
        return round($size, 2) . ' ' . $suffix;
    }

    private static function getDbSize($core)
    {
        // Get current db size in bytes
        $dbSize = 0;
        switch ($core->con->syntax()) {
            case 'sqlite':
                break;
            case 'postgresql':
                $sql = 'SELECT pg_database_size(\'' . $core->con->database() . '\') AS size';
                $rs  = $core->con->select($sql);
                while ($rs->fetch()) {
                    $dbSize += $rs->size;
                }
                break;
            case 'mysql':
                $sql = 'SHOW TABLE STATUS';
                $rs  = $core->con->select($sql);
                while ($rs->fetch()) {
                    $dbSize += $rs->Data_length + $rs->Index_length;
                }
                break;
        }
        return $dbSize;
    }

    private static function getUsedSpace($core)
    {
        // Get current space used by the installation in bytes
        // Take care about potential clean-install :
        // Get size of Dotclear install
        // + Size of outside plugins directories
        // + Size of outside cache directory
        // + Size of (public + themes directories for each blog)
        // Beware of aliases ?

        $hdUsed = 0;
        if (!function_exists('shell_exec')) {
            return $hdUsed;
        }

        // Stack of paths
        $stack = [];

        // Dotclear installation
        $stack[] = '..';

        // Plugins
        $plugins = explode(PATH_SEPARATOR, DC_PLUGINS_ROOT);
        $stack   = array_merge($stack, $plugins);

        // Cache
        $stack[] = DC_TPL_CACHE;

        // For each blog : public and theme folder
        // If not absolute (1st char <> /) then prefix with ../
        $rs = $core->getBlogs();
        while ($rs->fetch()) {
            $settings = new dcSettings($core, $rs->blog_id);
            $settings->addNamespace('system');
            $publicPath = $settings->system->public_path;
            $themesPath = $settings->system->themes_path;
            $stack[]    = (substr($publicPath, 0, 1) == '/' ? $publicPath : '../' . $publicPath);
            $stack[]    = (substr($themesPath, 0, 1) == '/' ? $themesPath : '../' . $themesPath);
        }

        // Stack of real directories
        $dir = [];
        foreach ($stack as $path) {
            // Get real path
            $realPath = path::real($path);
            if (!$realPath) {
                continue;
            }
            // Check if not already counted
            $index = 0;
            foreach ($dir as $folder) {
                if (substr($realPath, 0, strlen($folder)) == $folder) {
                    // Parent folder found in stack : ignore it
                    $realPath = '';
                    break;
                } elseif (substr($folder, 0, strlen($realPath)) == $realPath) {
                    // Child folder found in stack : replace it by parent
                    $dir[$index] = $realPath;
                    $realPath    = '';
                    break;
                }
                $index++;
            }
            if ($realPath != '') {
                $dir[] = $realPath;
                sort($dir);
            }
        }

        // Command : du -k -s <path>
        // Runs only on unix-like systems (Mac OS X, Unix, Linux)
        foreach ($dir as $folder) {
            if ($folder != '') {
                $hdUsed += (int) shell_exec('du -k -s -L ' . $folder);
            }
        }
        $hdUsed *= 1024;

        return $hdUsed;
    }

    private static function getFreeSpace($core)
    {
        // Get current free space on Hard Disk in bytes

        $hdFree = 0;
        if (!function_exists('disk_free_space')) {
            return $hdFree;
        }

        $hdFree = (float) @disk_free_space(".");
        return $hdFree;
    }

    private static function getTotalSpace($core)
    {
        // Get current total space on Hard Disk in bytes

        $hdTotal = 0;
        if (!function_exists('disk_total_space')) {
            return $hdTotal;
        }

        $hdTotal = (float) @disk_total_space(".");
        return $hdTotal;
    }

    private static function getPercentageOf($part, $total)
    {
        $percentage = 0;
        if (($part > 0) && ($total > 0)) {
            $percentage = round($part / $total, 2) * 100;
        }
        return $percentage;
    }

    private static function getLevelClass($value, $firstLevel, $secondLevel)
    {
        if ($firstLevel == 0 && $secondLevel == 0) {
            // No threshold -> always cool
            return 'percent_cool';
        }
        if ($secondLevel == 0) {
            $secondLevel = $firstLevel;
        }
        if ($firstLevel == 0) {
            $firstLevel = $secondLevel;
        }
        if ($secondLevel < $firstLevel) {
            $temp        = $firstLevel;
            $firstLevel  = $secondLevel;
            $secondLevel = $firstLevel;
        }
        if ($value < $firstLevel) {
            return 'percent_cool';
        } elseif ($value < $secondLevel) {
            return 'percent_warning';
        } elseif ($value <= 100) {
            return 'percent_alert';
        } else {
            return 'percent_explode';
        }
    }

    private static function getInfos($core)
    {
        $core->auth->user_prefs->addWorkspace('dmhostingmonitor');

        $first_threshold  = (integer) $core->auth->user_prefs->dmhostingmonitor->first_threshold;
        $second_threshold = (integer) $core->auth->user_prefs->dmhostingmonitor->second_threshold;

        $bargraph = $core->auth->user_prefs->dmhostingmonitor->show_gauges ? false : true;
        $large    = $core->auth->user_prefs->dmhostingmonitor->large;

        if ($core->auth->user_prefs->dmhostingmonitor->show_hd_info) {
            $hdTotal   = self::getTotalSpace($core);
            $hdFree    = self::getFreeSpace($core);
            $hdPercent = self::getPercentageOf($hdFree, $hdTotal);

            $hdUsed    = self::getUsedSpace($core);
            $hdMaxSize = $core->auth->user_prefs->dmhostingmonitor->max_hd_size;
            if ($hdMaxSize == 0) {
                // Use total size of hard-disk
                $hdMaxSize = $hdTotal;
            } else {
                $hdMaxSize *= 1000 * 1000;
            }
            $hdMaxPercent = self::getPercentageOf($hdUsed, $hdMaxSize);
        }

        if ($core->auth->user_prefs->dmhostingmonitor->show_db_info) {
            $dbSize    = self::getDbSize($core);
            $dbMaxSize = $core->auth->user_prefs->dmhostingmonitor->max_db_size;
            $dbMaxSize *= 1000 * 1000;
            $dbMaxPercent = self::getPercentageOf($dbSize, $dbMaxSize);
        }

        $ret = '<div id="hosting-monitor" class="box ' . ($large ? 'medium' : 'small dm_hm_short_info') . '">' .
        '<h3>' . '<img src="' . urldecode(dcPage::getPF('dmHostingMonitor/icon.png')) . '" alt="" />' . ' ' . __('Hosting Monitor') . '</h3>';
        $legend = [];

        $bar = '';
        $pie = '';

        if ($core->auth->user_prefs->dmhostingmonitor->show_hd_info) {
            /* Hard-disk free vs total information */
            if ($hdTotal > 0) {
                $bar .= '<div class="graphe" title="' . __('Hard-disk free') . '">' .
                '<strong class="barre ' . self::getLevelClass(100 - $hdPercent, $first_threshold, $second_threshold) .
                '" style="width: ' . min($hdPercent, 100) . '%;">' . $hdPercent . '%</strong></div>';
                if ($large) {
                    $bar .= '<p class="graphe text">' . __('Hard-disk free:') . ' ' . self::readableSize($hdFree);
                    if ($hdPercent > 0) {
                        $bar .= ' (' . $hdPercent . '% ' . __('of') . ' ' . self::readableSize($hdTotal) . ')';
                    } else {
                        $bar .= ' - ' . __('Hard-disk total:') . ' ' . self::readableSize($hdTotal);
                    }
                    $bar .= '</p>';
                } else {
                    $legend[] = __('HD Free');
                }
                $pie .=
                '<div id="hd-free" class="' . ($large ? 'pie-large' : 'pie-small') . '"></div>' .
                "<script type=\"text/javascript\">\n" .
                'var gauge_hd_free = new JustGage({id: "hd-free",value: ' . (100 - $hdPercent) .
                ',min: 0,max: 100,label: "%",title: "' . __('HD Free') . ' (' . self::readableSize($hdFree) .
                    ')",showInnerShadow: false});' . "\n" .
                    "</script>\n";
            }
            /* Dotclear used vs allocated space information */
            if ($hdUsed > 0) {
                $bar .= '<div class="graphe" title="' . __('Hard-disk used') . '">' .
                '<strong class="barre ' . self::getLevelClass($hdMaxPercent, $first_threshold, $second_threshold) .
                '" style="width: ' . min($hdMaxPercent, 100) . '%;">' . $hdMaxPercent . '%</strong></div>';
                if ($large) {
                    $bar .= '<p class="graphe text">' . __('Hard-disk used:') . ' ' . self::readableSize($hdUsed);
                    if ($hdMaxSize > 0) {
                        if ($hdMaxPercent > 0) {
                            $bar .= ' (' . $hdMaxPercent . '% ' . __('of') . ' ' . self::readableSize($hdMaxSize) . ')';
                        } else {
                            if ($hdMaxSize != $hdTotal) {
                                $bar .= ' - ' . __('Hard-disk limit:') . ' ' . self::readableSize($hdMaxSize);
                            }
                        }
                    }
                    $bar .= '</p>';
                } else {
                    $legend[] = __('HD Used');
                }
                $pie .=
                '<div id="hd-used" class="' . ($large ? 'pie-large' : 'pie-small') . '"></div>' .
                "<script type=\"text/javascript\">\n" .
                'var gauge_hd_used = new JustGage({id: "hd-used",value: ' . ($hdMaxSize > 0 ? $hdMaxPercent : 0) .
                ',min: 0,max: 100,label: "%",title: "' . __('HD Used') . ' (' . self::readableSize($hdUsed) .
                    ')",showInnerShadow: false});' . "\n" .
                    "</script>\n";
            }
        }

        if ($core->auth->user_prefs->dmhostingmonitor->show_db_info) {
            /* Database information */
            if ($dbSize > 0) {
                $bar .= '<div class="graphe" title="' . __('Database size') . '">' .
                '<strong class="barre ' . self::getLevelClass($dbMaxPercent, $first_threshold, $second_threshold) .
                '" style="width: ' . min($dbMaxPercent, 100) . '%;">' . $dbMaxPercent . '%</strong></div>';
                if ($large) {
                    $bar .= '<p class="graphe text">' . __('Database size:') . ' ' . self::readableSize($dbSize);
                    if ($dbMaxSize > 0) {
                        if ($dbMaxPercent > 0) {
                            $bar .= ' (' . $dbMaxPercent . '% ' . __('of') . ' ' . self::readableSize($dbMaxSize) . ')';
                        } else {
                            $bar .= ' - ' . __('Database limit:') . ' ' . self::readableSize($dbMaxSize);
                        }
                    }
                    $bar .= '</p>';
                } else {
                    $legend[] = __('DB Size');
                }
                $pie .=
                '<div id="db-used" class="' . ($large ? 'pie-large' : 'pie-small') . '"></div>' .
                "<script type=\"text/javascript\">\n" .
                'var gauge_db_used = new JustGage({id: "db-used",value: ' . ($dbMaxSize > 0 ? $dbMaxPercent : 0) .
                ',min: 0,max: 100,label: "%",title: "' . __('DB Size') . ' (' . self::readableSize($dbSize) .
                    ')",showInnerShadow: false});' . "\n" .
                    "</script>\n";
            }
        }

        if (count($legend)) {
            $bar .= '<p class="graphe-legend">' . implode("; ", $legend) . '</p>';
        }

        $ret .= ($bargraph ? $bar : $pie);
        $ret .= '</div>';

        return $ret;
    }

    public static function adminDashboardContents($core, $contents)
    {
        // Add module to the contents stack
        $core->auth->user_prefs->addWorkspace('dmhostingmonitor');
        if ($core->auth->user_prefs->dmhostingmonitor->activated) {
            if ($core->auth->user_prefs->dmhostingmonitor->show_hd_info ||
                $core->auth->user_prefs->dmhostingmonitor->show_db_info) {
                $contents[] = new ArrayObject([self::getInfos($core)]);
            }
        }
    }

    public static function adminDashboardHeaders()
    {
        global $core;

        $core->auth->user_prefs->addWorkspace('dmhostingmonitor');
        if ($core->auth->user_prefs->dmhostingmonitor->activated) {

            $ret = '<script type="text/javascript">' . "\n" .
            dcPage::jsVar('dotclear.dmHostingMonitor_Ping', $core->auth->user_prefs->dmhostingmonitor->ping) .
            dcPage::jsVar('dotclear.dmHostingMonitor_Offline', __('Server offline')) .
            dcPage::jsVar('dotclear.dmHostingMonitor_Online', __('Server online')) .
            "</script>\n";

            if ($core->auth->user_prefs->dmhostingmonitor->show_hd_info ||
                $core->auth->user_prefs->dmhostingmonitor->show_db_info) {
                $ret .=
                dcPage::cssLoad(urldecode(dcPage::getPF('dmHostingMonitor/style.css')), 'screen',
                    $core->getVersion('dmHostingMonitor')) . "\n" .
                dcPage::jsLoad(urldecode(dcPage::getPF('dmHostingMonitor/js/raphael.2.1.0.min.js')),
                    $core->getVersion('dmHostingMonitor')) . "\n" .
                dcPage::jsLoad(urldecode(dcPage::getPF('dmHostingMonitor/js/justgage.1.0.1.min.js')),
                    $core->getVersion('dmHostingMonitor')) . "\n";
            }
            if ($core->auth->user_prefs->dmhostingmonitor->ping) {
                $ret .=
                dcPage::jsLoad(urldecode(dcPage::getPF('dmHostingMonitor/js/service.js')),
                    $core->getVersion('dmHostingMonitor')) . "\n";
            }
            return $ret;
        }
    }

    public static function adminAfterDashboardOptionsUpdate($userID)
    {
        global $core;

        // Get and store user's prefs for plugin options
        $core->auth->user_prefs->addWorkspace('dmhostingmonitor');
        try {
            // Hosting monitor options
            $core->auth->user_prefs->dmhostingmonitor->put('activated', !empty($_POST['activated']), 'boolean');
            $core->auth->user_prefs->dmhostingmonitor->put('show_hd_info', !empty($_POST['show_hd_info']), 'boolean');
            $core->auth->user_prefs->dmhostingmonitor->put('max_hd_size', (integer) $_POST['max_hd_size'], 'integer');
            $core->auth->user_prefs->dmhostingmonitor->put('show_db_info', !empty($_POST['show_db_info']), 'boolean');
            $core->auth->user_prefs->dmhostingmonitor->put('max_db_size', (integer) $_POST['max_db_size'], 'integer');
            $core->auth->user_prefs->dmhostingmonitor->put('first_threshold', (integer) $_POST['first_threshold'], 'integer');
            $core->auth->user_prefs->dmhostingmonitor->put('second_threshold', (integer) $_POST['second_threshold'], 'integer');
            $core->auth->user_prefs->dmhostingmonitor->put('large', empty($_POST['small']), 'boolean');
            $core->auth->user_prefs->dmhostingmonitor->put('show_gauges', !empty($_POST['show_gauges']), 'boolean');
            $core->auth->user_prefs->dmhostingmonitor->put('ping', !empty($_POST['ping']), 'boolean');
        } catch (Exception $e) {
            $core->error->add($e->getMessage());
        }
    }

    public static function adminDashboardOptionsForm($core)
    {
        // Add fieldset for plugin options
        $core->auth->user_prefs->addWorkspace('dmhostingmonitor');

        echo '<div class="fieldset" id="dmhostingmonitor"><h4>' . __('Hosting monitor on dashboard') . '</h4>' .

        '<p>' .
        form::checkbox('activated', 1, $core->auth->user_prefs->dmhostingmonitor->activated) . ' ' .
        '<label for="activated" class="classic">' . __('Activate module') . '</label></p>' .

        '<hr />' .

        '<p>' .
        form::checkbox('show_hd_info', 1, $core->auth->user_prefs->dmhostingmonitor->show_hd_info) . ' ' .
        '<label for="show_hd_info" class="classic">' . __('Show hard-disk information') . '</label></p>' .

        '<p><label for="max_hd_size" class="classic">' . __('Allocated hard-disk size (in Mb, leave empty for unlimited):') . '</label> ' .
        form::field('max_hd_size', 7, 10, (integer) $core->auth->user_prefs->dmhostingmonitor->max_hd_size) .
        '</p>' .

        '<hr />' .

        '<p>' .
        form::checkbox('show_db_info', 1, $core->auth->user_prefs->dmhostingmonitor->show_db_info) . ' ' .
        '<label for="show_db_info" class="classic">' . __('Show database information') . '</label></p>' .

        '<p><label for="max_db_size" class="classic">' . __('Allocated database size (in Mb, leave empty for unlimited):') . '</label> ' .
        form::field('max_db_size', 7, 10, (integer) $core->auth->user_prefs->dmhostingmonitor->max_db_size) .
        '</p>' .

        '<p><label for="first_threshold" class="classic">' . __('1st threshold (in %, leave empty to ignore):') . '</label> ' .
        form::field('first_threshold', 2, 3, (integer) $core->auth->user_prefs->dmhostingmonitor->first_threshold) .
        '</p>' .

        '<p><label for="second_threshold" class="classic">' . __('2nd threshold (in %, leave empty to ignore):') . '</label> ' .
        form::field('second_threshold', 2, 3, (integer) $core->auth->user_prefs->dmhostingmonitor->second_threshold) .
        '</p>' .

        '<hr />' .

        '<p>' .
        form::checkbox('small', 1, !$core->auth->user_prefs->dmhostingmonitor->large) . ' ' .
        '<label for="small" class="classic">' . __('Small screen') . '</label></p>' .

        '<p>' .
        form::checkbox('show_gauges', 1, $core->auth->user_prefs->dmhostingmonitor->show_gauges) . ' ' .
        '<label for="show_gauges" class="classic">' . __('Show gauges instead of bar graph') . '</label></p>' .

        '<hr />' .

        '<p>' .
        form::checkbox('ping', 1, $core->auth->user_prefs->dmhostingmonitor->ping) . ' ' .
        '<label for="ping" class="classic">' . __('Check server status') . '</label></p>' .

            '</div>';
    }
}
