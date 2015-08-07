<?php

// ADDING AND REMOVING COLUMNS FROM TABLE

// include db class
include '../xmldb/xmldb.php';

// connect to database 
$db = xmlDb::connect('example_database');

// add table
$db->addTable('example_table');



// add column

// you can have unlimited number of columns in table

// note: for doing anything with table (adding columns, removing columns, selecting data, etc.) 
// first you must select table by calling method table(table_name)
// example: $db->table('table_name');


// add column id and set it's value to 1
$db->table('example_table')->addColumn('id', 1);

// add another column called name
$db->table('example_table')->addColumn('name', 'John');

// add another column called lastname
$db->table('example_table')->addColumn('lastname', 'Doe');



// now we can get columns from our table
// method getColumns() returns an array of columns names

// you can get columns names like this
$columns = $db->table('example_table')->getColumns();

print_r($columns);

// or like this
$columns = $db->getColumns('example_table');

print_r($columns);



// removing column from table

/*
$db->table('example_table')->removeColumn('lastname');

// get the columns
$columns = $db->table('example_table')->getColumns();

print_r($columns);

*/

