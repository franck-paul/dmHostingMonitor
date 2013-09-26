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

$new_version = $core->plugins->moduleInfo('dmHostingMonitor','version');
$old_version = $core->getVersion('dmHostingMonitor');

if (version_compare($old_version,$new_version,'>=')) return;

try
{
	$core->auth->user_prefs->addWorkspace('dmhostingmonitor');

	// Default prefs for last comments
	$core->auth->user_prefs->dmhostingmonitor->put('activated',false,'boolean','Activate Hosting Monitor',false,true);
	$core->auth->user_prefs->dmhostingmonitor->put('show_hd_info',true,'boolean','Show hard-disk information',false,true);
	$core->auth->user_prefs->dmhostingmonitor->put('max_hd_size',0,'integer','Size of allocated hard-disk (in Mb)',false,true);
	$core->auth->user_prefs->dmhostingmonitor->put('show_db_info',true,'boolean','Show database information',false,true);
	$core->auth->user_prefs->dmhostingmonitor->put('max_db_size',0,'integer','Size of allocated database file (in Mb)',false,true);
	$core->auth->user_prefs->dmhostingmonitor->put('first_threshold',80,'integer','1st alert threshold (in %)',false,true);
	$core->auth->user_prefs->dmhostingmonitor->put('second_threshold',90,'integer','2nd alert threshold (in %)',false,true);
	$core->auth->user_prefs->dmhostingmonitor->put('large',true,'boolean','Large display',false,true);

	$core->setVersion('dmHostingMonitor',$new_version);

	return true;
}
catch (Exception $e)
{
	$core->error->add($e->getMessage());
}
return false;
