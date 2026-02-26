<?php

// Database interaction PHP code
class loginDB
{
// Private variable for network connection to MySQL
private $logindb;

// Constructor, automatically called when class is called 
public function __construct()
{
	// TCP connection to MySQL server, local access on backend VM only
	$this->logindb = new mysqli("127.0.0.1","backendVM","password","it490_db");

	// Check if failed connection or credentials
	if ($this->logindb->connect_errno != 0)
	{
		echo "Error connecting to database: ".$this->logindb->connect_error.PHP_EOL;
		exit(1);
	}
	// Success message
	echo "correctly connected to database".PHP_EOL;
}

// Login function to verify user + generate session id
public function validateLogin($username,$password)
{
	// Sanitize username against SQL Injection
	$un = $this->logindb->real_escape_string($username);
	// Query to find row of user attempting login
	$statement = "select * from users where screenname = '$un'";
	// Execute query in MySQL database
	$response = $this->logindb->query($statement);

	// Fetch response in key:value pairs
	while ($row = $response->fetch_assoc())
	{
		echo "checking password for $username".PHP_EOL;

		// Compare the password typed against the stored hash for the user
		if (password_verify($password, $row["password_hash"]))
		{
			echo "passwords match for $username".PHP_EOL;

			// Generate session token now that user is logged in
			$session_key = bin2hex(random_bytes(16));

			// Update session_id in user's row with this session key
			$update = "UPDATE users SET session_id = '$session_key' WHER username = '$un'";
			// Execute update query
			$this->logindb->query($update);

			// Send success message and new generated token to frontend as array package
			return array("status" => "success", "session_id" => $session_key);
		}

		// Error message for incorrect password
		echo "passwords did not match for $username".PHP_EOL;
	}
	// Return invalid login in case of no 
	return array("status" => "error", "message" => "Invalid Login");
}

// Registration function for creating new secure acct
public function registerUser($username, $password)
{
	// Sanitize input against SQL Injection
	$un = $this->logindb->real_escape_string($username);

	// Hash selected user password (we never store plaintext passwords)
	$hashed_pw = password_hash($password, PASSWORD_DEFAULT) // PASSWORD_DEFAULT auto updates when stronger hashes in the future become standard
	
	// Query to insert new user's username and password into database
	$statement = "INSERT INTO users (username, password_hash) VALUES ('$un', '$hashed_pw')";

	// If username is unique (checked by database as un = unique), success
	if ($this->logindb->query($statement))
	{
		return array("status" => "success", "message" => "Registration Success"); \
	}
	
	// If registration fails bc of unique constraint in db, error
	return array("status" => "error", "message" => "Username Taken");
}
// Logout Function goes here

}
?>
