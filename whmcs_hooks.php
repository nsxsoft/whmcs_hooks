<?php

// Switches and Connection Functions

function addGrafanaTeamMember($tid, $uid) {
	$endpoint = "/api/teams/$tid/members";
	$data = json_encode( array('userId' => $uid) );

	return curlGrafanaPost($endpoint, $data);
}

function addOTRSCompany($customer_id, $company_name){
	$endpoint = "/api/v1/Admin/CustomerCompany/Add";

	$id = "customer_id=" . $customer_id;
	$cn = "name="        . $company_name;
	$params = "?$id&$cn";

	return curlOTRS($endpoint, $params);
}

function addOTRSUser($v_em, $v_fn, $v_ln, $v_ci, $v_pw) {
	$endpoint = "/api/v1/Admin/CustomerUser/Add";

	// Create full cli command for user OTRS CustomerUser creation
	$un = "user_name="     . $v_em;
	$fn = "first_name="    . $v_fn;
	$ln = "last_name="     . $v_ln;
	$em = "email_address=" . $v_em;
	$ci = "customer_id="   . $v_ci;
	$pw = "password="      . $v_pw;
	$params = "?$un&$fn&$ln&$em&$ci&$pw";

	return curlOTRS($endpoint, $params);
}

function changePasswordOTRSUser($user, $password) {
	$endpoint = "/api/v1/Admin/CustomerUser/SetPassword";

	$u = "user="     . $user;
	$p = "password=" . $password;
	$params = "?$u&$p";

	return curlOTRS($endpoint, $params);
}

function curlOTRS($endpoint, $qry_str) {
	$host = "pix-otrs.pixelxen.rocks";
	$port = 5000;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "$host:$port$endpoint$qry_str");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, True);
	$output = curl_exec($ch);
	curl_close ($ch);
	return $output;
}

function curlGrafanaGet($endpoint, $qry_str){
	$ch = curlGrafanaInit($endpoint . $qry_str);

	$output = json_decode(curl_exec($ch), true);
	curl_close ($ch);

	return $output;
}

function curlGrafanaInit($url_extension) {
	// Set fixed parameters
	$host =		'https://grafana.pixelxen.cloud';
	$port =		'3000';
	$cred =		"admin:pixelinside2011";
	$headers = 	array("Content-Type: application/json");

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "$host:$port" . $url_extension);
	curl_setopt($ch, CURLOPT_USERPWD, $cred);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, False);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, True);

	return $ch;
}

function curlGrafanaPost($endpoint, $data){
	$ch = curlGrafanaInit($endpoint);

	curl_setopt($ch, CURLOPT_POST, True);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

	$output = json_decode(curl_exec($ch), true);
	curl_close ($ch);

	return $output;
}

function curlGrafanaPut($endpoint, $data){
	$ch = curlGrafanaInit($endpoint);

	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

	$output = json_decode(curl_exec($ch), true);
	curl_close ($ch);

	return $output;
}

function createGrafanaTeam($company_id, $email) {
	$endpoint = "/api/teams";
	$data = "{
	  \"name\":  \"" . $company_id . "\",
	  \"email\": \"" . $email      . "\"
	}";

	$output = curlGrafanaPost($endpoint, $data);

	if($output['message'] == 'Team created') {
		return $output['teamId'];
	}
	else {	return False; }
}

function createGrafanaUser($fn, $ln, $em, $pw) {
	$endpoint =	"/api/admin/users";
	$data = "{
	  \"name\":	\"$fn $ln\",
	  \"email\":	\"$em\",
	  \"login\":	\"$em\",
	  \"password\":	\"$pw\"
	}";

	$output = curlGrafanaPost($endpoint, $data); 

	if ( $output['message'] == 'User created' ) {
		return $output['id'];
	}
	else { return False; }
}

function getGrafanaUserTeam($customer_id) {
	$endpoint = "/api/teams/search";
	$qry_str = "?name=$customer_id";
	$output = curlGrafanaGet($endpoint, $qry_str);
	return $output["teams"][0]["id"];
}

function getWHMCSClientID($uid) {
	$query = "
	  SELECT value
	  FROM tblcustomfieldsvalues
	  WHERE (
	    relid = '" . $uid . "'
	    AND
	    fieldid = (
	      SELECT id
	      FROM tblcustomfields
	      WHERE fieldname = 'Client ID'
	    )
	  )";
	$row = sqlQuery($query);
	return $row['value'];
}

