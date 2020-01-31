#<?php
#add_hook('HookName', 1, function($vars) {
#	// Load json switches
#	$json_file = '/var/www/pixelxen.store/htdocs/includes/hooks/whmcs_hooks_switches.json';
#	$strJsonFileContents = file_get_contents($json_file);
#	$switches = json_decode($strJsonFileContents, true);
#	$hook_switches = $switches['HookName'];
#
#	// Common code for data gathering
#
#	if($hook_switches['Grafana'] == 'Enabled')
#	{
#		// Code to communicate with Grafana...
#	}
#
#	if($hook_switches['OTRS'] == 'Enabled')
#	{
#		// Code to communicate with OTRS...
#	}
#  });
