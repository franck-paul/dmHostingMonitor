<?php

/**
 * @brief dmHostingMonitor, a plugin for Dotclear 2
 *
 * @package Dotclear
 * @subpackage Plugins
 *
 * @author Franck Paul and contributors
 *
 * @copyright Franck Paul contact@open-time.net
 * @copyright GPL-2.0 https://www.gnu.org/licenses/gpl-2.0.html
 */
declare(strict_types=1);

namespace Dotclear\Plugin\dmHostingMonitor;

use ArrayObject;
use Dotclear\App;
use Dotclear\Database\MetaRecord;
use Dotclear\Helper\File\Path;
use Dotclear\Helper\Html\Form\Checkbox;
use Dotclear\Helper\Html\Form\Div;
use Dotclear\Helper\Html\Form\Fieldset;
use Dotclear\Helper\Html\Form\Img;
use Dotclear\Helper\Html\Form\Label;
use Dotclear\Helper\Html\Form\Legend;
use Dotclear\Helper\Html\Form\None;
use Dotclear\Helper\Html\Form\Note;
use Dotclear\Helper\Html\Form\Number;
use Dotclear\Helper\Html\Form\Para;
use Dotclear\Helper\Html\Form\Set;
use Dotclear\Helper\Html\Form\Strong;
use Dotclear\Helper\Html\Form\Text;
use Exception;

/**
 * @todo switch to SqlStatement
 */
class BackendBehaviors
{
    private static function readableSize(int|float $size): string
    {
        switch (true) {
            case $size > 1_000_000_000_000:
                $size /= 1_000_000_000_000;
                $suffix = __('TB');

                break;
            case $size > 1_000_000_000:
                $size /= 1_000_000_000;
                $suffix = __('GB');

                break;
            case $size > 1_000_000:
                $size /= 1_000_000;
                $suffix = __('MB');

                break;
            case $size > 1000:
                $size /= 1000;
                $suffix = __('KB');

                break;
            default:
                $suffix = __('B');
        }

        return round($size, 2) . ' ' . $suffix;
    }

    private static function getDbSize(): float
    {
        // Get current db size in bytes
        $db_size = 0.0;
        switch (App::db()->con()->syntax()) {
            case 'sqlite':
                break;
            case 'postgresql':
                $sql = 'SELECT pg_database_size(\'' . App::db()->con()->database() . '\') AS size';
                $rs  = new MetaRecord(App::db()->con()->select($sql));
                while ($rs->fetch()) {
                    $size = is_numeric($size = $rs->size) ? (float) $rs->size : 0;
                    $db_size += $size;
                }

                break;
            case 'mysql':
                $sql = 'SHOW TABLE STATUS';
                $rs  = new MetaRecord(App::db()->con()->select($sql));
                while ($rs->fetch()) {
                    $data_length  = is_numeric($data_length = $rs->Data_length) ? (float) $data_length : 0.0;
                    $index_length = is_numeric($index_length = $rs->Index_length) ? (float) $index_length : 0.0;
                    $db_size += $data_length + $index_length;
                }

                break;
        }

        return $db_size;
    }

    private static function getUsedSpace(): float
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
        $plugins = explode(PATH_SEPARATOR, (string) App::config()->pluginsRoot());
        $stack   = [...$stack, ...$plugins];

        // Cache
        $stack[] = App::config()->cacheRoot();

        // Get current blog
        $current_blog_id = App::blog()->id();

        // For each blog : public and theme folder
        // If not absolute (1st char <> /) then prefix with ../
        $rs = App::blogs()->getBlogs();
        while ($rs->fetch()) {
            $blog_id = is_string($blog_id = $rs->blog_id) ? $blog_id : '';
            if ($blog_id !== '') {
                App::blog()->loadFromBlog($blog_id);

                $public_path = is_string($public_path = App::blog()->settings()->system->public_path) ? $public_path : '';
                if ($public_path !== '') {
                    $stack[] = (str_starts_with($public_path, '/') ? $public_path : '../' . $public_path);
                }

                $themes_path = is_string($themes_path = App::blog()->settings()->system->themes_path) ? $themes_path : '';
                if ($themes_path !== '') {
                    $stack[] = (str_starts_with($themes_path, '/') ? $themes_path : '../' . $themes_path);
                }
            }
        }

