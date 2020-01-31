#<?php
#add_hook('HookName', 1, function($vars) {
#	// Load json switches
#	$json_file = '/var/www/pixelxen.store/htdocs/includes/hooks/whmcs_hooks_switches.json';
#	$strJsonFileContents = file_get_contents($json_file);
#	$switches = json_decode($strJsonFileContents, true);
#	if($switches['HookName'] == 'Enabled')
#	{
#		// Code...
#	}
#  });
