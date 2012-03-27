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

if (!defined('DC_CONTEXT_ADMIN')){return;}

$new_version = $core->plugins->moduleInfo('dmHostingMonitor','version');
$old_version = $core->getVersion('dmHostingMonitor');

if (version_compare($old_version,$new_version,'>=')) return;

try
{
	$core->auth->user_prefs->addWorkspace('dmhostingmonitor');

	// Default prefs for last comments
	$core->auth->user_prefs->dmhostingmonitor->put('activated',false,'boolean','Activate Hosting Monitor',false,true);
	$core->auth->user_prefs->dmhostingmonitor->put('large',true,'boolean','Show module in large section',false,true);
	$core->auth->user_prefs->dmhostingmonitor->put('show_hd_info',true,'boolean','Show hard-disk information',false,true);
	$core->auth->user_prefs->dmhostingmonitor->put('max_hd_size',0,'integer','Size of allocated hard-disk (in Mb)',false,true);
	$core->auth->user_prefs->dmhostingmonitor->put('show_db_info',true,'boolean','Show database information',false,true);
	$core->auth->user_prefs->dmhostingmonitor->put('max_db_size',0,'integer','Size of allocated database file (in Mb)',false,true);

	$core->setVersion('dmHostingMonitor',$new_version);
	
	return true;
}
catch (Exception $e)
{
	$core->error->add($e->getMessage());
}
return false;

?>