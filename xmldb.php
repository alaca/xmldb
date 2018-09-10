<?php
/**
 * PHP XML DB
 *  
 * @copyright 2015
 * @author Ante Laca <ante.laca@gmail.com>
 * 
 */
class xmlDb
{

    /**
    * xmlDb instance
    * 
    * @var array
    */
    private static $instance = [];

    /**
    * cache queries
    * 
    * @var boolean
    */
    public  $cache         = true;

    private $db            = null;
    private $fh            = null;
    private $lock          = null;
    private $xml           = null;
    private $table         = null;
    private $query         = null;
    private $bind          = [];
    private $columns       = [];
    private $sort          = [];
    private $join_table    = null;
    private $primary_key   = null;
    private $foreign_key   = null;
    private $limit         = 0;
    private $affected_rows = 0;


    /**
    * constructor
    */
    private function __construct($database)
    {        
        // lock file
        $this->lock = dirname(__FILE__) . '/data/' . $database . '.lock';
        
        // if file exists that means the db file is in use, so we wait - trying to prevent race condition
        while (true == file_exists($this->lock)) {
            usleep(10); 
        }
        
        // create in use file, we are using this db now  
        while (!$this->fh = @fopen($this->lock, 'w')) {
            usleep(10); 
        }
        
        // wait until file is locked    
        while (!flock($this->fh, LOCK_EX)) {
            usleep(10); 
        }
        
        // register shutdown 
        register_shutdown_function([$this, '__unlock']); 
        
        
        // .lock file is created and locked, now we can work with database
        
        // db file
        $this->db = dirname(__FILE__) . '/data/' . $database . '.xml';
        
        if (!file_exists($this->db)) {
            // if db file not exists, create db file with db schema
            file_put_contents($this->db, '<?xml version="1.0" encoding="utf-8"?><database></database>'); 
            // set permissions on db file
            xmlDb::chmod($database);  
        }
        
        // try to load xml
        try {
	    libxml_use_internal_errors(true);
            $this->xml = new SimpleXMLElement(file_get_contents($this->db));
        } catch(Exception $e) {
            exit('Error: ' . $e->getMessage());
        } 
    }
    
    
    /**
    * unlock and delete .lock file
    * 
    */
    public function __unlock()
    {
        flock($this->fh, LOCK_UN);
        fclose($this->fh); 
        @unlink($this->lock);   
    }
    
    
    /**
    * connect to database
    * 
    * @param string $database
    * @return xmlDb
    */
    public static function connect($database)
    {
        if (!isset(static::$instance[$database])) {
            static::$instance[$database] = new self($database);
        }  
        
        return static::$instance[$database];
    }
        
    
    /**
    * create database backup
    * 
    * @return xmlDb
    */
    public function backup()
    {
        if (!copy($this->db, $this->db . '.bak')) {
            trigger_error('Can\'t create backup');    
        }   
         
        return $this;
    }
    
    
    /**
    * restore database backup
	* 
    * @return xmlDb
    */
    public function restore()
    {
        if (file_exists($this->db . '.bak')) {

            if (!copy($this->db . '.bak', $this->db)) {
                trigger_error('Can\'t restore backup');
            }
            
            static::clearCache();     
        }

        return $this;
    }

    
    /**
    * delete databse
    *  
    * @param string $database
    */
    public static function dropDatabase($database)
    {
        if (file_exists($file = dirname(__FILE__) . '/data/' . $database . '.xml')) {

            static::clearCache();
            
            return unlink($file);  
        }
        
        return false; 
    }
    
    
    /**
    * chmod database file
    * 
    * @param string $database
    * @param int $permissions
    */
    public static function chmod($database, $permissions = 0644)
    {
        if (file_exists($file = dirname(__FILE__) . '/data/' . $database . '.xml')) {
            return chmod($file, $permissions); 
        }
        
        return false;    
    }
    
    
    /**
    * get permissions of database file 
    * 
    * @param string $database
	* @return float
    */
    public static function getDatabasePerms($database)
    {
        return substr(decoct(fileperms(dirname(__FILE__) . '/data/' . $database . '.xml')), 2);
    }
    
    
    /**
    * add table to database
    * 
    * @param string $name
    */
    public function addTable($name)
    {     
        $table = $this->xml->xpath($name);
        
	if (empty($table)) {
            $this->xml->addChild($name)->addChild('row');
            return $this->save();     
        }           
    }
    
    
    /**
    * remove table from database
    * 
    * @param string $name
    */
    public function removeTable($name)
    {
        foreach ($this->xml->xpath('//database/' . $name) as $row) { 
            $node = dom_import_simplexml($row);
            $node->parentNode->removeChild($node);
        }
        
        return $this->save();    
    }
    
    
    /**
    * get database tables
	* 
    * @return array $tables
    */
    public function getTables()
    {
        $rows = $this->xml->xpath('//database');
                
        if (empty($rows)) {
            return [];
        }
		
	$tables = [];
        
        foreach ($rows[0] as $table) {
            $tables[] = $table->getName();
        }
        
        return $tables;    
    }
    
    
    /**
    * add table column
    * 
    * @param string $name
    * @param string $value
    */
    public function addColumn($name, $value = '')
    {
        if (!is_null($this->table)) {

            foreach ($this->xml->xpath('//database/' . $this->table . '/row') as $row) {
                if(isset($row->$name)) continue;
                $row->addChild($name, $value);    
            }
            
            static::clearCache();
            
            return $this->save();  
 
        } else {
            trigger_error('Can\'t add column, table not selected');
        }
            
    }
    
    
	/**
	* remove column from table
	* 
	* @param string $name
	*/
    public function removeColumn($name)
    {  
        $table = $this->table;
        
        foreach($this->xml->$table as $row) {
            foreach($row as $i => $column) {
                unset($column->$name);
            } 
        }
                
        return $this->save();
    }
    
    
	/**
	* get table columns
	* 
	* @param string $table
	* @return array	$columns 
	*/
    public function getColumns($table = null)
    {

        $table = is_null($table) ? $this->table : $table;
        
        $rows = $this->xml->xpath('//database/' . $table . '/row[position()=1]');
        
        if (empty($rows)) {
            return [];
        }

	$columns = [];
        
        foreach($rows[0] as $column) {
            $columns[] = $column->getName();
        }
        
        return $columns;
    }
    
    
	/**
	* select table
	* 
	* @param string $table
	* @return xmlDb
	*/
    public function from($table)
    {
        $this->table = trim($table);
	    
        return $this;  
    }
	
	
    /**
    * from() alias;
    * 
    * @param string $table
    * @return xmlDb
    */
    public function in($table)
    {
        return $this->from($table);
    }
    
    
    /**
    * join table
    * 
    * @param string $table
    * @param string $primary_key
    * @param string $foreign_key
    * @return xmlDb 
    */
    public function join($table, $primary_key, $foreign_key)
    {
        $this->join_table  = $table; 
        $this->primary_key = $primary_key;
        $this->foreign_key = $foreign_key;  
        
        // insert primary_key into select columns
        array_push($this->columns, $primary_key); 
    
        return $this;
    }
    
    
    /**
    * selct columns
    * 
    * @param string $select
    * @return xmlDb
    */
    public function select($select)
    {
	$select = trim($select);

        if ($select != '*') {
            if (strpos($select, ',')) {
                $columns = explode(',', $select);
                $columns = array_map('trim', $columns);
            } else {
                $columns[] = $select;
            }
        } else {
            $columns = $this->getColumns();
            
            if ($this->join_table) {
                $columns = array_unique(array_merge($columns, $this->getColumns($this->join_table)));
            }
        }

        $this->columns = $columns;
    
        return $this;    
    }
    
    
    /**
    * xpath query
    * 
    * @param string $column
    * @param string $value
    * @param string $comparison_operator
    * @param string $logical_operator
    * @return xmlDb
    */
    public function where($column, $value, $comparison_operator = '=', $logical_operator = 'and')
    {
	switch (strtolower($comparison_operator)) {

	    case 'contains':

	        /**
	        * to-do: case insensitive
	        */
			
	        $comparison_operator = is_null($this->query) ? '' : $logical_operator;

		$this->query .= sprintf('%s contains(%s, "%s")', $comparison_operator, $column, $value);
			
		break;

            default:
			
		if(!is_null($this->query)) {		
		    $column = " {$logical_operator} " . $column;
		}
				
		$this->query .= sprintf('%s %s "%s"', $column, $comparison_operator, $value);
				
	}
         
        return $this;   
    }
	
