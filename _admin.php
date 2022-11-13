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

// dead but useful code, in order to have translations
__('Hosting Monitor Dashboard Module') . __('Display server information on dashboard');

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

    private static function getDbSize()
    {
        // Get current db size in bytes
        $dbSize = 0;
        switch (dcCore::app()->con->syntax()) {
            case 'sqlite':
                break;
            case 'postgresql':
                $sql = 'SELECT pg_database_size(\'' . dcCore::app()->con->database() . '\') AS size';
                $rs  = new dcRecord(dcCore::app()->con->select($sql));
                while ($rs->fetch()) {
                    $dbSize += $rs->size;
                }

                break;
            case 'mysql':
                $sql = 'SHOW TABLE STATUS';
                $rs  = new dcRecord(dcCore::app()->con->select($sql));
                while ($rs->fetch()) {
                    $dbSize += $rs->Data_length + $rs->Index_length;
                }

                break;
        }

        return $dbSize;
    }

    private static function getUsedSpace()
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
        $rs = dcCore::app()->getBlogs();
        while ($rs->fetch()) {
            $settings = new dcSettings($rs->blog_id);
            $settings->addNamespace('system');
            $publicPath = $settings->system->public_path;   // @phpstan-ignore-line
            $themesPath = $settings->system->themes_path;   // @phpstan-ignore-line
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
            $hdUsed += (int) shell_exec('du -k -s -L ' . $folder);
        }
        $hdUsed *= 1024;

        return $hdUsed;
    }

    private static function getFreeSpace()
    {
        // Get current free space on Hard Disk in bytes

        $hdFree = 0;
        if (!function_exists('disk_free_space')) {
            return $hdFree;
        }

        return (float) @disk_free_space('.');
    }

    private static function getTotalSpace()
    {
        // Get current total space on Hard Disk in bytes

        $hdTotal = 0;
        if (!function_exists('disk_total_space')) {
            return $hdTotal;
        }

        return (float) @disk_total_space('.');
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
            [$firstLevel, $secondLevel] = [$secondLevel, $firstLevel];
        }
        if ($value < $firstLevel) {
            return 'percent_cool';
        } elseif ($value < $secondLevel) {
            return 'percent_warning';
        } elseif ($value <= 100) {
            return 'percent_alert';
        }

        return 'percent_explode';
    }

    private static function getInfos()
    {
        $dbSize       = 0;
        $dbMaxSize    = 0;
        $dbMaxPercent = 0;
        $hdTotal      = 0;
        $hdFree       = 0;
        $hdPercent    = 0;
        $hdUsed       = 0;
        $hdMaxSize    = 0;
        $hdMaxPercent = 0;

        dcCore::app()->auth->user_prefs->addWorkspace('dmhostingmonitor');

        $first_threshold  = (int) dcCore::app()->auth->user_prefs->dmhostingmonitor->first_threshold;
        $second_threshold = (int) dcCore::app()->auth->user_prefs->dmhostingmonitor->second_threshold;

        $bargraph = dcCore::app()->auth->user_prefs->dmhostingmonitor->show_gauges ? false : true;
        $large    = dcCore::app()->auth->user_prefs->dmhostingmonitor->large;

        if (dcCore::app()->auth->user_prefs->dmhostingmonitor->show_hd_info) {
            $hdTotal   = self::getTotalSpace();
            $hdFree    = self::getFreeSpace();
            $hdPercent = self::getPercentageOf($hdFree, $hdTotal);

            $hdUsed    = self::getUsedSpace();
            $hdMaxSize = dcCore::app()->auth->user_prefs->dmhostingmonitor->max_hd_size;
            if ($hdMaxSize == 0) {
                // Use total size of hard-disk
                $hdMaxSize = $hdTotal;
            } else {
                $hdMaxSize *= 1000 * 1000;
            }
            $hdMaxPercent = self::getPercentageOf($hdUsed, $hdMaxSize);
        }

        if (dcCore::app()->auth->user_prefs->dmhostingmonitor->show_db_info) {
            $dbSize    = self::getDbSize();
            $dbMaxSize = dcCore::app()->auth->user_prefs->dmhostingmonitor->max_db_size;
            $dbMaxSize *= 1000 * 1000;
            $dbMaxPercent = self::getPercentageOf($dbSize, $dbMaxSize);
        }

        $ret = '<div id="hosting-monitor" class="box ' . ($large ? 'medium' : 'small dm_hm_short_info') . '">' .
        '<h3>' . '<img src="' . urldecode(dcPage::getPF('dmHostingMonitor/icon.png')) . '" alt="" />' . ' ' . __('Hosting Monitor') . '</h3>';
        $legend = [];

        $bar  = '';
        $pie  = '';
        $json = [];

        if (dcCore::app()->auth->user_prefs->dmhostingmonitor->show_hd_info) {
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
                $pie .= '<div id="hd-free" class="' . ($large ? 'pie-large' : 'pie-small') . '">' .
                '<p>' . __('HD Free') . ($large ? ' (' . self::readableSize($hdFree) . ')' : '') . '</p>' .
                '</div>';
                $json['hd_free'] = 100 - $hdPercent;
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
                $pie .= '<div id="hd-used" class="' . ($large ? 'pie-large' : 'pie-small') . '">' .
                '<p>' . __('HD Used') . ($large ? ' (' . self::readableSize($hdUsed) . ')' : '') . '</p>' .
                '</div>';
                $json['hd_used'] = $hdMaxSize > 0 ? $hdMaxPercent : 0;
            }
        }

        if (dcCore::app()->auth->user_prefs->dmhostingmonitor->show_db_info) {
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
                $pie .= '<div id="db-used" class="' . ($large ? 'pie-large' : 'pie-small') . '">' .
                '<p>' . __('DB Size') . ($large ? ' (' . self::readableSize($dbSize) . ')' : '') . '</p>' .
                '</div>';
                $json['db_used'] = $dbMaxSize > 0 ? $dbMaxPercent : 0;
            }
        }

        if (count($legend)) {
            $bar .= '<p class="graphe-legend">' . implode('; ', $legend) . '</p>';
        }

        $ret .= ($bargraph ? $bar : $pie);
        $ret .= '</div>';

        if ($pie !== '') {
            $ret .= dcPage::jsJson('dm_hostingmonitor_values', $json) .
                    dcPage::jsLoad(
                        urldecode(dcPage::getPF('dmHostingMonitor/js/admin.js')),
                        dcCore::app()->getVersion('dmHostingMonitor')
                    );
        }

        return $ret;
    }

    public static function adminDashboardContents($core, $contents)
    {
        // Add module to the contents stack
        dcCore::app()->auth->user_prefs->addWorkspace('dmhostingmonitor');
        if (dcCore::app()->auth->user_prefs->dmhostingmonitor->activated) {
            if (dcCore::app()->auth->user_prefs->dmhostingmonitor->show_hd_info || dcCore::app()->auth->user_prefs->dmhostingmonitor->show_db_info) {
                $contents[] = new ArrayObject([self::getInfos()]);
            }
        }
    }

    public static function adminDashboardHeaders()
    {
        dcCore::app()->auth->user_prefs->addWorkspace('dmhostingmonitor');
        if (dcCore::app()->auth->user_prefs->dmhostingmonitor->activated) {
            $ret = '';

            if (dcCore::app()->auth->user_prefs->dmhostingmonitor->show_hd_info || dcCore::app()->auth->user_prefs->dmhostingmonitor->show_db_info) {
                $ret .= dcPage::cssLoad(
                    urldecode(dcPage::getPF('dmHostingMonitor/css/style.min.css')),
                    'screen',
                    dcCore::app()->getVersion('dmHostingMonitor')
                ) . "\n" .
                dcPage::jsLoad(
                    urldecode(dcPage::getPF('dmHostingMonitor/js/raphael.min.js')),
                    dcCore::app()->getVersion('dmHostingMonitor')
                ) . "\n" .
                dcPage::jsLoad(
                    urldecode(dcPage::getPF('dmHostingMonitor/js/justgage.min.js')),
                    dcCore::app()->getVersion('dmHostingMonitor')
                ) . "\n";
            }

            return $ret;
        }
    }

    public static function adminPageHTMLHead()
    {
        dcCore::app()->auth->user_prefs->addWorkspace('dmhostingmonitor');
        if (dcCore::app()->auth->user_prefs->dmhostingmonitor->activated && dcCore::app()->auth->user_prefs->dmhostingmonitor->ping) {
            echo
                dcPage::jsJson('dm_hostingmonitor', [
                    'dmHostingMonitor_Ping'    => dcCore::app()->auth->user_prefs->dmhostingmonitor->ping,
                    'dmHostingMonitor_Offline' => __('Server offline'),
                    'dmHostingMonitor_Online'  => __('Server online'),
                ]) .
                dcPage::jsLoad(
                    urldecode(dcPage::getPF('dmHostingMonitor/js/service.js')),
                    dcCore::app()->getVersion('dmHostingMonitor')
                ) . "\n";
        }
    }

    public static function adminAfterDashboardOptionsUpdate()
    {
        // Get and store user's prefs for plugin options
        dcCore::app()->auth->user_prefs->addWorkspace('dmhostingmonitor');

        try {
            // Hosting monitor options
            dcCore::app()->auth->user_prefs->dmhostingmonitor->put('activated', !empty($_POST['activated']), 'boolean');
            dcCore::app()->auth->user_prefs->dmhostingmonitor->put('show_hd_info', !empty($_POST['show_hd_info']), 'boolean');
            dcCore::app()->auth->user_prefs->dmhostingmonitor->put('max_hd_size', (int) $_POST['max_hd_size'], 'integer');
            dcCore::app()->auth->user_prefs->dmhostingmonitor->put('show_db_info', !empty($_POST['show_db_info']), 'boolean');
            dcCore::app()->auth->user_prefs->dmhostingmonitor->put('max_db_size', (int) $_POST['max_db_size'], 'integer');
            dcCore::app()->auth->user_prefs->dmhostingmonitor->put('first_threshold', (int) $_POST['first_threshold'], 'integer');
            dcCore::app()->auth->user_prefs->dmhostingmonitor->put('second_threshold', (int) $_POST['second_threshold'], 'integer');
            dcCore::app()->auth->user_prefs->dmhostingmonitor->put('large', empty($_POST['small']), 'boolean');
            dcCore::app()->auth->user_prefs->dmhostingmonitor->put('show_gauges', !empty($_POST['show_gauges']), 'boolean');
            dcCore::app()->auth->user_prefs->dmhostingmonitor->put('ping', !empty($_POST['ping']), 'boolean');
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }
    }

    public static function adminDashboardOptionsForm()
    {
        // Add fieldset for plugin options
        dcCore::app()->auth->user_prefs->addWorkspace('dmhostingmonitor');

        echo '<div class="fieldset" id="dmhostingmonitor"><h4>' . __('Hosting monitor on dashboard') . '</h4>' .

        '<p>' .
        form::checkbox('activated', 1, dcCore::app()->auth->user_prefs->dmhostingmonitor->activated) . ' ' .
        '<label for="activated" class="classic">' . __('Activate module') . '</label></p>' .

        '<hr />' .

        '<p>' .
        form::checkbox('show_hd_info', 1, dcCore::app()->auth->user_prefs->dmhostingmonitor->show_hd_info) . ' ' .
        '<label for="show_hd_info" class="classic">' . __('Show hard-disk information') . '</label></p>' .

        '<p><label for="max_hd_size" class="classic">' . __('Allocated hard-disk size (in Mb, leave empty for unlimited):') . '</label> ' .
        form::number('max_hd_size', 1, 9999999, dcCore::app()->auth->user_prefs->dmhostingmonitor->max_hd_size) .
        '</p>' .

        '<hr />' .

        '<p>' .
        form::checkbox('show_db_info', 1, dcCore::app()->auth->user_prefs->dmhostingmonitor->show_db_info) . ' ' .
        '<label for="show_db_info" class="classic">' . __('Show database information') . '</label></p>' .

        '<p><label for="max_db_size" class="classic">' . __('Allocated database size (in Mb, leave empty for unlimited):') . '</label> ' .
        form::number('max_db_size', 1, 9999999, dcCore::app()->auth->user_prefs->dmhostingmonitor->max_db_size) .
        '</p>' .

        '<p><label for="first_threshold" class="classic">' . __('1st threshold (in %, leave empty to ignore):') . '</label> ' .
        form::number('first_threshold', 1, 100, dcCore::app()->auth->user_prefs->dmhostingmonitor->first_threshold) .
        '</p>' .

        '<p><label for="second_threshold" class="classic">' . __('2nd threshold (in %, leave empty to ignore):') . '</label> ' .
        form::number('second_threshold', 1, 100, dcCore::app()->auth->user_prefs->dmhostingmonitor->second_threshold) .
        '</p>' .

        '<hr />' .

        '<p>' .
        form::checkbox('small', 1, !dcCore::app()->auth->user_prefs->dmhostingmonitor->large) . ' ' .
        '<label for="small" class="classic">' . __('Small screen') . '</label></p>' .

        '<p>' .
        form::checkbox('show_gauges', 1, dcCore::app()->auth->user_prefs->dmhostingmonitor->show_gauges) . ' ' .
        '<label for="show_gauges" class="classic">' . __('Show gauges instead of bar graph') . '</label></p>' .

        '<hr />' .

        '<p>' .
        form::checkbox('ping', 1, dcCore::app()->auth->user_prefs->dmhostingmonitor->ping) . ' ' .
        '<label for="ping" class="classic">' . __('Check server status') . '</label></p>' .

            '</div>';
    }
}

// Admin page behaviours
dcCore::app()->addBehavior('adminPageHTMLHead', [dmHostingMonitorBehaviors::class, 'adminPageHTMLHead']);

// Dashboard behaviours
dcCore::app()->addBehavior('adminDashboardHeaders', [dmHostingMonitorBehaviors::class, 'adminDashboardHeaders']);
dcCore::app()->addBehavior('adminDashboardContents', [dmHostingMonitorBehaviors::class, 'adminDashboardContents']);

dcCore::app()->addBehavior('adminAfterDashboardOptionsUpdate', [dmHostingMonitorBehaviors::class, 'adminAfterDashboardOptionsUpdate']);
dcCore::app()->addBehavior('adminDashboardOptionsForm', [dmHostingMonitorBehaviors::class, 'adminDashboardOptionsForm']);
