<?php

// ADDING AND REMOVING TABLES

// include db class
include '../xmldb/xmldb.php';

// connect to database 
$db = xmlDb::connect('example_database');

// add table to database
$db->addTable('example_table');

// you can have unlimited number of table in database so let's add another table
$db->addTable('another_table');


// now we can get tables from our database
// method getTables() returns an array of table names
$tables = $db->getTables();

// print tables names
print_r($tables);



// you can also remove table from db by calling method removeTable(table_name)
$db->removeTable('another_table');

// get tables
$tables = $db->getTables();

// print tables
print_r($tables);