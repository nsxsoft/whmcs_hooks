<?php
add_hook('ClientAdd', 1, function($vars) {
	  
	/////////////////////////
	// otrs.Console.pl API //
	/////////////////////////
	/* --help
	Add a customer company.

	Usage:
	 otrs.Console.pl Admin::CustomerCompany::Add --customer-id ... --name ... [--street ...] [--zip ...] [--city ...] [--country ...] [--url ...] [--comment ...]

	Options:
	 --customer-id ...              - Company ID for the new customer company.
	 --name ...                     - Company name for the new customer company.
	 [--street ...]                 - Street of the new customer company.
	 [--zip ...]                    - ZIP code of the new customer company.
	 [--city ...]                   - City of the new customer company.
	 [--country ...]                - Country of the new customer company.
	 [--url ...]                    - URL of the new customer company.
	 [--comment ...]                - Comment for the new customer company.
	 [--help]                       - Display help for this command.
	 [--no-ansi]                    - Do not perform ANSI terminal output coloring.
	 [--quiet]                      - Suppress informative output, only retain error messages.
	 */

	// Load json switches
	$json_file = '/var/www/pixelxen.store/htdocs/includes/hooks/whmcs_hooks_switches.json';
	$strJsonFileContents = file_get_contents($json_file);
	$switches = json_decode($strJsonFileContents, true);
	$hook_switches = $switches['ClientAdd'];

	// Get 'Client Id' from WHMCS database
	$dbsocket = "/var/run/mysqld/mysqld.sock";
	$dbname   = "store";
	$username = "whmcs_hook";
	$query = "
	  SELECT value
	  FROM tblcustomfieldsvalues
	  WHERE (
	    relid = (
	      SELECT id
	      FROM tblclients
	      WHERE companyname = '" . $vars['companyname'] . "'
	    ) AND
	    fieldid = (
	      SELECT id
	      FROM tblcustomfields
	      WHERE fieldname = 'Client ID'
	    )
	  )";
	
	try {
		//$conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
		$conn = new PDO("mysql:unix_socket=$dbsocket;dbname=$dbname", $username);

		// set the PDO error mode to exception
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	      
		// query
		$stmt = $conn->query($query);
		$row = $stmt->fetch();
		$company_id = $row['value'];
	}
	catch(PDOException $e) { $e = "Connection failed: " . $e->getMessage(); }
	$conn = null;
	

	if($hook_switches['OTRS'] == 'Enabled')
	{
		// Connect the endpoint in pix-otrs with cURL
		$host = "pix-otrs.pixelxen.rocks";
		$port = 5000;
		$endpoint = "/api/v1/Admin/CustomerCompany/Add";
		$id = "customer_id=" . $company_id;
		$cn = "name=" . $vars['companyname'];
	
		$full_url = $host . ":" . $port . $endpoint . "?" . $id . "&" . $cn;
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $full_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$output = curl_exec($ch);
		curl_close ($ch);
	}
  });