        // Back to current blog
        App::blog()->loadFromBlog($current_blog_id);

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
                if (str_starts_with($realPath, $folder)) {
                    // Parent folder found in stack : ignore it
                    $realPath = '';

                    break;
                } elseif (str_starts_with($folder, $realPath)) {
                    // Child folder found in stack : replace it by parent
                    $dir[$index] = $realPath;
                    $realPath    = '';

                    break;
                }

                ++$index;
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

        return $hdUsed * 1024;
    }

    private static function getFreeSpace(): float
    {
        // Get current free space on Hard Disk in bytes

        $hdFree = 0;
        if (!function_exists('disk_free_space')) {
            return $hdFree;
        }

        return (float) @disk_free_space('.');
    }

    private static function getTotalSpace(): float
    {
        // Get current total space on Hard Disk in bytes

        $hdTotal = 0;
        if (!function_exists('disk_total_space')) {
            return $hdTotal;
        }

        return (float) @disk_total_space('.');
    }

    private static function getPercentageOf(float $part, float $total): float
    {
        if (($part > 0) && ($total > 0)) {
            return round($part / $total, 2) * 100;
        }

        return 0;
    }

    private static function getLevelClass(float $value, float $firstLevel, float $secondLevel): string
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
        }

        if ($value < $secondLevel) {
            return 'percent_warning';
        }

        if ($value <= 100) {
            return 'percent_alert';
        }

        return 'percent_explode';
    }

    private static function getInfos(): string
    {
        // Variable data helpers
        $_Bool = fn (mixed $var): bool => (bool) $var;
        $_Int  = fn (mixed $var, int $default = 0): int => $var !== null && is_numeric($val = $var) ? (int) $val : $default;

        $preferences = My::prefs();

        $dbSize       = 0;
        $dbMaxSize    = 0;
        $dbMaxPercent = 0;
        $hdTotal      = 0;
        $hdFree       = 0;
        $hdPercent    = 0;
        $hdUsed       = 0;
        $hdMaxSize    = 0;
        $hdMaxPercent = 0;

        $first_threshold  = $_Int($preferences->first_threshold);
        $second_threshold = $_Int($preferences->second_threshold);

        $bargraph = !$_Bool($preferences->show_gauges);
        $large    = $_Bool($preferences->large);

        if ($_Bool($preferences->show_hd_info)) {
            $hdTotal   = self::getTotalSpace();
            $hdFree    = self::getFreeSpace();
            $hdPercent = self::getPercentageOf($hdFree, $hdTotal);

            $hdUsed    = self::getUsedSpace();
            $hdMaxSize = $_Int($preferences->max_hd_size);
            if ($hdMaxSize === 0) {
                // Use total size of hard-disk
                $hdMaxSize = $hdTotal;
            } else {
                $hdMaxSize *= 1000 * 1000;
            }

            $hdMaxPercent = self::getPercentageOf($hdUsed, $hdMaxSize);
        }

        if ($_Bool($preferences->show_db_info)) {
            $dbSize    = self::getDbSize();
            $dbMaxSize = $_Int($preferences->max_db_size);
            $dbMaxSize *= 1000 * 1000;
            $dbMaxPercent = self::getPercentageOf($dbSize, $dbMaxSize);
        }

        $json   = [];
        $blocks = [];
        $legend = [];

        if ($_Bool($preferences->show_hd_info)) {
            /* Hard-disk free vs total information */
            if ($hdTotal > 0) {
                if ($bargraph) {
                    $blocks[] = (new Div())
                        ->class('graphe')
                        ->title(__('Hard-disk free'))
                        ->items([
                            (new Strong($hdPercent . '%'))
                                ->class(['barre', self::getLevelClass(100 - $hdPercent, $first_threshold, $second_threshold)])
                                ->extra('style="width: ' . min($hdPercent, 100) . '%;"'),
                        ]);
                    if ($large) {
                        $blocks[] = (new Para())
                            ->class(['graphe', 'text'])
                            ->separator(' ')
                            ->items([
                                (new Text(null, __('Hard-disk free:'))),
                                (new Text(null, self::readableSize($hdFree))),
                                $hdPercent > 0 ?
                                    (new Text(null, '(' . $hdPercent . '% ' . __('of') . ' ' . self::readableSize($hdTotal) . ')')) :
                                    (new Text(null, '- ' . __('Hard-disk total:') . ' ' . self::readableSize($hdTotal))),
                            ]);
                    } else {
                        $legend[] = __('HD Free');
                    }
                } else {
                    $blocks[] = (new Div('hd-free'))
                        ->class($large ? 'pie-large' : 'pie-small')
                        ->items([
                            (new Para())
                                ->separator(' ')
                                ->items([
                                    (new Text(null, __('HD Free'))),
                                    $large ?
                                        (new Text(null, '(' . self::readableSize($hdFree) . ')')) :
                                        (new None()),
                                ]),
                        ]);

                    $json['hd_free'] = 100 - $hdPercent;
                }
            }

            /* Dotclear used vs allocated space information */
            if ($hdUsed > 0) {
                if ($bargraph) {
                    $blocks[] = (new Div())
                        ->class('graphe')
                        ->title(__('Hard-disk used'))
                        ->items([
                            (new Strong($hdMaxPercent . '%'))
                                ->class(['barre', self::getLevelClass($hdMaxPercent, $first_threshold, $second_threshold)])
                                ->extra('style="width: ' . min($hdMaxPercent, 100) . '%;"'),
                        ]);
                    if ($large) {
                        $blocks[] = (new Para())
                            ->class(['graphe', 'text'])
                            ->separator(' ')
                            ->items([
                                (new Text(null, __('Hard-disk used:'))),
                                (new Text(null, self::readableSize($hdUsed))),
                                ($hdMaxSize > 0) && ($hdMaxPercent > 0) ?
                                    (new Text(null, '(' . $hdMaxPercent . '% ' . __('of') . ' ' . self::readableSize($hdMaxSize) . ')')) :
                                    ($hdMaxSize > 0 ?
                                        (new Text(null, '- ' . __('Hard-disk limit:') . ' ' . self::readableSize($hdMaxSize))) :
                                        (new None())),
                            ]);
                    } else {
                        $legend[] = __('HD Used');
                    }
                } else {
                    $blocks[] = (new Div('hd-used'))
                        ->class($large ? 'pie-large' : 'pie-small')
                        ->items([
                            (new Para())
                                ->separator(' ')
                                ->items([
                                    (new Text(null, __('HD Used'))),
                                    $large ?
                                        (new Text(null, '(' . self::readableSize($hdUsed) . ')')) :
                                        (new None()),
                                ]),
                        ]);

                    $json['hd_used'] = $hdMaxSize > 0 ? $hdMaxPercent : 0;
                }
            }
        }

        /* Database information */
        if ($_Bool($preferences->show_db_info) && $dbSize > 0) {
            if ($bargraph) {
                $blocks[] = (new Div())
                    ->class('graphe')
                    ->title(__('Database size'))
                    ->items([
                        (new Strong($dbMaxPercent . '%'))
                            ->class(['barre', self::getLevelClass($dbMaxPercent, $first_threshold, $second_threshold)])
                            ->extra('style="width: ' . min($dbMaxPercent, 100) . '%;"'),
                    ]);
                if ($large) {
                    $blocks[] = (new Para())
                        ->class(['graphe', 'text'])
                        ->separator(' ')
                        ->items([
                            (new Text(null, __('Database size:'))),
                            (new Text(null, self::readableSize($dbSize))),
                            ($dbMaxSize > 0) && ($dbMaxPercent > 0) ?
                                (new Text(null, '(' . $dbMaxPercent . '% ' . __('of') . ' ' . self::readableSize($dbMaxSize) . ')')) :
                                ($dbMaxSize > 0 ?
                                    (new Text(null, '- ' . __('Database limit:') . ' ' . self::readableSize($dbMaxSize))) :
                                    (new None())),
                        ]);
                } else {
                    $legend[] = __('DB Size');
                }
            } else {
                $blocks[] = (new Div('db-used'))
                    ->class($large ? 'pie-large' : 'pie-small')
                    ->items([
                        (new Para())
                            ->separator(' ')
                            ->items([
                                (new Text(null, __('DB Size'))),
                                $large ?
                                    (new Text(null, '(' . self::readableSize($dbSize) . ')')) :
                                    (new None()),
                            ]),
                    ]);

                $json['db_used'] = $dbMaxSize > 0 ? $dbMaxPercent : 0;
            }
        }

        if ($legend !== []) {
            $blocks[] = (new Note())
                ->class('graphe-legend')
                ->text(implode('; ', $legend));
        }

        $script = '';
        if (!$bargraph) {
            $script = App::backend()->page()->jsJson('dm_hostingmonitor_values', $json) .
                    App::backend()->page()->jsLoad(
                        urldecode((string) App::backend()->page()->getPF(My::id() . '/js/admin.js')),
                        App::version()->getVersion(My::id())
                    );
        }

        return (new Set())
            ->items([
                (new Div('hosting-monitor'))
                    ->class(['box', $large ? 'medium' : 'small dm_hm_short_info'])
                    ->items([
                        (new Text(
                            'h3',
                            (new Img(urldecode((string) App::backend()->page()->getPF('dmHostingMonitor/icon.svg'))))
                                ->alt('')
                                ->class('icon-small')
                            ->render() . ' ' . __('Hosting Monitor')
                        )),
                        ... $blocks,
                    ]),
                (new Text(null, $script)),
            ])
        ->render();
    }

    /**
     * @param      ArrayObject<int, ArrayObject<int, string>>  $contents  The contents
     */
    public static function adminDashboardContents(ArrayObject $contents): string
    {
        $preferences = My::prefs();

        // Add module to the contents stack
        if ($preferences->activated && ($preferences->show_hd_info || $preferences->show_db_info)) {
            $contents->append(new ArrayObject([self::getInfos()]));
        }

        return '';
    }

    public static function adminDashboardHeaders(): string
    {
        $preferences = My::prefs();

        if ($preferences->activated) {
            $ret = '';

            if ($preferences->show_hd_info || $preferences->show_db_info) {
                $ret .= App::backend()->page()->cssLoad(
                    urldecode((string) App::backend()->page()->getPF(My::id() . '/css/style.css')),
                    'screen',
                    App::version()->getVersion(My::id())
                ) .
                App::backend()->page()->jsLoad(
                    urldecode((string) App::backend()->page()->getPF(My::id() . '/lib/raphael/raphael.min.js')),
                    App::version()->getVersion(My::id())
                ) .
                App::backend()->page()->jsLoad(
                    urldecode((string) App::backend()->page()->getPF(My::id() . '/lib/justgage/justgage.min.js')),
                    App::version()->getVersion(My::id())
                );
            }

            return $ret;
        }

        return '';
    }

    public static function adminPageHTMLHead(): string
    {
        $preferences = My::prefs();

        if ($preferences->activated && $preferences->ping) {
            echo
                App::backend()->page()->jsJson('dm_hostingmonitor', [
                    'ping'     => $preferences->ping,
                    'offline'  => __('Server offline'),
                    'online'   => __('Server online'),
                    'interval' => ($preferences->interval ?? 300),
                ]) .
                App::backend()->page()->jsLoad(
                    urldecode((string) App::backend()->page()->getPF(My::id() . '/js/service.js')),
                    App::version()->getVersion(My::id())
                );
        }

        return '';
    }

    public static function adminAfterDashboardOptionsUpdate(): string
    {
        // Get and store user's prefs for plugin options
        try {
            // Post data helpers
            $_Bool = fn (string $name): bool => !empty($_POST[$name]);
            $_Int  = fn (string $name, int $default = 0): int => isset($_POST[$name]) && is_numeric($val = $_POST[$name]) ? (int) $val : $default;

            $preferences = My::prefs();

            // Hosting monitor options
            $preferences->put('activated', $_Bool('dmhostingmonitor_activated'), App::userWorkspace()::WS_BOOL);
            $preferences->put('show_hd_info', $_Bool('dmhostingmonitor_show_hd_info'), App::userWorkspace()::WS_BOOL);
            $preferences->put('max_hd_size', $_Int('dmhostingmonitor_max_hd_size'), App::userWorkspace()::WS_INT);
            $preferences->put('show_db_info', $_Bool('dmhostingmonitor_show_db_info'), App::userWorkspace()::WS_BOOL);
            $preferences->put('max_db_size', $_Int('dmhostingmonitor_max_db_size'), App::userWorkspace()::WS_INT);
            $preferences->put('first_threshold', $_Int('dmhostingmonitor_first_threshold'), App::userWorkspace()::WS_INT);
            $preferences->put('second_threshold', $_Int('dmhostingmonitor_second_threshold'), App::userWorkspace()::WS_INT);
            $preferences->put('large', !$_Bool('dmhostingmonitor_small'), App::userWorkspace()::WS_BOOL);
            $preferences->put('show_gauges', $_Bool('dmhostingmonitor_show_gauges'), App::userWorkspace()::WS_BOOL);
            $preferences->put('ping', $_Bool('dmhostingmonitor_ping'), App::userWorkspace()::WS_BOOL);
            $preferences->put('interval', $_Int('dmhostingmonitor_interval'), App::userWorkspace()::WS_INT);
        } catch (Exception $e) {
            App::error()->add($e->getMessage());
        }

        return '';
    }

    public static function adminDashboardOptionsForm(): string
    {
        // Variable data helpers
        $_Bool = fn (mixed $var): bool => (bool) $var;
        $_Int  = fn (mixed $var, int $default = 0): int => $var !== null && is_numeric($val = $var) ? (int) $val : $default;

        $preferences = My::prefs();

        // Add fieldset for plugin options
        echo
        (new Fieldset('dmhostingmonitor'))
        ->legend((new Legend(__('Hosting monitor on dashboard'))))
        ->fields([
            (new Para())->items([
                (new Checkbox('dmhostingmonitor_activated', $_Bool($preferences->activated)))
                    ->value(1)
                    ->label((new Label(__('Activate module'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Text(null, '<hr>')),
            (new Para())->items([
                (new Checkbox('dmhostingmonitor_show_hd_info', $_Bool($preferences->show_hd_info)))
                    ->value(1)
                    ->label((new Label(__('Show hard-disk information'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Number('dmhostingmonitor_max_hd_size', 0, 9_999_999, $_Int($preferences->max_hd_size)))
                    ->label((new Label(__('Allocated hard-disk size (in Mb, leave empty for unlimited):'), Label::INSIDE_TEXT_BEFORE))),
            ]),
            (new Text(null, '<hr>')),
            (new Para())->items([
                (new Checkbox('dmhostingmonitor_show_db_info', $_Bool($preferences->show_db_info)))
                    ->value(1)
                    ->label((new Label(__('Show database information'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Number('dmhostingmonitor_max_db_size', 0, 9_999_999, $_Int($preferences->max_db_size)))
                    ->label((new Label(__('Allocated database size (in Mb, leave empty for unlimited):'), Label::INSIDE_TEXT_BEFORE))),
            ]),
            (new Para())->items([
                (new Number('dmhostingmonitor_first_threshold', 0, 9_999_999, $_Int($preferences->first_threshold)))
                    ->label((new Label(__('1st threshold (in %, leave empty to ignore):'), Label::INSIDE_TEXT_BEFORE))),
            ]),
            (new Para())->items([
                (new Number('dmhostingmonitor_second_threshold', 0, 9_999_999, $_Int($preferences->second_threshold)))
                    ->label((new Label(__('2nd threshold (in %, leave empty to ignore):'), Label::INSIDE_TEXT_BEFORE))),
            ]),
            (new Text(null, '<hr>')),
            (new Para())->items([
                (new Checkbox('dmhostingmonitor_small', !$_Bool($preferences->large)))
                    ->value(1)
                    ->label((new Label(__('Small screen'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Checkbox('dmhostingmonitor_show_gauges', $_Bool($preferences->show_gauges)))
                    ->value(1)
                    ->label((new Label(__('Show gauges instead of bar graph'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Text(null, '<hr>')),
            (new Para())->items([
                (new Checkbox('dmhostingmonitor_ping', $_Bool($preferences->ping)))
                    ->value(1)
                    ->label((new Label(__('Check server status'), Label::INSIDE_TEXT_AFTER))),
            ]),
            (new Para())->items([
                (new Number('dmhostingmonitor_interval', 0, 9_999_999, $_Int($preferences->interval)))
                    ->label((new Label(__('Interval in seconds between two pings:'), Label::INSIDE_TEXT_BEFORE))),
            ]),
        ])
        ->render();

        return '';
    }
}
