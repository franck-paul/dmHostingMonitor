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

use ArrayObject;
use dcCore;
use dcPage;
use dcSettings;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Fieldset;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Legend;
use Dotclear\Helper\Html\Form\Number;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Text;
use Exception;

class BackendBehaviors
{
    private static function readableSize($size)
    {
        switch (true) {
            case ($size > 1_000_000_000_000):
                $size /= 1_000_000_000_000;
                $suffix = __('TB');

                break;
            case ($size > 1_000_000_000):
                $size /= 1_000_000_000;
                $suffix = __('GB');

                break;
            case ($size > 1_000_000):
                $size /= 1_000_000;
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
                $rs  = new MetaRecord(dcCore::app()->con->select($sql));
                while ($rs->fetch()) {
                    $dbSize += $rs->size;
                }

                break;
            case 'mysql':
                $sql = 'SHOW TABLE STATUS';
                $rs  = new MetaRecord(dcCore::app()->con->select($sql));
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
        $stack   = [...$stack, ...$plugins];

        // Cache
        $stack[] = DC_TPL_CACHE;

        // For each blog : public and theme folder
        // If not absolute (1st char <> /) then prefix with ../
        $rs = dcCore::app()->getBlogs();
        while ($rs->fetch()) {
            $settings   = new dcSettings($rs->blog_id);
            $publicPath = $settings->system->public_path;   // @phpstan-ignore-line
            $themesPath = $settings->system->themes_path;   // @phpstan-ignore-line
            $stack[]    = (substr($publicPath, 0, 1) == '/' ? $publicPath : '../' . $publicPath);
            $stack[]    = (substr($themesPath, 0, 1) == '/' ? $themesPath : '../' . $themesPath);
        }

        // Stack of real directories
        $dir = [];
        foreach ($stack as $path) {
            // Get real path
            $realPath = Path::real($path);
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
        $settings = dcCore::app()->auth->user_prefs->get(My::id());

        $dbSize       = 0;
        $dbMaxSize    = 0;
        $dbMaxPercent = 0;
        $hdTotal      = 0;
        $hdFree       = 0;
        $hdPercent    = 0;
        $hdUsed       = 0;
        $hdMaxSize    = 0;
        $hdMaxPercent = 0;

        $first_threshold  = (int) $settings->first_threshold;
        $second_threshold = (int) $settings->second_threshold;

        $bargraph = $settings->show_gauges ? false : true;
        $large    = $settings->large;

        if ($settings->show_hd_info) {
            $hdTotal   = self::getTotalSpace();
            $hdFree    = self::getFreeSpace();
            $hdPercent = self::getPercentageOf($hdFree, $hdTotal);

            $hdUsed    = self::getUsedSpace();
            $hdMaxSize = $settings->max_hd_size;
            if ($hdMaxSize == 0) {
                // Use total size of hard-disk
                $hdMaxSize = $hdTotal;
            } else {
                $hdMaxSize *= 1000 * 1000;
            }
            $hdMaxPercent = self::getPercentageOf($hdUsed, $hdMaxSize);
        }

        if ($settings->show_db_info) {
            $dbSize    = self::getDbSize();
            $dbMaxSize = $settings->max_db_size;
            $dbMaxSize *= 1000 * 1000;
            $dbMaxPercent = self::getPercentageOf($dbSize, $dbMaxSize);
        }

        $ret = '<div id="hosting-monitor" class="box ' . ($large ? 'medium' : 'small dm_hm_short_info') . '">' .
        '<h3>' . '<img src="' . urldecode(dcPage::getPF('dmHostingMonitor/icon.png')) . '" alt="" />' . ' ' . __('Hosting Monitor') . '</h3>';
        $legend = [];

        $bar  = '';
        $pie  = '';
        $json = [];

        if ($settings->show_hd_info) {
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

        if ($settings->show_db_info) {
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
                        urldecode(dcPage::getPF(My::id() . '/js/admin.js')),
                        dcCore::app()->getVersion(My::id())
                    );
        }

        return $ret;
    }

    public static function adminDashboardContents($contents)
    {
        $settings = dcCore::app()->auth->user_prefs->get(My::id());

        // Add module to the contents stack
        if ($settings->activated) {
            if ($settings->show_hd_info || $settings->show_db_info) {
                $contents[] = new ArrayObject([self::getInfos()]);
            }
        }
    }

    public static function adminDashboardHeaders()
    {
        $settings = dcCore::app()->auth->user_prefs->get(My::id());

        if ($settings->activated) {
            $ret = '';

            if ($settings->show_hd_info || $settings->show_db_info) {
                $ret .= dcPage::cssLoad(
                    urldecode(dcPage::getPF(My::id() . '/css/style.css')),
                    'screen',
                    dcCore::app()->getVersion(My::id())
                ) . "\n" .
                dcPage::jsLoad(
                    urldecode(dcPage::getPF(My::id() . '/js/raphael.js')),
                    dcCore::app()->getVersion(My::id())
                ) . "\n" .
                dcPage::jsLoad(
                    urldecode(dcPage::getPF(My::id() . '/js/justgage.js')),
                    dcCore::app()->getVersion(My::id())
                ) . "\n";
            }

            return $ret;
        }
    }

    public static function adminPageHTMLHead()
    {
        $settings = dcCore::app()->auth->user_prefs->get(My::id());

        if ($settings->activated && $settings->ping) {
            echo
                dcPage::jsJson('dm_hostingmonitor', [
                    'dmHostingMonitor_Ping'    => $settings->ping,
                    'dmHostingMonitor_Offline' => __('Server offline'),
                    'dmHostingMonitor_Online'  => __('Server online'),
                ]) .
                dcPage::jsLoad(
                    urldecode(dcPage::getPF(My::id() . '/js/service.js')),
                    dcCore::app()->getVersion(My::id())
                ) . "\n";
        }
    }

    public static function adminAfterDashboardOptionsUpdate()
    {
        $settings = dcCore::app()->auth->user_prefs->get(My::id());

        // Get and store user's prefs for plugin options
        try {
            // Hosting monitor options
            $settings->put('activated', !empty($_POST['activated']), 'boolean');
            $settings->put('show_hd_info', !empty($_POST['show_hd_info']), 'boolean');
            $settings->put('max_hd_size', (int) $_POST['max_hd_size'], 'integer');
            $settings->put('show_db_info', !empty($_POST['show_db_info']), 'boolean');
            $settings->put('max_db_size', (int) $_POST['max_db_size'], 'integer');
            $settings->put('first_threshold', (int) $_POST['first_threshold'], 'integer');
            $settings->put('second_threshold', (int) $_POST['second_threshold'], 'integer');
            $settings->put('large', empty($_POST['small']), 'boolean');
            $settings->put('show_gauges', !empty($_POST['show_gauges']), 'boolean');
            $settings->put('ping', !empty($_POST['ping']), 'boolean');
        } catch (Exception $e) {
            dcCore::app()->error->add($e->getMessage());
        }
    }

    public static function adminDashboardOptionsForm()
    {
        $settings = dcCore::app()->auth->user_prefs->get(My::id());

        // Add fieldset for plugin options
        echo
        (new Fieldset('dmhostingmonitor'))
        ->legend((new Legend(__('Hosting monitor on dashboard'))))
        ->fields([
            (new Para())->items([
                (new Checkbox('activated', $settings->activated))
                    ->value(1)
                    ->label((new Label(__('Activate module'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Text(null, '<hr />')),
            (new Para())->items([
                (new Checkbox('show_hd_info', $settings->show_hd_info))
                    ->value(1)
                    ->label((new Label(__('Show hard-disk information'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Number('max_hd_size', 1, 9_999_999, $settings->max_hd_size))
                    ->label((new Label(__('Allocated hard-disk size (in Mb, leave empty for unlimited):'), Label::INSIDE_TEXT_BEFORE))),
            ]),
            (new Text(null, '<hr />')),
            (new Para())->items([
                (new Checkbox('show_db_info', $settings->show_db_info))
                    ->value(1)
                    ->label((new Label(__('Show database information'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Number('max_db_size', 1, 9_999_999, $settings->max_db_size))
                    ->label((new Label(__('Allocated database size (in Mb, leave empty for unlimited):'), Label::INSIDE_TEXT_BEFORE))),
            ]),
            (new Para())->items([
                (new Number('first_threshold', 1, 9_999_999, $settings->first_threshold))
                    ->label((new Label(__('1st threshold (in %, leave empty to ignore):'), Label::INSIDE_TEXT_BEFORE))),
            ]),
            (new Para())->items([
                (new Number('second_threshold', 1, 9_999_999, $settings->second_threshold))
                    ->label((new Label(__('2nd threshold (in %, leave empty to ignore):'), Label::INSIDE_TEXT_BEFORE))),
            ]),
            (new Text(null, '<hr />')),
            (new Para())->items([
                (new Checkbox('small', $settings->large))
                    ->value(1)
                    ->label((new Label(__('Small screen'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Checkbox('show_gauges', $settings->show_gauges))
                    ->value(1)
                    ->label((new Label(__('Show gauges instead of bar graph'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Text(null, '<hr />')),
            (new Para())->items([
                (new Checkbox('ping', $settings->ping))
                    ->value(1)
                    ->label((new Label(__('Check server status'), Label::INSIDE_TEXT_AFTER))),
            ]),
        ])
        ->render();
    }
}