function loadJsonSwitches($hookName) {
	$switches_file = "/var/www/pixelxen.store/htdocs/includes" .
				"/hooks/whmcs_hooks_switches.json";
	$strJsonFileContents = file_get_contents($switches_file);
	$switches = json_decode($strJsonFileContents, true);
	return $switches[$hookName];
}

function sqlCreateConnection() {
	// Variables
	$dbhost   = "localhost";
	$dbname   = "store";
	$username = "whmcs_hook";
	$password = "c4cec87ad251d49";
	
	// connect
	$dsn = "mysql:host=$host;dbname=$dbname";
	$conn = new PDO($dsn, $username, $password);
	return $conn; 
}

function sqlQueryFetchAll($q) {
	$conn = sqlCreateConnection();

	// set the PDO error mode to exception
	$conn->setAttribute(
		PDO::ATTR_ERRMODE,
		PDO::ERRMODE_EXCEPTION
	);
      
	// query
	$stmt = $conn->query($q);
	$result = $stmt->fetchAll();
	$conn = null;

	return $result;
}

function sqlQuery($q) {
	$conn = sqlCreateConnection();
	try {

		// set the PDO error mode to exception
		$conn->setAttribute(
			PDO::ATTR_ERRMODE,
			PDO::ERRMODE_EXCEPTION
		);
	      
		// query
		$stmt = $conn->query($q);
		$result = $stmt->fetch();
	}
	catch(PDOException $e) {
	  logActivity("Hook Error: " . $e->getMessage(), 0);
	}
	$conn = null;
	return $result;
}

//////////////////////////////////////////////
////////////                      ////////////
////////////    HOOK FUNCTIONS    ////////////
////////////                      ////////////
//////////////////////////////////////////////

function hook_ClientAdd($vars) {

	// Load parameters from $vars
	$v_ui = $vars['userid'];
	$v_cn = $vars['companyname'];
	$v_fn = $vars['firstname'];
	$v_ln = $vars['lastname'];
	$v_em = $vars['email'];
	$v_pw = $vars['password'];

	// Load json switches
	$hook_switches = loadJsonSwitches('ClientAdd');

	// Get client from WHMCS database
	$customer_id = getWHMCSClientID($v_ui);

	// OTRS
	if($hook_switches['OTRS'] == 'Enabled')
	{
		// Create 'CustomerCompany'
		addOTRSCompany($customer_id, $v_cn);

		// Create 'CustomerUser' for 'CustomerCompany'
		addOTRSUser($v_em, $v_fn, $v_ln, $v_ui, $v_pw);
	}

	// Grafana
	if($hook_switches['Grafana'] == 'Enabled')
	{
		// Create user
		$grafana_id = createGrafanaUser($v_fn, $v_ln, $v_em, $v_pw);

		// Create team for $customer_id
		$team_id = createGrafanaTeam($customer_id, $v_em);

		// Add team member
		$result = addGrafanaTeamMember($team_id, $grafana_id);
	}
}

function hook_ClientChangePassword($vars) {

	// Load parameters from $vars
	$v_ui = $vars['userid'];
	$v_pw = $vars['password'];

	// Load json switches
	$hook_switches = loadJsonSwitches('ClientChangePassword');

	// Get email for WHMCS userid
	$query = "
	  SELECT email
	  FROM tblclients
	  WHERE id = $v_ui";
	$em = sqlQuery($query)['email'];

	// OTRS
	if($hook_switches['OTRS'] == 'Enabled')
	{
		changePasswordOTRSUser($em, $v_pw);
	}

	// Grafana
	if($hook_switches['Grafana'] == 'Enabled')
	{
		// Find Grafana id using email
		$endpoint = "/api/users/lookup";
		$params = "?loginOrEmail=$em";
		$grafana_id = curlGrafanaGet($endpoint, $params)['id']; 

		// Change password
		$endpoint = "/api/admin/users/$grafana_id/password";
		$data = json_encode( array('password' => $v_pw) );

		$res = curlGrafanaPut($endpoint, $data);
	}
}

