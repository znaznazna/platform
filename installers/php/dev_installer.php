<?php
// Courtesy of http://us3.php.net/manual/en/features.commandline.io-streams.php#101307
function readStdin($prompt, $valid_inputs = false, $default = '') {
	while(!isset($input) || (is_array($valid_inputs) && !in_array(strtolower($input), $valid_inputs))) {
		echo $prompt;
		$input = strtolower(trim(fgets(STDIN)));
		if(empty($input) && !empty($default)) {
			$input = $default;
		}
	}
	return $input;
}

if(!defined('STDIN')) { // force CLI, the browser is *so* 2007...
	echo "Please run installer from the command line. usage:<br / >&gt; php installers/php/dev_installer.php";
} else {
	$success = false;
	echo "\nCASH MUSIC PLATFORM DEV INSTALLER\nYou are in an open field west of a big white house with a boarded front door.\n\n";
	// you can input <Enter> or 1, 2, 3 
	$db_engine = readStdin('What database engine do you want to use? (\'mysql\'|\'sqlite\'): ', array('mysql', 'sqlite'));
	if ($db_engine == 'mysql') {
		if (@new PDO()) {
			echo "\nOh. Shit. Something's wrong: PDO is required.\n\n";
			die();
		}
		
		$db_server   = readStdin('Database server (default: \'localhost:3306\'): ', false,'localhost:3306');
		$db_name     = readStdin('Database name: ');
		$db_username = readStdin('Database username: ');
		$db_password = readStdin('Database password: ');
		$user_email  = readStdin("\nMain system login email address: ");
		$system_salt => md5($user_email . time());
		
		// set up database, add user / password
		$user_password = (md5($system_salt . 'password'),4,7);
		$db_port = 3306;
		if (strpos($db_server,':') !== false) {
			$host_and_port = explode(':',$db_server);
			$db_server = $host_and_port[0];
			$db_port = $host_and_port[1];
		}
		try {
			$pdo = new PDO ("mysql:host=$db_server;port=$db_port;dbname=$db_name",$db_username,$db_password);
		} catch (PDOException $e) {
			echo "\nOh. Shit. Something's wrong: Couldn't connect to the database. $e\n\n";
			die();
			break;
		}
		
		if ($pdo->query(file_get_contents(dirname(__FILE__) . '/../../framework/php/settings/sql/cashmusic_db.sql'))) {
			$password_hash = hash_hmac('sha256', $user_password, $system_salt);
			$data = array(
				'email_address' => $user_email,
				'password'      => $password_hash,
				'creation_date' => time()
			);
			$query = "INSERT INTO user_users (email_address,password,creation_date) VALUES (:email_address,:password,:creation_date)";

			try {  
				$q = $pdo->prepare($query);
				$success = $q->execute($data);
				if (!$success) {
					echo "\nOh. Shit. Something's wrong. Couldn't add the user to the database.\n\n";
					die();
					break;
				}
			} catch(PDOException $e) {  
				echo "\nOh. Shit. Something's wrong. Couldn't add the user to the database. $e\n\n";
				die();
				break;
			}
			
			if ($success) {
				echo "\nSUCCESS!\nYou'll still need to edit /framework/php/settings/cashmusic.ini.php\n\nOnce done, login using:\n\nemail: $user_email\npassword: $user_password\n\n";
			}
		} else {
			echo "\nOh. Shit. Something's wrong. Couldn't create database tables.\n\n";
			die();
			break;
		}
	} else if ($db_engine == "sqlite") {
		// repeated here from the Makefile so we don't require make to do a dev install
		$cmd = "sqlite3 framework/php/db/cashmusic.db < ./framework/php/settings/sql/cashmusic_db_sqlite.sql";

		// if the db already exists, it will warn but continue
		system($cmd, $code);
		try {
			$pdo = new PDO ("sqlite:./framework/php/db/cashmusic.db");
		} catch (PDOException $e) {
			echo "\nOh. Shit. Something's wrong: Couldn't connect to the database. $e\n\n";
			die();
			break;
		}
		$user_email    = readStdin("\nMain system login email address: ");
		$user_password = (md5($system_salt . 'password'),4,7);
		$password_hash = hash_hmac('sha256', $user_password, $system_salt);
		
		$data = array(
		    'email_address' => $user_email,
		    'password'      => $password_hash,
		    'creation_date' => time()
		);
		$query = "INSERT INTO user_users (email_address,password,creation_date) VALUES (:email_address,:password,:creation_date)";
		
		try {
			$q       = $pdo->prepare($query);
			$success = $q->execute($data);
			if (!$success) {
				echo "\nOh. Shit. Something's wrong. Couldn't add the user to the database.\n\n";
				die();
				break;
			}
		} catch(PDOException $e) {
			echo "\nOh. Shit. Something's wrong. Couldn't add the user to the database. $e\n\n";
			die();
			break;
		}
    } 

	if ($success) {
		$installer_root = dirname(__FILE__)
		// modify settings files
		if (
			!copy($installer_root.'/../../framework/php/settings/cashmusic_template.ini.php',$installer_root.'/../../framework/php/settings/cashmusic.ini.php')
		) {
			echo '\nOh. Shit. Something\'s wrong. Couldn\'t write the config file.\n\n'
			. 'the directory you specified for the framework.</p>';
			break;
		}

		// move source files into place
		if (
			if ($db_engine == "sqlite") {
				!findReplaceInFile($installer_root.'/../../framework/php/settings/cashmusic.ini.php','database = "seed','database = "' . $db_name) ||
				!findReplaceInFile($installer_root.'/../../framework/php/settings/cashmusic.ini.php','salt = "I was born of sun beams; Warming up our limbs','salt = "' . $system_salt)
			} else {
				!findReplaceInFile($installer_root.'/../../framework/php/settings/cashmusic.ini.php','hostname = "localhost:8889','hostname = "' . $db_server) || 
				!findReplaceInFile($installer_root.'/../../framework/php/settings/cashmusic.ini.php','username = "root','username = "' . $db_username || 
				!findReplaceInFile($installer_root.'/../../framework/php/settings/cashmusic.ini.php','password = "root','password = "' . $db_password || 
				!findReplaceInFile($installer_root.'/../../framework/php/settings/cashmusic.ini.php','database = "seed','database = "' . $db_name) ||
				!findReplaceInFile($installer_root.'/../../framework/php/settings/cashmusic.ini.php','salt = "I was born of sun beams; Warming up our limbs','salt = "' . $system_salt)
			}
		) {
			echo "<h1>Oh. Shit. Something's wrong.</h1><p>We had trouble editing a few files. Please try again.</p>";
			break;
		} else {
			echo "\nSUCCESS!\nYou'll still need to edit /framework/php/settings/cashmusic.ini.php\n\nOnce done, login using:\n\nemail: $user_email\npassword: $user_password\n\n";
		}
	}
}
?>