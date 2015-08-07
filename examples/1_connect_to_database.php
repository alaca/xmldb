<?php

// CREATE AND CONNNECT TO DATABASE

// first include db class
include '../xmldb/xmldb.php';


// if db file not exists it will be created automaticly
$db = xmlDb::connect('example_database');
// $db is now database object instance 


