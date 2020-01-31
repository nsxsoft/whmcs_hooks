<?php
add_hook('ContactAdd', 1, function($vars) {
	  
	/////////////////////////
	// otrs.Console.pl API //
	/////////////////////////
	/* --help
	Add a customer user.

	Usage:
	 otrs.Console.pl Admin::CustomerUser::Add --user-name ... --first-name ... --last-name ... --email-address ... --customer-id ... [--password ...]

	Options:
	--user-name ...                - User name for the new customer user.
	--first-name ...               - First name of the new customer user.
	--last-name ...                - Last name of the new customer user.
	--email-address ...            - Email address of the new customer user.
	--customer-id ...              - Customer ID for the new customer user.
	[--password ...]               - Password for the new customer user. If left empty, a password will be generated automatically.
	[--help]                       - Display help for this command.
	[--no-ansi]                    - Do not perform ANSI terminal output coloring.
	[--quiet]                      - Suppress informative output, only retain error messages.
	*/

	// Load json switches
	$json_file = '/var/www/pixelxen.store/htdocs/includes/hooks/whmcs_hooks_switches.json';
	$strJsonFileContents = file_get_contents($json_file);
	$switches = json_decode($strJsonFileContents, true);
	if($switches['ContactAdd'] == 'Enabled')
	{
		# Load direct contact parameters from $vars
		$v_fn = $vars['firstname'];
		$v_ln = $vars['lastname'];
		$v_em = $vars['email'];
	
		# Get client from WHMCS database
		$dbsocket = "/var/run/mysqld/mysqld.sock";
		$dbname   = "store";
		$username = "whmcs_hook";
		$query    = "
		  SELECT value
		  FROM tblcustomfieldsvalues
		  WHERE (
		    relid = (
		      SELECT userid
		      FROM tblcontacts
		      WHERE (
		        email     = '" . $v_em . "' AND
		        firstname = '" . $v_fn . "' AND
		        lastname  = '" . $v_ln . "'
		      )
		    ) AND
		    fieldid = (
		      SELECT id
		      FROM tblcustomfields
		      WHERE fieldname = 'Client ID'
		    )
		  )";
		
		// connect
		try {
			//$conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
			$conn = new PDO("mysql:unix_socket=$dbsocket;dbname=$dbname", $username);
	
			// set the PDO error mode to exception
			$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		      
			// query
			$stmt = $conn->query($query);
			$row = $stmt->fetch();
			$customer_id = $row['value'];
		}
		catch(PDOException $e) {
		  #$error_message = "Connection failed: " . $e->getMessage();
		  logActivity("ContactAdd Hook Error: " . $e->getMessage(), 0);
		}
		$conn = null;
	
		// Connect the endpoint in pix-otrs with cURL
		$host = "pix-otrs.pixelxen.rocks";
		$port = 5000;
		$endpoint = "/api/v1/Admin/CustomerUser/Add";
		// Create full cli command for user OTRS CustomerUser creation
		$un = "user_name="     . $v_em;
		$fn = "first_name="    . $v_fn;
		$ln = "last_name="     . $v_ln;
		$em = "email_address=" . $v_em;
		$ci = "customer_id="   . $customer_id;
	
		$params = $un . "&" . $fn . "&" . $ln . "&" . $em . "&" . $ci;
		$full_url = $host . ":" . $port . $endpoint . "?" . $params;
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $full_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($ch);
		curl_close ($ch);
	
		# TODELETE # Save output to file
		$fd = fopen("/tmp/whmcs_CustomerUser", "w");
		fwrite($fd, $full_url . "\n" . $output);
		fclose($fd);
	}
  });
