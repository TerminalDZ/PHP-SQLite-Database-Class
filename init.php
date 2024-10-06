<?php
require_once 'Database.php'; // Include the Database class

$db = Database::getInstance(); // Create an instance of the Database class

// Get the value of the 'name' column from the 'users' table
/*
$name = $db->getValue('users', 'name');

echo "The value of the 'name' column is: $name\n"; // Output the value of the 'name' column
*/
?>