    /**
    * or xpath query
    * 
    * @param string $column
    * @param string $value
    * @param string $comparison_operator
    * @return xmlDb
    */
    public function orWhere($column, $value, $comparison_operator = '=')
    {
	if (is_null($this->query)) {
	    trigger_error('Where clause missing');		
	}

	return $this->where($column, $value, $comparison_operator, ' or');
    }
	
    /**
    * get xpath query
    * 
    */
    public function getQuery()
    {
	return is_null($this->query) ? '' : '[' . $this->query . ']';
    }
    
    
    /**
    * bind placholder value
    * 
    * @param string $name
    * @param string $value
	* @return xmlDb
    */
    public function bind($name, $value)
    {
        if (strpos($this->query, ':' . $name)) {
            $this->query = str_replace(':' . $name, '"' . $value . '"', $this->query);
        } else {
            $this->bind[$name] = $value;
        }
           
        return $this;
    }
    
    
    /**
    * insert data
    * 
    * @param array $data
    */
    public function insert($data = [])
    {
        if (!empty($this->bind)) {
            $data = array_merge($data, $this->bind);
        }

        $columns = $this->getColumns();

        // first insert - no columns
        if (empty($columns)) {

            // add id if not set
            if (!array_key_exists('id', $data)) {
                $this->addColumn('id', 1);    
            }
            
            foreach ($data as $name => $value) {
                $this->addColumn($name, $value);
            }
            
            return $this;
        }

        $row = $this->addRow();

        foreach ($columns as $column) {

            if ($column == 'id') {
                $row->addChild('id', $this->lastId() + 1); continue;
            }
            
            $value = isset($data[$column]) ? $data[$column] : '';
            $row->addChild($column, $value); 

        }
        
	return $this->clear()->save();
   
    }
    
    
    /**
    * get row
    * 
    * @return object
    */
    public function getRow()
    {
        return $this->get(1);   
    }
    
    
    /**
    * get all rows
    * 
    * @return object array
    */
    public function getAll()
    {
        return $this->get();
    }
	
