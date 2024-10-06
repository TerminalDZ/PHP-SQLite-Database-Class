<?php
/**
 * SqliteDb Class
 *
 * @category  Database Access
 * @package   SqliteDb
 * @author    Idriiss Boukmouche <boukmoucheidriss@gmail.com>
 * @copyright Copyright (c) 2024
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link      http://github.com/Terminaldz/PHP-Sqlite-Database-Class
 * @version   1.0.0
 */

require_once 'config.php';
class Database {
    private $pdo;
    private $stmt;
    private static $_instance;
    protected $_query;
    protected $_where = array();
    protected $_join = array(); 
    protected $_having = array();
    protected $_orderBy = array();
    protected $_groupBy = array();
    protected $_queryOptions = array();
    protected $_bindParams = array();
    public $count = 0;
    public $totalCount = 0;
    public static $prefix = '';

    public function __construct() {
        try {
            $this->pdo = new PDO('sqlite:' . DB_PATH);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Failed to connect to the database: " . $e->getMessage());
        }
    }

    public function getValue($tableName, $column, $numRows = null)
{
    $result = $this->get($tableName, $numRows, $column);
    
    if (is_array($result) && count($result) > 0) {
        return array_values($result[0])[0];
    }
    
    return null;
}

public function groupBy($columns) {
    if (is_array($columns)) {
        $this->_groupBy = array_merge($this->_groupBy, $columns);
    } else {
        $this->_groupBy[] = $columns;
    }
    return $this;
}


    public static function getInstance() {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function query($sql, $params = array()) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function join($table, $condition, $type = 'INNER') {
        $this->_join[] = "$type JOIN $table ON $condition";
        return $this;
    }

    public function where($whereProp, $whereValue = null, $operator = '=') {
        if (is_array($whereValue) && ($key = key($whereValue)) != "0") {
            $operator = $key;
            $whereValue = $whereValue[$key];
        }
        
        if (is_null($whereValue) && $operator == '=') {
            $operator = 'IS NULL';
        } elseif (is_null($whereValue)) {
            $operator = 'IS NOT NULL';
        }
        
        $this->_where[] = array($whereProp, $operator, $whereValue);
        return $this;
    }

    public function orWhere($whereProp, $whereValue = null, $operator = '=') {
        if (is_array($whereValue) && ($key = key($whereValue)) != "0") {
            $operator = $key;
            $whereValue = $whereValue[$key];
        }
        
        if (is_null($whereValue) && $operator == '=') {
            $operator = 'IS NULL';
        } elseif (is_null($whereValue)) {
            $operator = 'IS NOT NULL';
        }
        
        $this->_where[] = array($whereProp, $operator, $whereValue, 'OR');
        return $this;
    }

    public function get($tableName, $numRows = null, $columns = '*') {
        if (empty($columns)) {
            $columns = '*';
        }
        
        $column = is_array($columns) ? implode(', ', $columns) : $columns;
        
        $table = self::$prefix . $tableName;
        
        $this->_query = "SELECT " . $column . " FROM " . $table;
        
        // بناء جملة JOIN
        if (!empty($this->_join)) {
            $this->_query .= " " . implode(" ", $this->_join);
        }

        // بناء شرط WHERE
        if (!empty($this->_where)) {
            $this->_query .= " WHERE";
            $firstWhere = true;
            
            foreach ($this->_where as $where) {
                if (count($where) === 4) {
                    list($column, $operator, $value, $concat) = $where;
                } else {
                    list($column, $operator, $value) = $where;
                    $concat = 'AND';
                }
                
                if (!$firstWhere) {
                    $this->_query .= " $concat";
                }
                
                if ($operator == 'IN' || $operator == 'NOT IN') {
                    $this->_query .= " $column $operator (" . str_repeat('?,', count($value) - 1) . '?)';
                    $this->_bindParams = array_merge($this->_bindParams, $value);
                } else if ($operator == 'BETWEEN') {
                    $this->_query .= " $column $operator ? AND ?";
                    $this->_bindParams[] = $value[0];
                    $this->_bindParams[] = $value[1];
                } else if ($operator == 'IS NULL' || $operator == 'IS NOT NULL') {
                    $this->_query .= " $column $operator";
                } else {
                    $this->_query .= " $column $operator ?";
                    $this->_bindParams[] = $value;
                }
                
                $firstWhere = false;
            }
        }
        
        // إضافة LIMIT
        if ($numRows !== null) {
            $this->_query .= " LIMIT " . (int)$numRows;
        }
        
        $stmt = $this->pdo->prepare($this->_query);
        $stmt->execute($this->_bindParams);
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->reset();
        
        return $result;
    }

    public function getOne($tableName, $columns = '*') {
        $res = $this->get($tableName, 1, $columns);
        return isset($res[0]) ? $res[0] : null;
    }

    public function insert($tableName, $insertData) {
        if (!is_array($insertData)) {
            return false;
        }
        
        $table = self::$prefix . $tableName;
        $columns = array_keys($insertData);
        $values = array_values($insertData);
        $placeholders = str_repeat('?,', count($values) - 1) . '?';
        
        $this->_query = "INSERT INTO " . $table . 
                        " (`" . implode('`, `', $columns) . "`) VALUES (" . 
                        $placeholders . ")";
        
        $stmt = $this->pdo->prepare($this->_query);
        $stmt->execute($values);
        
        $this->reset();
        return $this->pdo->lastInsertId();
    }

    public function update($tableName, $tableData) {
        if (!is_array($tableData)) {
            return false;
        }
        
        $table = self::$prefix . $tableName;
        $this->_query = "UPDATE " . $table . " SET ";
        
        foreach ($tableData as $column => $value) {
            $this->_query .= "`" . $column . "` = ?, ";
            $this->_bindParams[] = $value;
        }
        
        $this->_query = rtrim($this->_query, ', ');
        
        if (!empty($this->_where)) {
            $this->_query .= " WHERE";
            $firstWhere = true;
            
            foreach ($this->_where as $where) {
                if (count($where) === 4) {
                    list($column, $operator, $value, $concat) = $where;
                } else {
                    list($column, $operator, $value) = $where;
                    $concat = 'AND';
                }
                
                if (!$firstWhere) {
                    $this->_query .= " $concat";
                }
                
                $this->_query .= " $column $operator ?";
                $this->_bindParams[] = $value;
                
                $firstWhere = false;
            }
        }
        
        $stmt = $this->pdo->prepare($this->_query);
        $result = $stmt->execute($this->_bindParams);
        
        $this->reset();
        return $result;
    }

    public function delete($tableName) {
        $table = self::$prefix . $tableName;
        $this->_query = "DELETE FROM " . $table;
        
        if (!empty($this->_where)) {
            $this->_query .= " WHERE";
            $firstWhere = true;
            
            foreach ($this->_where as $where) {
                if (count($where) === 4) {
                    list($column, $operator, $value, $concat) = $where;
                } else {
                    list($column, $operator, $value) = $where;
                    $concat = 'AND';
                }
                
                if (!$firstWhere) {
                    $this->_query .= " $concat";
                }
                
                $this->_query .= " $column $operator ?";
                $this->_bindParams[] = $value;
                
                $firstWhere = false;
            }
        }
        
        $stmt = $this->pdo->prepare($this->_query);
        $result = $stmt->execute($this->_bindParams);
        
        $this->reset();
        return $result;
    }

    public function setPrefix($prefix) {
        self::$prefix = $prefix;
        return $this;
    }

    private function reset() {
        $this->_where = array();
        $this->_bindParams = array();
        $this->_query = null;
        $this->_join = array(); 
    }
}
