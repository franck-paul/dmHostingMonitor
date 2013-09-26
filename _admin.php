<?php
# -- BEGIN LICENSE BLOCK ----------------------------------
# This file is part of dmHostingMonitor, a plugin for Dotclear 2.
#
# Copyright (c) Franck Paul and contributors
# carnet.franck.paul@gmail.com
#
# Licensed under the GPL version 2.0 license.
# A copy of this license is available in LICENSE file or at
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
# -- END LICENSE BLOCK ------------------------------------

if (!defined('DC_CONTEXT_ADMIN')) { return; }

// dead but useful code, in order to have translations
__('Hosting Monitor Dashboard Module').__('Display server information on dashboard');

// Dashboard behaviours
$core->addBehavior('adminPageHTMLHead',array('dmHostingMonitorBehaviors','adminPageHTMLHead'));
$core->addBehavior('adminDashboardContents',array('dmHostingMonitorBehaviors','adminDashboardContents'));

$core->addBehavior('adminAfterDashboardOptionsUpdate',array('dmHostingMonitorBehaviors','adminAfterDashboardOptionsUpdate'));
$core->addBehavior('adminDashboardOptionsForm',array('dmHostingMonitorBehaviors','adminDashboardOptionsForm'));

# BEHAVIORS
class dmHostingMonitorBehaviors
{
	static function readableSize($size)
    {
        switch (true)
        {
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
        return round($size, 2).' '.$suffix;
    }

	static function getDbSize($core)
	{
		// Get current db size in bytes
		$dbSize = 0;
		switch ($core->con->driver())
		{
			case 'sqlite':
				break;
			case 'pgsql':
				$sql = 'SELECT pg_database_size(\''.$core->con->database().'\') AS size';
				$rs = $core->con->select($sql);
				while ($rs->fetch()) {
					$dbSize += $rs->size;
				}
				break;
			case 'mysql':
			case 'mysqli':
				$sql = 'SHOW TABLE STATUS';
				$rs = $core->con->select($sql);
				while ($rs->fetch()) {
					$dbSize += $rs->Data_length + $rs->Index_length;
				}
				break;
		}
		return $dbSize;
	}

	static function getUsedSpace($core)
	{
		// Get current space used by the installation in bytes
		// Take care about potential clean-install :
		// Get size of Dotclear install
		// + Size of outside plugins directories
		// + Size of outside cache directory
		// + Size of (public + themes directories for each blog)
		// Beware of aliases ?

		$hdUsed = 0;
		if (!function_exists('shell_exec')) return $hdUsed;

		// Stack of paths
		$stack = array();

		// Dotclear installation
		$stack[] = '..';

		// Plugins
		$plugins = explode(PATH_SEPARATOR,DC_PLUGINS_ROOT);
		$stack = array_merge($stack,$plugins);

		// Cache
		$stack[] = DC_TPL_CACHE;

		// For each blog : public and theme folder
		// If not absolute (1st char <> /) then prefix with ../
		$rs = $core->getBlogs();
		while ($rs->fetch()) {
			$settings = new dcSettings($core,$rs->blog_id);
			$settings->addNamespace('system');
			$publicPath = $settings->system->public_path;
			$themesPath = $settings->system->themes_path;
			$stack[] = (substr($publicPath,0,1) == '/' ? $publicPath : '../'.$publicPath);
			$stack[] = (substr($themesPath,0,1) == '/' ? $themesPath : '../'.$themesPath);
		}

/* Trace (add a / to the /* at the beginning of this line to uncomment the following code)
		echo '<h3>Paths</h3>';
		foreach ($stack as $folder) {
			echo '<p>'.$folder.'</p>';
		}
//*/

		// Stack of real directories
		$dir = array();
		foreach ($stack as $path) {
			// Get real path
			$realPath = path::real($path);
			if (!$realPath) {
				continue;
			}
			// Check if not already counted
			$index = 0;
			foreach ($dir as $folder) {
				if (substr($realPath,0,strlen($folder)) == $folder) {
					// Parent folder found in stack : ignore it
					$realPath = '';
					break;
				} elseif (substr($folder,0,strlen($realPath)) == $realPath) {
					// Child folder found in stack : replace it by parent
					$dir[$index] = $realPath;
					$realPath = '';
					break;
				}
				$index++;
			}
			if ($realPath != '') {
				$dir[] = $realPath;
				sort($dir);
			}
		}

/* Trace (add a / to the /* at the beginning of this line to uncomment the following code)
		echo '<h3>Folders</h3>';
		foreach ($dir as $folder) {
			echo '<p>'.$folder.'</p>';
		}
//*/

		// Command : du -k -s <path>
		// Runs only on unix-like systems (Mac OS X, Unix, Linux)
		foreach ($dir as $folder) {
			if ($folder != '') {
				$hdUsed += substr(shell_exec('du -k -s -L '.$folder),0,-3);
			}
		}
		$hdUsed *= 1024;

		return $hdUsed;
	}

	static function getFreeSpace($core)
	{
		// Get current free space on Hard Disk in bytes

		$hdFree = 0;
		if (!function_exists('disk_free_space')) return $hdFree;

		$hdFree = (float)@disk_free_space(".");
		return $hdFree;
	}

	static function getTotalSpace($core)
	{
		// Get current total space on Hard Disk in bytes

		$hdTotal = 0;
		if (!function_exists('disk_total_space')) return $hdTotal;

		$hdTotal = (float)@disk_total_space(".");
		return $hdTotal;
	}

	static function getPercentageOf($part,$total)
	{
		$percentage = -1;
		if (($part > 0) && ($total > 0)) {
			$percentage = round($part / $total, 2) * 100;
		}
		return $percentage;
	}

	static function getLevelClass($value,$firstLevel,$secondLevel)
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
			$temp = $firstLevel;
			$firstLevel = $secondLevel;
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

	static function getInfos($core)
	{
		$core->auth->user_prefs->addWorkspace('dmhostingmonitor');

		$first_threshold = (integer)$core->auth->user_prefs->dmhostingmonitor->first_threshold;
		$second_threshold = (integer)$core->auth->user_prefs->dmhostingmonitor->second_threshold;

		$large = $core->auth->user_prefs->dmhostingmonitor->large;

		if ($core->auth->user_prefs->dmhostingmonitor->show_hd_info) {
			$hdTotal = dmHostingMonitorBehaviors::getTotalSpace($core);
			$hdFree = dmHostingMonitorBehaviors::getFreeSpace($core);
			$hdPercent = dmHostingMonitorBehaviors::getPercentageOf($hdFree,$hdTotal);

			$hdUsed = dmHostingMonitorBehaviors::getUsedSpace($core);
			$hdMaxSize = $core->auth->user_prefs->dmhostingmonitor->max_hd_size;
			if ($hdMaxSize == 0) {
				// Use total size of hard-disk
				$hdMaxSize = $hdTotal;
			} else {
				$hdMaxSize *= 1000 * 1000;
			}
			$hdMaxPercent = dmHostingMonitorBehaviors::getPercentageOf($hdUsed,$hdMaxSize);
		}

		if ($core->auth->user_prefs->dmhostingmonitor->show_db_info)
		{
			$dbSize = dmHostingMonitorBehaviors::getDbSize($core);
			$dbMaxSize = $core->auth->user_prefs->dmhostingmonitor->max_db_size;
			$dbMaxSize *= 1000 * 1000;
			$dbMaxPercent = dmHostingMonitorBehaviors::getPercentageOf($dbSize,$dbMaxSize);
		}

		$ret = '<div id="hosting-monitor" class="box '.($large ? 'medium' : 'small dm_hm_short_info').'"">'.
			'<h3>'.'<img src="index.php?pf=dmHostingMonitor/icon.png" alt="" />'.' '.__('Hosting Monitor').'</h3>';
		$legend = array();

		if ($core->auth->user_prefs->dmhostingmonitor->show_hd_info) {
			/* Hard-disk free vs total information */
			if (($hdTotal > 0) && ($hdPercent >= 0)) {
				$ret .= '<div class="graphe" title="'.__('Hard-disk free').'">'.
					'<strong class="barre '.dmHostingMonitorBehaviors::getLevelClass(100 - $hdPercent,$first_threshold,$second_threshold).
					'" style="width: '.min($hdPercent,100).'%;">'.$hdPercent.'%</strong></div>';
				if ($large) {
					$ret .= '<p class="graphe text">'.__('Hard-disk free:').' '.dmHostingMonitorBehaviors::readableSize($hdFree);
					if ($hdPercent > 0) {
						$ret .= ' ('.$hdPercent.'% '.__('of').' '.dmHostingMonitorBehaviors::readableSize($hdTotal).')';
					} else {
						$ret .= ' - '.__('Hard-disk total:').' '.dmHostingMonitorBehaviors::readableSize($hdTotal);
					}
					$ret .= '</p>';
				} else {
					$legend[] = __('HD Free');
				}
			}
			/* Dotclear used vs allocated space information */
			if (($hdMaxSize > 0) && ($hdMaxPercent >= 0)) {
				$ret .= '<div class="graphe" title="'.__('Hard-disk used').'">'.
					'<strong class="barre '.dmHostingMonitorBehaviors::getLevelClass($hdMaxPercent,$first_threshold,$second_threshold).
					'" style="width: '.min($hdMaxPercent,100).'%;">'.$hdMaxPercent.'%</strong></div>';
				if ($large) {
					$ret .= '<p class="graphe text">'.__('Hard-disk used:').' '.dmHostingMonitorBehaviors::readableSize($hdUsed);
					if ($hdMaxSize > 0) {
						if ($hdMaxPercent > 0) {
							$ret .= ' ('.$hdMaxPercent.'% '.__('of').' '.dmHostingMonitorBehaviors::readableSize($hdMaxSize).')';
						} else {
							if ($hdMaxSize != $hdTotal) {
								$ret .= ' - '.__('Hard-disk limit:').' '.dmHostingMonitorBehaviors::readableSize($hdMaxSize);
							}
						}
					}
					$ret .= '</p>';
				} else {
					$legend[] = __('HD Used');
				}
			}
		}

		if ($core->auth->user_prefs->dmhostingmonitor->show_db_info)
		{
			/* Database information */
			if (($dbMaxSize > 0) && ($dbMaxPercent >= 0)) {
				$ret .= '<div class="graphe" title="'.__('Database size').'">'.
					'<strong class="barre '.dmHostingMonitorBehaviors::getLevelClass($dbMaxPercent,$first_threshold,$second_threshold).
					'" style="width: '.min($dbMaxPercent,100).'%;">'.$dbMaxPercent.'%</strong></div>';
				if ($large) {
					$ret .= '<p class="graphe text">'.__('Database size:').' '.dmHostingMonitorBehaviors::readableSize($dbSize);
					if ($dbMaxSize > 0) {
						if ($dbMaxPercent > 0) {
							$ret .= ' ('.$dbMaxPercent.'% '.__('of').' '.dmHostingMonitorBehaviors::readableSize($dbMaxSize).')';
						} else {
							$ret .= ' - '.__('Database limit:').' '.dmHostingMonitorBehaviors::readableSize($dbMaxSize);
						}
					}
					$ret .= '</p>';
				} else {
					$legend[] = __('DB Size');
				}
			}
		}

		if (count($legend)) {
			$ret .= '<p class="graphe-legend">'.implode("; ", $legend).'</p>';
		}
		$ret .= '</div>';

		return $ret;
	}

	public static function adminDashboardContents($core,$contents)
	{
		// Add module to the contents stack
		$core->auth->user_prefs->addWorkspace('dmhostingmonitor');
		if ($core->auth->user_prefs->dmhostingmonitor->activated) {
			$contents[] = new ArrayObject(array(dmHostingMonitorBehaviors::getInfos($core)));
		}
	}

	public static function adminPageHTMLHead()
	{
		echo '<link rel="stylesheet" href="index.php?pf=dmHostingMonitor/style.css" type="text/css" media="screen" />'."\n";
	}

	public static function adminAfterDashboardOptionsUpdate($userID)
	{
		global $core;

		// Get and store user's prefs for plugin options
		$core->auth->user_prefs->addWorkspace('dmhostingmonitor');
		try {
			// Hosting monitor options
			$core->auth->user_prefs->dmhostingmonitor->put('activated',!empty($_POST['activated']),'boolean');
			$core->auth->user_prefs->dmhostingmonitor->put('show_hd_info',!empty($_POST['show_hd_info']),'boolean');
			$core->auth->user_prefs->dmhostingmonitor->put('max_hd_size',(integer)$_POST['max_hd_size'],'integer');
			$core->auth->user_prefs->dmhostingmonitor->put('show_db_info',!empty($_POST['show_db_info']),'boolean');
			$core->auth->user_prefs->dmhostingmonitor->put('max_db_size',(integer)$_POST['max_db_size'],'integer');
			$core->auth->user_prefs->dmhostingmonitor->put('first_threshold',(integer)$_POST['first_threshold'],'integer');
			$core->auth->user_prefs->dmhostingmonitor->put('second_threshold',(integer)$_POST['second_threshold'],'integer');
			$core->auth->user_prefs->dmhostingmonitor->put('large',empty($_POST['small']),'boolean');
		}
		catch (Exception $e)
		{
			$core->error->add($e->getMessage());
		}
	}

	public static function adminDashboardOptionsForm($core)
	{
		// Add fieldset for plugin options
		$core->auth->user_prefs->addWorkspace('dmhostingmonitor');

		echo '<div class="fieldset"><h4>'.__('Hosting monitor on dashboard').'</h4>'.

		'<p>'.
		form::checkbox('activated',1,$core->auth->user_prefs->dmhostingmonitor->activated).' '.
		'<label for="activated" class="classic">'.__('Activate module').'</label></p>'.

		'<p>'.
		form::checkbox('show_hd_info',1,$core->auth->user_prefs->dmhostingmonitor->show_hd_info).' '.
		'<label for="show_hd_info" class="classic">'.__('Show hard-disk information').'</label></p>'.

		'<p><label for="max_hd_size" class="classic">'.__('Allocated hard-disk size (in Mb, leave empty for unlimited):').'</label> '.
		form::field('max_hd_size',7,10,(integer) $core->auth->user_prefs->dmhostingmonitor->max_hd_size).
		'</p>'.

		'<p>'.
		form::checkbox('show_db_info',1,$core->auth->user_prefs->dmhostingmonitor->show_db_info).' '.
		'<label for="show_db_info" class="classic">'.__('Show database information').'</label></p>'.

		'<p><label for="max_db_size" class="classic">'.__('Allocated database size (in Mb, leave empty for unlimited):').'</label> '.
		form::field('max_db_size',7,10,(integer) $core->auth->user_prefs->dmhostingmonitor->max_db_size).
		'</p>'.

		'<p><label for="first_threshold" class="classic">'.__('1st threshold (in %, leave empty to ignore):').'</label> '.
		form::field('first_threshold',2,3,(integer) $core->auth->user_prefs->dmhostingmonitor->first_threshold).
		'</p>'.

		'<p><label for="second_threshold" class="classic">'.__('2nd threshold (in %, leave empty to ignore):').'</label> '.
		form::field('second_threshold',2,3,(integer) $core->auth->user_prefs->dmhostingmonitor->second_threshold).
		'</p>'.

		'<p>'.
		form::checkbox('small',1,!$core->auth->user_prefs->dmhostingmonitor->large).' '.
		'<label for="small" class="classic">'.__('Small screen').'</label></p>'.

		'</div>';
	}
}
