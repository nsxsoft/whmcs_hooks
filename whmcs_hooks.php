<?php



//   SQL FUNCTIONS

function sqlCreateConnection() {
	// Load DB variables
	$conf_vars = loadConfVariables()
	$host = $conf_vars['hub']['host'];
	$name = $conf_vars['hub']['name'];
	$user = $conf_vars['hub']['user'];
	$pass = $conf_vars['hub']['pass'];
	
	// connect
	$dsn = "mysql:host=$host;dbname=$name";
	$conn = new PDO($dsn, $user, $pass);
	return $conn; 
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



//   PROCESSING FUNCTIONS

function loadConfVariables() {
	$switches_file = "/var/www/pixelxen.store/htdocs/includes" .
				"/hooks/whmcs_hooks_conf.json";
	$strJsonFileContents = file_get_contents($switches_file);
	$switches = json_decode($strJsonFileContents, true);
	return $switches;
}

function curlPixpanelHub($endpoint, $data) {
	$ch = curl_init();

	# Load PixpanelHub API variables
	$conf_vars = loadConfVariables()
	$host = $conf_vars['hub']['host'];
	$port = $conf_vars['hub']['port'];
	$api  = $conf_vars['hub']['api'];
	curl_setopt($ch, CURLOPT_URL, "$host:$port" . $api . $endpoint);

	# Authentication
	#$cred = $conf_vars['user'] . ":" . $conf_vars["pass"];
	#curl_setopt($ch, CURLOPT_USERPWD, $cred);
	#curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

	# Headers
	$headers = array("Content-Type: application/json");
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

	# SSL
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, False);

	# Return as string
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, True);

	# POST parameters
	curl_setopt($ch, CURLOPT_POST, True);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

	# Execution and return
	$output = json_decode(curl_exec($ch), true);
	curl_close ($ch);
	return $output;
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



//   HOOK FUNCTIONS

function hook_ClientAdd($vars) {

	$endpoint = '/client/add';

	// Load parameters from $vars
	$cn = $vars['companyname'];
	$em = $vars['email'];
	$fn = $vars['firstname'];
	$ln = $vars['lastname'];
	$pw = $vars['password'];
	$ui = $vars['userid'];

	// Get client from WHMCS database
	$ci = getWHMCSClientID($ui);

	$data = "{
          \"companyname\": \"$cn\",
          \"customerid\":  \"$ci\",
          \"email\":       \"$em\",
          \"firstname\":   \"$fn\",
          \"lastname\":    \"$ln\",
          \"password\":    \"$pw\",
          \"userid\":      \"$ui\"
	}";

	res = curlPixpanelHub($endpoint, $data);
}

function hook_ClientChangePassword($vars) {

	$endpoint = '/client/password';

	// Load parameters from $vars
	$pw = $vars['password'];
	$ui = $vars['userid'];

	// Get email for WHMCS userid
	$query = "
	  SELECT email
	  FROM tblclients
	  WHERE id = $ui";
	$em = sqlQuery($query)['email'];

	$data = "{
          \"email\":       \"$em\",
          \"password\":    \"$pw\",
          \"userid\":      \"$ui\"
	}";

	res = curlPixpanelHub($endpoint, $data);
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

	$endpoint = '/contact/add';

	// Load parameters from $vars
	$co = $vars['contactid'];
	$em = $vars['email'];
	$fn = $vars['firstname'];
	$ln = $vars['lastname'];
	$pw = $vars['password'];
	$sa = $vars['subaccount'];
	$ui = $vars['userid'];

	// Perform action only if it is a sub-account
	if ($sa == '0') { return ; }

	// Get client from WHMCS database
	$ci = getWHMCSClientID($ui);

	$data = "{
          \"contactid\":   \"$co\",
          \"customerid\":  \"$ci\",
          \"email\":       \"$em\",
          \"firstname\":   \"$fn\",
          \"lastname\":    \"$ln\",
          \"password\":    \"$pw\",
          \"userid\":      \"$ui\"
	}";

	res = curlPixpanelHub($endpoint, $data);
}

function hook_ContactChangePassword($vars) {

	$endpoint = '/contact/password';

	// Load parameters from $vars
	$co = $vars['contactid'];
	$pw = $vars['password'];

	// Only perform actions if user is known
	if( is_null($co) ) { return ; }

	$data = "{
          \"contactid\":   \"$co\",
          \"password\":    \"$pw\"
	}";

	res = curlPixpanelHub($endpoint, $data);
}

function hook_ContactEdit($vars) {

	# TO BE COMPLETED

	$endpoint = '/contact/edit';
}



//   HOOK ENABLING

add_hook('ClientAdd',                1, hook_ClientAdd);
add_hook('ClientChangePassword',     1, hook_ClientChangePassword);
add_hook('ClientDetailsValidation',  1, hook_ClientDetailsValidation);
add_hook('ContactAdd',               1, hook_ContactAdd);
add_hook('ContactChangePassword',    1, hook_ContactChangePassword);
add_hook('ContactEdit',              1, hook_ContactEdit);
