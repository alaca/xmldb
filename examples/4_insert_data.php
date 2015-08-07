<?php

// INSERTING DATA

// include db class
include '../xmldb/xmldb.php';


// connect to database 
$db = xmlDb::connect('example_database');

// $db->addTable('example_table');

// you can insert data in two diferent ways

// by passing an array with column names and their values to insert method
$data = array(
    'name'     => 'My name',
    'lastname' => 'My last name',
);

$db->table('example_table')->insert($data);


// or by using method bind(column_name, value) with insert
$db->table('example_table')
   ->bind('name', 'My other name') 
   ->bind('lastname', 'My other last name')
   ->insert();
   

// get data

$db->table('example_table')->select('*');

$data = $db->getAll();

print_r($data);
