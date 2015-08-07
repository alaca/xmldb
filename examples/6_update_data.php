<?php

// UPDATE DATA

// include db class
include '../xmldb/xmldb.php';


// connect to database 
$db = xmlDb::connect('example_database');

// $db->addTable('example_table');

// you can update data in two diferent ways

// by passing an array with column names and their values to update method
$data = array(
    'name'     => 'My other name',
    'lastname' => 'My other last name',
);

$db->table('example_table')
   ->where('name = "My name"') 
   ->update($data);
   
// check how many rows are updated
echo 'Rows updated: ' . $db->affectedRows() . '<br />';


// or by using method bind(column_name, value)
$db->table('example_table')
   ->where('name = "John"')
   ->bind('name', 'My other name') 
   ->bind('lastname', 'My other last name')
   ->update();
   
// check how many rows are updated
echo 'Rows updated: ' . $db->affectedRows();
   
