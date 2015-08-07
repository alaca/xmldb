<?php

// DELETE DATA


// include xmlDb class
include '../xmldb/xmldb.php';

// connect to database 
$xmlDb = xmlDb::connect('example_database');

// $db->addTable('example_table');


// delete all rows where column "name" equal to "John" 
$xmlDb->table('example_table')
      ->where('name = "John"')
      ->delete();
   
   
// limiting delete

// we can limt delete by calling method limit()
$xmlDb->table('example_table')
      ->where('id > 3')
      ->limit(5)
      ->delete();
// this will delete 5 rows where row column "id" is greater then 3

// then we can check how many rows is deleted by calling method affectedRows()
echo 'Rows deleted: ' . $xmlDb->affectedRows();