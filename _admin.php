<?php
# -- BEGIN LICENSE BLOCK ---------------------------------------
#
# This file is part of Dotclear 2.
#
# Copyright (c) 2012 Franck Paul
# Licensed under the GPL version 2.0 license.
# See LICENSE file or
# http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
#
# -- END LICENSE BLOCK -----------------------------------------
if (!defined('DC_CONTEXT_ADMIN')) { return; }

// Dashboard behaviours
$core->addBehavior('adminDashboardItems',array('dmHostingMonitorBehaviors','adminDashboardItems'));
$core->addBehavior('adminDashboardContents',array('dmHostingMonitorBehaviors','adminDashboardContents'));

// User-preferecences behaviours
$core->addBehavior('adminBeforeUserUpdate',array('dmHostingMonitorBehaviors','adminBeforeUserUpdate'));
$core->addBehavior('adminPreferencesForm',array('dmHostingMonitorBehaviors','adminPreferencesForm'));

# BEHAVIORS
class dmHostingMonitorBehaviors
{
	static function getDbSize($core)
	{
		// Get current db size in Kb
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
				$sql = 'SHOW TABLE STATUS';
				$rs = $core->con->select($sql);
				while ($rs->fetch()) {
					$dbSize += $rs->Data_length + $rs->Index_length;
				}
				break;
		}
		return $dbSize / 1024;
	}
	
	static function getUsedSpace($core)
	{
		// Get current space used by the installation in Kb
		// Take care about potential clean-install :
		// Get size of Dotclear install
		// + Size of outside plugins directories
		// + Size of outside cache directory
		// + Size of (public + themes directories for each blog)
		// Beware of aliases ?

		$hdUsed = 0;
		if (!function_exists('shell_exec')) return $hdUsed;

		// du -k -s .. executed in the admin directory gives the Dotclear install
		// Runs only on unix-like systems (Mac OS X, Unix, Linux)
		$hdUsed = substr(shell_exec('du -k -s ..'),0,-3);

		return $hdUsed;
	}
	
	static function getFreeSpace($core)
	{
		// Get current free space on Hard Disk in Kb
		
		$hdFree = 0;
		if (!function_exists('disk_free_space')) return $hdFree;
			
		$hdFree = disk_free_space(".") / 1024;
		return $hdFree;
	}

	static function getTotalSpace($core)
	{
		// Get current total space on Hard Disk in Kb
		
		$hdTotal = 0;
		if (!function_exists('disk_total_space')) return $hdFree;
			
		$hdTotal = disk_total_space(".") / 1024;
		return $hdTotal;
	}
	
	static function getInfos($core)
	{
		$ret = '<div id="hosting-monitor">'.'<h3>'.'<img src="index.php?pf=dmHostingMonitor/icon.png" alt="" />'.' '.__('Hosting Monitor').'</h3>';
		$ret .= '<p>'.__('Database size:').' '.sprintf('%.2f',dmHostingMonitorBehaviors::getDbSize($core)/1024).' '.__('Mb').'</p>';
		$ret .= '<p>'.__('Hard-disk used:').' '.sprintf('%.2f',dmHostingMonitorBehaviors::getUsedSpace($core)/1024).' '.__('Mb').'</p>';
		$ret .= '<p>'.__('Hard-disk free:').' '.sprintf('%.2f',dmHostingMonitorBehaviors::getFreeSpace($core)/1024).' '.__('Mb').'</p>';
		$ret .= '<p>'.__('Hard-disk total:').' '.sprintf('%.2f',dmHostingMonitorBehaviors::getTotalSpace($core)/1024).' '.__('Mb').'</p>';
		$ret .= '</div>';

		return $ret;
	}
	
	public static function adminDashboardItems($core,$items)
	{
		// Add small module to the items stack
		$core->auth->user_prefs->addWorkspace('dmhostingmonitor');
		if ($core->auth->user_prefs->dmhostingmonitor->activated && !$core->auth->user_prefs->dmhostingmonitor->large) {
			$items[] = new ArrayObject(array(dmHostingMonitorBehaviors::getInfos($core)));
		}
	}

	public static function adminDashboardContents($core,$contents)
	{
		// Add large modules to the contents stack
		$core->auth->user_prefs->addWorkspace('dmhostingmonitor');
		if ($core->auth->user_prefs->dmhostingmonitor->activated && $core->auth->user_prefs->dmhostingmonitor->large) {
			$contents[] = new ArrayObject(array(dmHostingMonitorBehaviors::getInfos($core)));
		}
	}

	public static function adminBeforeUserUpdate($cur,$userID)
	{
		global $core;

		// Get and store user's prefs for plugin options
		$core->auth->user_prefs->addWorkspace('dmhostingmonitor');
		try {
			// Hosting monitor options
			$core->auth->user_prefs->dmhostingmonitor->put('activated',!empty($_POST['activated']),'boolean');
			$core->auth->user_prefs->dmhostingmonitor->put('large',!empty($_POST['large']),'boolean');
			$core->auth->user_prefs->dmhostingmonitor->put('show_hd_info',!empty($_POST['show_hd_info']),'boolean');
			$core->auth->user_prefs->dmhostingmonitor->put('max_hd_size',(integer)$_POST['max_hd_size'],'integer');
			$core->auth->user_prefs->dmhostingmonitor->put('show_db_info',!empty($_POST['show_db_info']),'boolean');
			$core->auth->user_prefs->dmhostingmonitor->put('max_db_size',(integer)$_POST['max_db_size'],'integer');
		} 
		catch (Exception $e)
		{
			$core->error->add($e->getMessage());
		}
	}
	
	public static function adminPreferencesForm($core)
	{
		// Add fieldset for plugin options
		$core->auth->user_prefs->addWorkspace('dmhostingmonitor');

		echo '<div class="col">';

		echo '<fieldset><legend>'.__('Hosting monitor on dashboard').'</legend>'.
		
		'<p><label for"activated" class="classic">'.
		form::checkbox('activated',1,$core->auth->user_prefs->dmhostingmonitor->activated).' '.
		__('Activate module').'</label></p>'.

		'<p><label for"large" class="classic">'.
		form::checkbox('large',1,$core->auth->user_prefs->dmhostingmonitor->large).' '.
		__('Display hosting monitor module in large section (under favorites)').'</label></p>'.

		'<p><label for"show_hd_info" class="classic">'.
		form::checkbox('show_hd_info',1,$core->auth->user_prefs->dmhostingmonitor->show_hd_info).' '.
		__('Show hard-disk information').'</label></p>'.

		'<p><label for"max_hd_size">'.__('Allocated hard-disk size (in Mb, leave empty for unlimited):').
		form::field('max_hd_size',7,10,(integer) $core->auth->user_prefs->dmhostingmonitor->max_hd_size).
		'</label></p>'.

		'<p><label for"show_db_info" class="classic">'.
		form::checkbox('show_db_info',1,$core->auth->user_prefs->dmhostingmonitor->show_db_info).' '.
		__('Show database information').'</label></p>'.

		'<p><label for"max_db_size">'.__('Allocated database size (in Mb, leave empty for unlimited):').
		form::field('max_db_size',7,10,(integer) $core->auth->user_prefs->dmhostingmonitor->max_db_size).
		'</label></p>'.

		'<br class="clear" />'. //Opera sucks
		'</fieldset>';

		echo '</div>';
	}
}
?>