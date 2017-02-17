# PHP XML DATABASE - XMLDB

###Connect 
```php
// include library
include 'xmldb.php';

// connect to db
$db = xmlDb::connect('example_database');
```

###Add table 
```php
// add table to database
$db->addTable('example_table');

$db->addTable('another_table');

// getTables() returns an array of table names
$tables = $db->getTables();

// print tables names
print_r($tables);
```

###Remove table
```php
// remove table from database
$db->removeTable('another_table');
```


###Get tables
```php
$tables = $db->getTables();
// print tables
print_r($tables);
```

###Add column
```php
$db->table('example_table')->addColumn('id', 1);
$db->table('example_table')->addColumn('name', 'John');
$db->table('example_table')->addColumn('lastname', 'Doe');
```

###Get column
```php
$columns = $db->table('example_table')->getColumns();
// or
$columns = $db->getColumns('example_table');
```



###Remove column from table
```php
$db->table('example_table')->removeColumn('lastname');
```

### Insert data into database
```php
$data = [
    'name'     => 'My name',
    'lastname' => 'My last name',
];

$db->table('example_table')->insert($data);


// or

$db->table('example_table')
   ->bind('name', 'My name 2') 
   ->bind('lastname', 'My last name 2')
   ->insert();
```



###Get data from database
```php
// getRow() returns object with column names as properties, if there is no data returns false 
$db->table('example_table')->select('id, name')->getRow();

// print data
echo 'id: ' . $row->id;
echo 'name: ' . $row->name;


// getAll() returns an array of objects, if there is no data returns false 
$rows = $db->table('example_table')->select('id, name')->getAll();

// print data
foreach ($rows as $row) {
    echo 'id: ' .$row->id;
    echo 'name: ' . $row->name . '<br />';
}




// join tables
$db->table('users')
   ->join('users_table' ,'id', 'user_id') //join() method must come immediately after table() method
   ->select('*');
// this will combine data from two tables where column "id" of "users" table
// euqals to column "user_id" of "users_data" table

$data = $db->getAll();

print_r($data);

// using where()

$db->table('users')
   ->join('users_table' ,'id', 'user_id')
   ->select('id, name, lastname')
   ->where('id = 2'); // where "users" table "id" = 2

$user = $db->getRow();
```


###Update data
```php
$data = [
	'name'     => 'My name updated',
	'lastname' => 'My last name updated',
];

$db->table('example_table')
   ->where('name = "My name"') 
   ->update($data);

// print num rows updated
echo 'Rows updated: ' . $db->affectedRows() . '<br />';

// another way of updating data
$db->table('example_table')
   ->where('name = "My name updated"')
   ->bind('name', 'My name updtaed again') 
   ->bind('lastname', 'My last name updated again')
   ->update();

echo 'Rows updated: ' . $db->affectedRows() . '<br />';
```



###Delete data
```php
$xmlDb->table('example_table')
      ->where('name = "My name updtaed again"')
      ->delete();



// we can limt delete by calling method limit()
$xmlDb->table('example_table')
      ->where('id > 3')
      ->limit(5)
      ->delete();

echo 'Rows deleted: ' . $xmlDb->affectedRows();
```