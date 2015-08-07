<?php

// GET DATA

// include xmlDb class
include '../xmldb/xmldb.php';

// connect to database 
$db = xmlDb::connect('example_database');

// $db->addTable('example_table');

// select table and columns
$db->table('example_table')
   ->select('*');

// get data using getRow()
$row = $db->getRow();

// getRow() returns object with column names as properties, if there is no data returns false 

// print data
echo 'id ' .$row->id . ', name ';
echo $row->name . '<br />';



// get data using getAll()

// select table and columns
$db->table('example_table')
   ->select('id, name, lastname');

// get all rows
$rows = $db->getAll();
// getAll() returns an array of objects, if there is no data returns false 

// print data
foreach($rows as $row)
{
    echo 'id ' .$row->id . ', name ';
    echo $row->name . '<br />';
}


