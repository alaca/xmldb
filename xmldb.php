<?php
/**
 * PHP XML DB
 * 
 * Store and retrieve data records in XML files
 * 
 * @copyright 2015
 * @author Ante Laca <ante.laca@gmail.com>
 * 
 */
class xmlDb
{
    private static $instance = array();
    private $fh = null;
    private $file = null;
    private $xml = null;
    private $query = null;
    private $bind = array();
    private $limit = 0;
    private $table = null;
    private $columns = array();
    private $sort = null;
    private $join_table = null;
    private $primary_key = null;
    private $foreign_key = null;
    private $affected_rows = 0;
    
    
    /**
    * constructor
    */
    private function __construct($database)
    {        
        // in use database file
        $this->in_use = dirname(__FILE__) . '/data/' . $database . '.lock';
        
        // if file exists that means the db file is in use, so we wait - trying to prevent race condition
        $i = 20;
        while(true == file_exists($this->in_use))
        {
            usleep($i += 20); 
        }
        
        // create in use file, we are using this db now  
        $i = 20;  
        while(false == $this->fh = @fopen($this->in_use, 'w'))
        {
            usleep($i += 20);
        }
        
        // wait for file lock
        $i = 20;     
        while(false == flock($this->fh, LOCK_EX))
        {
            usleep($i += 20);
        }
        
        // register shutdown 
        register_shutdown_function(array($this, '__unlock')); 
        
        
        // in use file is created and locked, now we can work with database
        
        // db file
        $this->file = dirname(__FILE__) . '/data/' . $database . '.xml';
        
        if(false == file_exists($this->file))
        {
            // if db file not exists, create db file with db schema
            file_put_contents($this->file, '<?xml version="1.0" encoding="utf-8"?><database></database>'); 
            
            // set permissions on db file
            xmlDb::chmodDatabase($database);  
        }
        
        libxml_use_internal_errors(true);
        
        // load xml
        try
        {
            $this->xml = new SimpleXMLElement(file_get_contents($this->file));
        } 
        catch(Exception $e)
        {
            exit('Error: ' . $e->getMessage());
        } 
    }
    
    
    /**
    * unlock and delete in use file
    * 
    */
    public function __unlock()
    {
        flock($this->fh, LOCK_UN);
        fclose($this->fh); 
        @unlink($this->in_use);   
    }
    
    
    /**
    * connect to database
    * 
    * @param string $database
    */
    public static function connect($database)
    {
        if(false == isset(self::$instance[$database]))
        {
            self::$instance[$database] = new self($database);
        }  
        
        return self::$instance[$database];
    }
        
    
    /**
    * create database backup
    * 
    */
    public function backup()
    {
        if(false == copy($this->file, $this->file . '.bak'))
        {
            exit('Error: can\'t create backup');    
        }   
         
        return $this;
    }
    
    
    /**
    * restore database backup
    * 
    */
    public function restore()
    {
        if(file_exists($this->file . '.bak'))
        {
            if(false == copy($this->file . '.bak', $this->file))
            {
                exit('Error: can\'t restore backup');
            }
            
            self::_clearCache();     
        }

        return $this;
    }

    
    /**
    * delete databse
    *  
    * @param string $database
    */
    public static function deleteDatabase($database)
    {
        $file = dirname(__FILE__) . '/data/' . $database . '.xml';
        
        if(file_exists($file))
        {
            self::_clearCache();
            
            return unlink($file);  
        }
        
        return false; 
    }
    
    
    /**
    * change permissions of database file
    * 
    * @param string $database
    * @param int $permissions
    */
    public static function chmodDatabase($database, $permissions = 0644)
    {
        $file = dirname(__FILE__) . '/data/' . $database . '.xml';
        
        if(file_exists($file))
        {
            return chmod($file, $permissions); 
        }
        
        return false;    
    }
    
    
    /**
    * get permissions of database file 
    * 
    * @param string $database
    */
    public static function getDatabasePerms($database)
    {
        return substr(decoct(fileperms(dirname(__FILE__) . '/data/' . $database . '.xml')), 2);
    }
    
    
    /**
    * add table
    * 
    * @param string $name
    */
    public function addTable($name)
    {     
        $table = $this->xml->xpath($name);
        
        if(empty($table))
        {
            $table = $this->xml->addChild($name);
            $table->addChild('row');
            
            self::_clearCache();

            return $this->_save();     
        }           
    }
    
    
    /**
    * remove table
    * 
    * @param string $name
    */
    public function removeTable($name)
    {
        foreach($this->xml->xpath('//database/' . $name) as $row)
        { 
            $node = dom_import_simplexml($row);
            $node->parentNode->removeChild($node);
        }
        
        self::_clearCache();
        
        return $this->_save();    
    }
    
    
    /**
    * get database tables
    * 
    */
    public function getTables()
    {
        $tables = array();
        
        $rows = $this->xml->xpath('//database');
                
        if(empty($rows))
        {
            return $tables;
        }
        
        foreach($rows[0] as $table)
        {
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
        if(false == is_null($this->table))
        {
            $rows = $this->xml->xpath('//database/' . $this->table . '/row');
            
            foreach($rows as $row)
            {
                if(isset($row->$name)) continue;
                $row->addChild($name, $value);    
            }
            
            self::_clearCache();
            
            return $this->_save();   
        } 
        else
        {
            exit('Error: can\'t add column, table not selected');
        }
            
    }
    
    
    /**
    * remove column
    * 
    * @param string $name
    */
    public function removeColumn($name)
    {  
        $table = $this->table;
        
        foreach($this->xml->$table as $row)
        {
            foreach($row as $k => $column)
            {
                unset($column->$name);
            } 
        }
        
        self::_clearCache();
        
        return $this->_save();
          
    }
    
    
    /**
    * get columns
    * 
    * @param string $table
    */
    public function getColumns($table = null)
    {
        $table = is_null($table) ? $this->table : $table;
                
        $columns = array();
        
        $rows = $this->xml->xpath('//database/' . $table . '/row[position()=1]');
        
        if(empty($rows))
        {
            return $columns;
        }
        
        foreach($rows[0] as $column)
        {
            $columns[] = $column->getName();
        }
        
        return $columns;
    }
    
    
    /**
    * select table
    * 
    * @param string $table
    */
    public function table($table)
    {
        $this->table = trim($table);
        
        return $this;  
    }
    
    
    /**
    * join table
    * 
    * @param string $table
    * @param string $primary_key
    * @param string $foreign_key
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
    * select columns
    * 
    * @param string $columns
    */
    public function select($columns)
    {
        if($columns != '*')
        {
            if(strpos($columns, ','))
            {
                $_columns = explode(',', $columns);
                $_columns = array_map('trim', $_columns);
            }
            else
            {
                $_columns[] = $columns;
            }
        }
        else
        {
            $_columns = $this->getColumns();
            
            if($this->join_table)
            {
                $_columns = array_unique(array_merge($_columns, $this->getColumns($this->join_table)));
            }
        }

        $this->columns = $_columns;
    
        return $this;    
    }
    
    
    /**
    * set xpath query
    *  
    * @param string $query
    */
    public function where($query)
    {
        $this->query = '[' . $query . ']';
         
        return $this;   
    }
    
    
    /**
    * bind placholder value
    * 
    * @param string $name
    * @param string $value
    */
    public function bind($name, $value)
    {
        if(strpos($this->query, ':' . $name))
        {
            $this->query = str_replace(':' . $name, '"' . $value . '"', $this->query);
        }
        else
        {
            $this->bind[$name] = $value;
        }
           
        return $this;
    }
    
    
    /**
    * insert data
    * 
    * @param array $data
    */
    public function insert($data = array())
    {
        if(false == empty($this->bind))
        {
            $data = array_merge($data, $this->bind);
        }
        
        $columns = $this->getColumns();
        
        // first insert - no columns
        if(empty($columns))
        {
            // add id if not set
            if(false == array_key_exists('id', $data))
            {
                $this->addColumn('id', 1);    
            }
            
            foreach($data as $name => $value)
            {
                $this->addColumn($name, $value);
            }
            
            return $this;
        }
        
        $lastId = $this->lastId() + 1;
            
        $row = $this->_addRow();
        
        foreach($columns as $column)
        {
            if($column == 'id')
            {
                $row->addChild('id', $lastId); continue;
            }
            
            $value = isset($data[$column]) ? $data[$column] : '';
            $row->addChild($column, $value); 
        }
        
        $this->_clear();
        self::_clearCache();
        
        return $this->_save();    
    }
    
    
    /**
    * get row
    * 
    * @return object
    */
    public function getRow()
    {
        return $this->_get(true);   
    }
    
    
    /**
    * get all rows
    * 
    * @return array
    */
    public function getAll()
    {
        return $this->_get(false);
    }
         
    
    /**
    * update rows
    *  
    * @param array $data
    */
    public function update($data = array())
    {        
        if(false == empty($this->bind))
        {
            $data = array_merge($this->bind, $data);
        }
        
        $i = 1;
        
        foreach($this->xml->xpath('//database/' . $this->table . '/row' . $this->query) as $row)
        { 
            foreach($row->children() as $column)
            {
                if(array_key_exists($column->getName(), $data))
                {
                    $dom = dom_import_simplexml($column);
                    $dom->nodeValue = $data[$column->getName()];  
                }    
            }
            
            if($i == $this->limit) break; $i++;
            
            $this->affected_rows++; 
        }
        
        $this->_clear(); 
        self::_clearCache();
        
        return $this->_save();    
           
    }
    
    
    /**
    * delete rows
    * 
    */
    public function delete()
    {
        $i = 1;
               
        foreach($this->xml->xpath('//database/' . $this->table . '/row' . $this->query) as $row)
        { 
            $node = dom_import_simplexml($row);
            $node->parentNode->removeChild($node);
            
            if($i == $this->limit) break; $i++;
            
            $this->affected_rows++; 
        }
        
        $this->_clear();
        self::_clearCache(); 
        
        return $this->_save();    
    }
    
        
    /**
    * order by column asc or desc
    * 
    * @param string $column
    * @param string $order
    */
    public function orderBy($column, $order = 'asc')
    {
        switch($order)
        {
            case 'desc':
                
                $order = SORT_DESC;
                
            break;
            default:
            
                $order = SORT_ASC;
        }
        
        $this->sort = array($column, $order);
        
        return $this;   
    }
    
    
    /**
    * limit result
    * 
    * @param int $rows
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
        $result = $this->xml->xpath('//database/' . $this->table . '/row[last()]');
        
        if(isset($result[0]->id))
        {
            return intval($result[0]->id); 
        }
        
        return 1;   
    }
    
    
    /**
    * get num of updated or deleted rows
    * 
    */
    public function affectedRows()
    {
        $num = $this->affected_rows;
        $this->affected_rows = 0;
        
        return $num;
    }
    
    /**
    * get result
    * 
    */
    private function _get($get_one)
    {               
        $data    = array();
        $columns = array();  
  
        // cache file
        $cache_file = dirname(__FILE__) . '/data/' . md5($this->table . $this->join_table . $this->query . $this->limit) . '.cache';

        if(file_exists($cache_file))
        {
            // load cache
            $data = unserialize(file_get_contents($cache_file));
        }
        else
        {
            // set limit for getRow()
            if($get_one)
            {
                $this->limit = 1;
            }
            
            // if we use join, get columns of join table
            if(false == is_null($this->join_table))
            {
                $jtable_columns = $this->getColumns($this->join_table);    
            } 
            
            $i = 1;
            
            foreach($this->xml->xpath('//database/' . $this->table . '/row' . $this->query) as $row)
            {
                foreach($row->children() as $column)
                {          
                    if(in_array($column->getName(), $this->columns))
                    {
                        // join
                        if(false == is_null($this->join_table))
                        {
                            if($column->getName() == $this->primary_key)
                            {
                                $jtable = $this->xml->xpath('//database/' . $this->join_table . '/row[' . $this->foreign_key . ' = ' . (string) $column . ']');
                                                                
                                if(false == empty($jtable))
                                {
                                    foreach($jtable[0] as $jcolumn)
                                    {  
                                        if(in_array($jcolumn->getName(), $this->columns))
                                        {
                                            if($jcolumn->getName() == 'id') continue;

                                            $columns[$jcolumn->getName()] = (string) $jcolumn;
                                        }                                  
                                    }
                                }
                                else
                                {
                                    // fill empty values
                                    foreach($jtable_columns as $c)
                                    {
                                        if(in_array($c, $this->columns))
                                        {
                                            if($c == 'id') continue;
            
                                            $columns[$c] = ''; 
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
                if($i == $this->limit) break; $i++;
            }  
            
            // save cache
            file_put_contents($cache_file, serialize($data));  
        }
        
        // sort result
        if(is_array($this->sort))
        {
            list($column, $sort) = $this->sort;
            
            $data = $this->_sortBy($data, $column, $sort);           
        }  
        
        $this->_clear();  
        
        // if there is no results return false
        if(0 == count($data)) 
        {
            return false;
        }
        
        // return one row
        if($get_one)
        {
            return $data[0];
        }
        
        // return all rows
        return $data;
            
    }
    

    /**
    * add row to table
    * 
    */
    private function _addRow()
    {
        $table = $this->xml->xpath('//database/' . $this->table);
        return $table[0]->addChild('row');    
    }
    
    
    /**
    * save document
    * 
    */
    private function _save()
    {
        return $this->xml->asXML($this->file);   
    }
    
    
    /**
    * clear data
    * 
    */
    private function _clear()
    {
        $this->table       = null;
        $this->query       = null;
        $this->limit       = 0;
        $this->sort        = null;
        $this->join_table  = null;
        $this->primary_key = null;
        $this->foreign_key = null;
        $this->bind        = array();
    }
    
    
    /**
    * sort data
    * 
    * @param array $data
    * @param string $column
    * @param string $dir
    */
    private function _sortBy($data, $column, $dir)
    {  
        $sort = array();
        
        foreach($data as $key => $row) 
        {
            if(isset($row->$column))
            {
                $sort[$key] = $row->$column;
            }
        }

        if(false == empty($sort))
        {
            array_multisort($sort, $dir, $data);    
        }

        return $data;
    }
    
    /**
    * delete cache files
    * 
    */
    private static function _clearCache()
    {
        foreach(glob(dirname(__FILE__) . '/data/*.cache') as $file)
        {
            unlink($file);
        }
    }
}