function hook_ClientDetailsValidation($vars) {

	// Get Client Id string from $vars
	$query = "
	  SELECT id
	  FROM tblcustomfields
	  WHERE	fieldname = 'Client Id'
	";
	$customstring_id = sqlQuery($query)['id'];
	$client_id = $vars['customfield'][$customstring_id];

	// Check if it's a valid string
	$match_patt = '/^[a-z]{3}$/';
	if( !preg_match($match_patt, $client_id) ) {
		return "Given Client ID '$client_id' does not match format '$match_patt'.";
	}

	// At this point, 'Client ID' has a valid format.
	// It's not possible to make sure 'Client Id' is not being
	// used by another client account. The reason for that is that 
	// this hook for 'ClientDetailsValidation' is triggered for both
	// 'ClientAdd' and 'ClientEdit', but it is impossible to know 
	// which one. For a Client Id that is already in use, an error
	// should be raised for 'ClientAdd'; for 'ClientEdit' only
	// should be raised in case a client A is trying to change to
	// the same code as a client B. If it is the same client, no 
	// errors should be raised, but WHMCS don't let you compare 
	// stored data with new one, as treats every field as new.

	// Nothing is to be returned on success.
}

function hook_ContactAdd($vars) {

	// Load parameters from $vars
	$v_sa = $vars['subaccount'];
	$v_ui = $vars['userid'];
	$v_fn = $vars['firstname'];
	$v_ln = $vars['lastname'];
	$v_em = $vars['email'];
	$v_pw = $vars['password'];
	$v_ci = $vars['contactid'];

	// Perform action only if it is a sub-account
	if ($v_sa == '0') { return ; }

	// Load json switches
	$hook_switches = loadJsonSwitches('ContactAdd');

	// Get client from WHMCS database
	$customer_id = getWHMCSClientID($v_ui);

	// OTRS
	if($hook_switches['OTRS'] == 'Enabled')
	{
		addOTRSUser($v_em, $v_fn, $v_ln, $v_em, $customer_id, $v_pw);
	}

	// Grafana
	if($hook_switches['Grafana'] == 'Enabled')
	{
		// Create user
		$grafana_id = createGrafanaUser($v_fn, $v_ln, $v_em, $v_pw);
		
		// Get team_id for user
		$team_id = getGrafanaUserTeam($customer_id);
		
		// Add team member
		addGrafanaTeamMember($team_id, $grafana_id);
	}

	// Save correspondants
	$query = "
	  INSERT INTO whmcs_contacts_correspondants(
	    whmcs_contactid,
	    otrs_login,
	    grafana_id
	  )
	  VALUES(
	    $v_ci, 
	    '$v_em',
	    $grafana_id
	  )";
	sqlQuery($query);
}

function hook_ContactChangePassword($vars) {

	// Load parameters from $vars
	$v_ci = $vars['contactid'];
	$v_pw = $vars['password'];

	// Only perform actions if user is known
	if( is_null($v_ci) ) { return ; }

	// Load json switches
	$hook_switches = loadJsonSwitches('ContactChangePassword');

	// Get email for WHMCS contactid
	$query = "
	  SELECT email
	  FROM tblcontacts
	  WHERE id = $v_ci";
	$em = sqlQuery($query)['email'];

	// OTRS
	if($hook_switches['OTRS'] == 'Enabled')
	{
		changePasswordOTRSUser($em, $v_pw) . "\n";
	}

	// Grafana
	if($hook_switches['Grafana'] == 'Enabled')
	{
		// Find Grafana id using email
		$endpoint = "/api/users/lookup";
		$params = "?loginOrEmail=$em";
		$grafana_id = curlGrafanaGet($endpoint, $params)['id']; 

		// Change password
		$endpoint = "/api/admin/users/$grafana_id/password";
		$data = json_encode( array('password' => $v_pw) );

		curlGrafanaPut($endpoint, $data);
	}
}

function hook_ContactEdit($vars) {
	$query = "
	  SELECT id, password
	  FROM tblcontacts
	  WHERE subaccount = 1
	";
	$id_pw_list = sqlQueryFetchAll($query);

	$txt = '';
	foreach ($id_pw_list as $row_key => $row) {
		$id = $row['id'];
		$pw_hash = $row['password'];
		$txt .= "$id $pw_hash\n";
	}

	$txt .= var_export($vars, true) . "\n";

	$fd = fopen('/tmp/whmcs_hooks_ContactEdit.debug', 'w');
	fwrite( $fd, $txt );
	fclose($fd);
}

/////////////////////////////////////////////
////////////                     ////////////
////////////    HOOKS ENABLING   ////////////
////////////                     ////////////
/////////////////////////////////////////////

add_hook('ClientAdd',  1, hook_ClientAdd);
add_hook('ClientChangePassword', 1, hook_ClientChangePassword);
add_hook('ClientDetailsValidation',  1, hook_ClientDetailsValidation);
add_hook('ContactAdd', 1, hook_ContactAdd);
add_hook('ContactChangePassword', 1, hook_ContactChangePassword);
add_hook('ContactEdit', 1, hook_ContactEdit);