	/**
    * get result
    */
    private function get($results = 0)
    {               
        $data = $columns = [];  
  
  	// check cache
        if (file_exists($cache_file = dirname(__FILE__) . '/data/' . md5($this->table . $this->join_table . $this->query . $this->limit) . '.cache') && $this->cache) {
            // load cache
            $data = unserialize(file_get_contents($cache_file));

        } else {
			
	    // if columns are not set explicitly, get them all
            if (empty($this->columns)) {
                $this->columns = $this->getColumns($this->table);    
            } 
            
            // if we use join, get columns of join table
            if (!is_null($this->join_table)) {
                $jtable_columns = $this->getColumns($this->join_table);    
            } 
            
            foreach ($this->xml->xpath('//database/' . $this->table . '/row' . $this->getQuery()) as $i => $row) {
				
                foreach ($row->children() as $column) {  
        
                    if (in_array($column->getName(), $this->columns)) {

                        // join
                        if (!is_null($this->join_table)) {

                            if ($column->getName() == $this->primary_key) {

                                $jtable = $this->xml->xpath('//database/' . $this->join_table . '/row[' . $this->foreign_key . ' = ' . (string) $column . ']');
                                                                
                                if (!empty($jtable)) {

                                    foreach ($jtable[0] as $jcolumn) { 
 
                                        if (in_array($jcolumn->getName(), $this->columns)) {

                                            if($jcolumn->getName() == 'id') continue;

                                            $columns[$jcolumn->getName()] = (string) $jcolumn;
                                        }                                  
                                    }

                                } else {

                                    // fill empty values
                                    foreach ($jtable_columns as $name) {

                                        if (in_array($name, $this->columns)) {
                                            if($name == 'id') continue;
                                            $columns[$name] = ''; 
                                        } 
   
                                    }
                                    
                                } 

                            }               
                        }
                        
                        $columns[$column->getName()] = (string) $column;   
                    }
                    
                }

                $data[] = (object) $columns;
                
                // check limit
                if(++$i == $this->limit) break;
            }  
            
            // save cache
	    if ($this->cache) {
		file_put_contents($cache_file, serialize($data));  
	    }
        }
        
        // sort result
        if (!empty($this->sort)) {
            $data = $this->sortArray($data, $this->sort[0], $this->sort[1]);           
        }  
        
        // if there is no results return false
        if(count($data) == 0) {
            return false;
        }
        
        // return one row
        if ($results == 1) {
            return $data[0];
        }

	$this->clear();  
        
        // return all rows
        return $data;
            
    }
         
    
    /**
    * update rows
    *  
    * @param array $data
    */
    public function update($data = [])
    {        
        if (!empty($this->bind)) {
            $data = array_merge($this->bind, $data);
        }
        
        foreach ($this->xml->xpath('//database/' . $this->table . '/row' . $this->getQuery()) as $i => $row) {

            foreach ($row->children() as $column) {

                if(array_key_exists($column->getName(), $data)) {
                    $dom = dom_import_simplexml($column);
                    $dom->nodeValue = $data[$column->getName()];  
                }    
            }
            
            if (++$i == $this->limit) break;
            
            $this->affected_rows++; 
        }

	return $this->clear()->save();    
           
    }
    
    
    /**
    * delete rows
    * 
    */
    public function delete()
    {
    
        foreach ($this->xml->xpath('//database/' . $this->table . '/row' . $this->getQuery()) as $i => $row) {

            $node = dom_import_simplexml($row);
            $node->parentNode->removeChild($node);
            
            if(++$i == $this->limit) break;
            
            $this->affected_rows++; 
        }

        return $this->clear()->save();
    
    }
    
        
    /**
    * order by column asc or desc
    * 
    * @param string $column
    * @param string $order
    * @return xmlDb
    */
    public function orderBy($column, $order = 'asc')
    {
	$direction = (strtolower($order) == 'desc') ? SORT_DESC : SORT_ASC;
        $this->sort = [$column, $direction];
        
        return $this;   
    }
    
    
    /**
    * limit result
    * 
    * @param int $rows
	* @return xmlDb
    */
    public function limit($limit)
    {
        $this->limit = intval($limit);
	    
        return $this; 
    }
    
    
    /**
    * get last insert id 
    * 
    */
    public function lastId()
    {
        $rows = $this->xml->xpath('//database/' . $this->table . '/row');

	return count($rows) - 1;

	/**
	* row[last()] Not working, hm...

	$row = $this->xml->xpath('//database/' . $this->table . '/row[last()]');

        if (isset($row[0]->id)) {
            return $row[0]->id; 
        }
        
        return 1;   

	*/
    }
    
    
    /**
    * get num of updated or deleted rows
    * 
    * @return int $num
    */
    public function affectedRows()
    {
        $num = $this->affected_rows;
        $this->affected_rows = 0;
        
        return $num;
    }
    

    /**
    * add row to selected table
    */
    private function addRow()
    {
        $table = $this->xml->xpath('//database/' . $this->table);
        return $table[0]->addChild('row');    
    }
    
    
    /**
    * save xml document
    */
    private function save()
    {        
	static::clearCache();

        return $this->xml->asXML($this->db);   
    }
    
    
    /**
    * clear data
    */
    private function clear()
    {
	$this->table         = null;
	$this->query         = null;
	$this->bind          = [];
	$this->columns       = [];
        $this->sort          = [];
	$this->join_table    = null;
	$this->primary_key   = null;
	$this->foreign_key   = null;
        $this->limit         = 0;

	return $this;
    }
    
    
    /**
    * sort data
    * 
    * @param array $data
    * @param string $column
    * @param string $dir
    */
    private function sortArray($data, $column, $dir)
    {  
        $sort = [];
        
        foreach ($data as $key => $row) {
            if (isset($row->$column)) {
                $sort[$key] = $row->$column;
            }
        }

        if (!empty($sort)) {
            array_multisort($sort, $dir, $data);    
        }

        return $data;
    }
    
    /**
    * delete cache files
    */
    public static function clearCache()
    {
	foreach (glob(dirname(__FILE__) . '/data/*.cache') as $file) {
	    unlink($file);
	}
    }
}
