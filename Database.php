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

class Database {
    /**
     * Static instance of self
     *
     * @var Database
     */
    protected static $_instance;

    private $pdo;
    private $stmt;
    protected $_query;
    protected $_lastQuery;
    protected $_queryOptions = array();
    protected $_join = array();
    protected $_joinAnd = array();
    protected $_where = array();
    protected $_having = array();
    protected $_orderBy = array();
    protected $_groupBy = array();
    protected $_bindParams = array();
    protected $_updateColumns = null;
    protected $_nestJoin = false;
    protected $_forUpdate = false;
    protected $_lockInShareMode = false;
    protected $_tableName = '';
    protected $_lastInsertId = null;
    protected $_mapKey = null;
    protected $_transaction_in_progress = false;
    protected $_fetchMode = PDO::FETCH_ASSOC;
    public $count = 0;
    public $totalCount = 0;
    public static $prefix = '';
    public $pageLimit = 20;
    public $totalPages = 0;

    public function __construct() {
        try {
            $this->pdo = new PDO('sqlite:' . DB_PATH);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$_instance = $this;
        } catch (PDOException $e) {
            die("Failed to connect to the database: " . $e->getMessage());
        }
    }

    /**
     * A method of returning the static instance to allow access to the
     * instantiated object from within another class.
     *
     * @return Database Returns the current instance.
     */
    public static function getInstance() {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Reset states after an execution
     *
     * @return Database Returns the current instance.
     */
    protected function reset() {
        $this->_where = array();
        $this->_having = array();
        $this->_join = array();
        $this->_joinAnd = array();
        $this->_orderBy = array();
        $this->_groupBy = array();
        $this->_bindParams = array();
        $this->_query = null;
        $this->_queryOptions = array();
        $this->_nestJoin = false;
        $this->_forUpdate = false;
        $this->_lockInShareMode = false;
        $this->_tableName = '';
        $this->_lastInsertId = null;
        $this->_updateColumns = null;
        $this->_mapKey = null;
        $this->count = 0;
        $this->totalCount = 0;
        return $this;
    }

    /**
     * Method to set a prefix
     *
     * @param string $prefix Contains a table prefix
     *
     * @return Database
     */
    public function setPrefix($prefix) {
        self::$prefix = $prefix;
        return $this;
    }
    public function createTable($table, $columns) {
        /**
         * Создает таблицу в базе данных.
         * @param string $table Название таблицы
         * @param array $columns Ассоциативный массив ['column_name' => 'data_type']
         * @return bool
         * 
         * Возможные значения data_type:
         * - INTEGER: Целочисленный тип, может использоваться с PRIMARY KEY AUTOINCREMENT
         * - TEXT: Текстовое поле
         * - REAL: Число с плавающей точкой
         * - BLOB: Двоичные данные
         * - NUMERIC: Числовой тип (может содержать дату/время)
         * 
         * Допустимые свойства полей:
         * - PRIMARY KEY: Уникальный идентификатор
         * - AUTOINCREMENT: Автоматическое увеличение значения (только с INTEGER PRIMARY KEY)
         * - NOT NULL: Запрещает NULL-значения
         * - UNIQUE: Требует уникальности значений
         * - DEFAULT value: Устанавливает значение по умолчанию
         */
        $columns_sql = [];
        foreach ($columns as $name => $type) {
            $columns_sql[] = "$name $type";
        }
        $columns_sql[] = "created_at INTEGER NOT NULL";
        $columns_sql[] = "updated_at INTEGER NOT NULL";
        $columns_sql[] = "deleted_at INTEGER DEFAULT NULL";

        $sql = "CREATE TABLE IF NOT EXISTS $table (" . implode(", ", $columns_sql) . ")";
        try {
            $this->rawQuery($sql);
            return true;
        } catch (e) {
            return false;
        }
        
    }
    public function dropTable($table) {
        /**
         * Удаляет таблицу из базы данных.
         * @param string $table Название таблицы
         * @return bool
         */
        $sql = "DROP TABLE IF EXISTS $table";
        try {
            $this->rawQuery($sql);
            return true;
        } catch (e) {
            return false;
        }
    }
    public function addColumn($table, $column, $type) {
        /**
         * Добавляет новую колонку в существующую таблицу.
         * @param string $table Название таблицы
         * @param string $column Название колонки
         * @param string $type Тип данных колонки (например, TEXT, INTEGER и т. д.)
         * @return bool
         */
        $sql = "ALTER TABLE $table ADD COLUMN $column $type";
        $this->rawQuery($sql);
        return true;
      
    }
    public function dd($data,$exit = false){
        echo '<pre>';
        if($exit){
            exit(var_dump($data));
        }
        var_dump($data);
        echo '</pre>';
    }
    public function dropColumn($table, $column) {
        /**
         * Удаляет колонку из таблицы (требуется обходной путь, так как SQLite не поддерживает удаление колонок напрямую).
         * @param string $table Название таблицы
         * @param string $column Название удаляемой колонки
         * @return bool
         */

            // Получаем список существующих колонок
            $stmt = $this->rawQuery("PRAGMA table_info($table)");

            // // Формируем новый список колонок, исключая удаляемый
            $newColumns = array_filter($stmt, function($col) use ($column) {
                return $col['name'] !== $column;
            });
 
            
            if (count($newColumns) === count($stmt)) {
                throw new Exception("Column not found: $column");
            }
            
            $columnNames = array_map(fn($col) => $col['name'] . ' ' . $col['type'], $newColumns);
            $columnNamesList = implode(", ", array_map(fn($col) => $col['name'], $newColumns));
            
            // Создаем временную таблицу
            $tempTable = $table . "_temp";
            $this->rawQuery("CREATE TABLE $tempTable ($columnNamesList)");

            // Копируем данные
            $this->rawQuery("INSERT INTO $tempTable SELECT $columnNamesList FROM $table");
            
            // Удаляем старую таблицу и переименовываем временную
            $this->dropTable($table);
            $this->rawQuery("ALTER TABLE $tempTable RENAME TO $table");
            
            return true;
       
    }
    /**
     * Execute raw SQL query.
     *
     * @param string $query      User-provided query to execute.
     * @param array  $bindParams Variables array to bind to the SQL statement.
     *
     * @return array Contains the returned rows from the query.
     */
    public function rawQuery($query, $bindParams = array()) {
        $this->_query = $query;
        $this->_bindParams = $bindParams;
        $stmt = $this->pdo->prepare($this->_query);
        $stmt->execute($this->_bindParams);
        $this->reset();
        return $stmt->fetchAll($this->_fetchMode);
    }

    /**
     * Get the value of a single column from a single row
     */
    public function getValue($tableName, $column, $limit = 1) {
        $res = $this->get($tableName, $limit, "{$column} AS retval");
        if (!$res) {
            return null;
        }
        if ($limit == 1) {
            return isset($res[0]["retval"]) ? $res[0]["retval"] : null;
        }
        $newRes = array();
        for ($i = 0; $i < $this->count; $i++) {
            $newRes[] = $res[$i]['retval'];
        }
        return $newRes;
    }

    /**
     * Build the SELECT query
     */
    public function get($tableName, $numRows = null, $columns = '*') {
        if (empty($columns)) {
            $columns = '*';
        }
        
        $column = is_array($columns) ? implode(', ', $columns) : $columns;
        $table = self::$prefix . $tableName;
        $this->_tableName = $table;
        $this->_query = "SELECT " . implode(' ', $this->_queryOptions) . ' ' .
                        $column . " FROM " . $table;
                        
        $this->_buildJoin();
        $this->_buildWhere();
        $this->_buildGroupBy();
        $this->_buildHaving();
        $this->_buildOrderBy();
        $this->_buildLimit($numRows);

        $stmt = $this->pdo->prepare($this->_query);
        $stmt->execute($this->_bindParams);
        $result = $stmt->fetchAll($this->_fetchMode);
        $this->count = $stmt->rowCount();
        $this->reset();
        return $result;
    }

    /**
     * Get one row
     */
    public function getOne($tableName, $columns = '*') {
        $res = $this->get($tableName, 1, $columns);
        return isset($res[0]) ? $res[0] : null;
    }

    /**
     * Insert method to add new row
     */
    public function insert($tableName, $insertData) {
        if (!is_array($insertData)) {
            return false;
        }
        $table = self::$prefix . $tableName;
        $columns = array_keys($insertData);
        $columns = array_merge($columns, ['created_at', 'updated_at']);
        $values = array_values($insertData);
        $values = array_merge(array_values($insertData), [time(), time()]);
        $params = array();
        $placeholders = array();

        foreach ($values as $value) {
            $placeholders[] = '?';
            $params[] = $value;
        }

        $this->_query = "INSERT INTO " . $table .
                        " (`" . implode('`, `', $columns) . "`) VALUES (" .
                        implode(', ', $placeholders) . ")";

        $stmt = $this->pdo->prepare($this->_query);
        $status = $stmt->execute($params);
        $this->reset();

        if ($status) {
            $this->_lastInsertId = $this->pdo->lastInsertId();
            return $this->_lastInsertId;
        } else {
            return false;
        }
    }
    public function toTime($timestamp, $format=false, $utc = 'EUROPE/MOSCOW') {
        date_default_timezone_set($utc);
        $updateFormat = !$format ? 'Y-m-d H:i:s' : $format;
        return date($updateFormat, $timestamp);
    }
    /**
     * Update method
     */
    public function update($tableName, $payload, $numRows = null) {
        if (!is_array($payload)) {
            return false;
        }
        $tableData = array_merge($payload, ['updated_at' => time()]);        
        $table = self::$prefix . $tableName;

        $this->_query = "UPDATE " . $table . " SET ";
        $params = array();

        foreach ($tableData as $column => $value) {
            $this->_query .= "`" . $column . "` = ?, ";
            $params[] = $value;
        }
      
        $this->_query = rtrim($this->_query, ', ');

        $this->_buildWhere();
        $this->_buildOrderBy();
        $this->_buildLimit($numRows);

        $params = array_merge($params, $this->_bindParams);

        $stmt = $this->pdo->prepare($this->_query);

        $result = $stmt->execute($params);
        $this->count = $stmt->rowCount();
        $this->reset();
        return $result;
    }

    /**
     * Delete method
     */
    public function delete($tableName, $numRows = null) {
        $table = self::$prefix . $tableName;
        $this->_query = "DELETE FROM " . $table;

        $this->_buildWhere();
        $this->_buildOrderBy();
        $this->_buildLimit($numRows);

        $stmt = $this->pdo->prepare($this->_query);
        $result = $stmt->execute($this->_bindParams);
        $this->count = $stmt->rowCount();
        $this->reset();
        return $result;
    }

    /**
     * Where method
     */
    public function where($whereProp, $whereValue = null, $operator = '=', $cond = 'AND') {
        if (count($this->_where) == 0) {
            $cond = '';
        }
        $this->_where[] = array($cond, $whereProp, $operator, $whereValue);
        return $this;
    }

    /**
     * Or Where method
     */
    public function orWhere($whereProp, $whereValue = null, $operator = '=') {
        return $this->where($whereProp, $whereValue, $operator, 'OR');
    }

    /**
     * Having method
     */
    public function having($havingProp, $havingValue = null, $operator = '=', $cond = 'AND') {
        if (count($this->_having) == 0) {
            $cond = '';
        }
        $this->_having[] = array($cond, $havingProp, $operator, $havingValue);
        return $this;
    }

    /**
     * Or Having method
     */
    public function orHaving($havingProp, $havingValue = null, $operator = '=') {
        return $this->having($havingProp, $havingValue, $operator, 'OR');
    }

    /**
     * Join method
     */
    public function join($joinTable, $joinCondition, $joinType = '') {
        $allowedTypes = array('LEFT', 'RIGHT', 'OUTER', 'INNER', 'LEFT OUTER', 'RIGHT OUTER', 'NATURAL');
        $joinType = strtoupper(trim($joinType));
        if ($joinType && !in_array($joinType, $allowedTypes)) {
            throw new Exception('Wrong JOIN type: ' . $joinType);
        }
        $this->_join[] = array($joinType, $joinTable, $joinCondition);
        return $this;
    }

    /**
     * Order By method
     */
    public function orderBy($orderByField, $orderbyDirection = "DESC") {
        $allowedDirection = array("ASC", "DESC");
        $orderbyDirection = strtoupper(trim($orderbyDirection));

        if (!in_array($orderbyDirection, $allowedDirection)) {
            throw new Exception('Wrong order direction: ' . $orderbyDirection);
        }

        $this->_orderBy[$orderByField] = $orderbyDirection;
        return $this;
    }

    /**
     * Group By method
     */
    public function groupBy($groupByField) {
        $this->_groupBy[] = $groupByField;
        return $this;
    }

    /**
     * Start Transaction
     */
    public function startTransaction() {
        $this->_transaction_in_progress = $this->pdo->beginTransaction();
        return $this;
    }

    /**
     * Commit Transaction
     */
    public function commit() {
        $this->_transaction_in_progress = false;
        return $this->pdo->commit();
    }

    /**
     * Rollback Transaction
     */
    public function rollback() {
        $this->_transaction_in_progress = false;
        return $this->pdo->rollBack();
    }

    /**
     * Get Last Insert ID
     */
    public function getInsertId() {
        return $this->_lastInsertId;
    }

    /**
     * Escape method
     */
    public function escape($str) {
        return substr($this->pdo->quote($str), 1, -1);
    }

    /**
     * Build Pair method
     */
    protected function _buildPair($operator, $value) {
        if (is_array($value)) {
            return '(' . implode(', ', $value) . ')';
        } else {
            return $operator . $value;
        }
    }

    /**
     * Build JOIN clause
     */
    protected function _buildJoin() {
        if (empty($this->_join)) {
            return;
        }

        foreach ($this->_join as $data) {
            list ($joinType, $joinTable, $joinCondition) = $data;

            if (is_object($joinTable)) {
                $joinStr = $this->_buildPair("", $joinTable);
            } else {
                $joinStr = $joinTable;
            }

            $this->_query .= " " . $joinType . " JOIN " . $joinStr .
                ' ON ' . $joinCondition;
        }
    }

    /**
     * Build WHERE clause
     */
    protected function _buildWhere() {
        if (empty($this->_where)) {
            return;
        }

        $this->_query .= ' WHERE ';
        $first = true;

        foreach ($this->_where as $cond) {
            list ($concat, $prop, $comp, $val) = $cond;

            if ($first) {
                $concat = '';
            }

            $this->_query .= " $concat $prop";

            if (is_array($val)) {
                if ($comp == 'IN' || $comp == 'NOT IN') {
                    $placeholders = implode(', ', array_fill(0, count($val), '?'));
                    $this->_query .= " $comp ($placeholders)";
                    $this->_bindParams = array_merge($this->_bindParams, $val);
                } elseif ($comp == 'BETWEEN') {
                    $this->_query .= " $comp ? AND ?";
                    $this->_bindParams = array_merge($this->_bindParams, $val);
                } else {
                    throw new Exception("Unsupported operator with array value: $comp");
                }
            } else {
                if ($comp == 'IS NULL' || $comp == 'IS NOT NULL') {
                    $this->_query .= " $comp";
                } else {
                    $this->_query .= " $comp ?";
                    $this->_bindParams[] = $val;
                }
            }

            $first = false;
        }
    }

    /**
     * Build HAVING clause
     */
    protected function _buildHaving() {
        if (empty($this->_having)) {
            return;
        }

        $this->_query .= ' HAVING ';
        $first = true;

        foreach ($this->_having as $cond) {
            list ($concat, $prop, $comp, $val) = $cond;

            if ($first) {
                $concat = '';
            }

            $this->_query .= " $concat $prop";

            if (is_array($val)) {
                if ($comp == 'IN' || $comp == 'NOT IN') {
                    $placeholders = implode(', ', array_fill(0, count($val), '?'));
                    $this->_query .= " $comp ($placeholders)";
                    $this->_bindParams = array_merge($this->_bindParams, $val);
                } elseif ($comp == 'BETWEEN') {
                    $this->_query .= " $comp ? AND ?";
                    $this->_bindParams = array_merge($this->_bindParams, $val);
                } else {
                    throw new Exception("Unsupported operator with array value: $comp");
                }
            } else {
                if ($comp == 'IS NULL' || $comp == 'IS NOT NULL') {
                    $this->_query .= " $comp";
                } else {
                    $this->_query .= " $comp ?";
                    $this->_bindParams[] = $val;
                }
            }

            $first = false;
        }
    }

    /**
     * Build GROUP BY clause
     */
    protected function _buildGroupBy() {
        if (empty($this->_groupBy)) {
            return;
        }

        $this->_query .= " GROUP BY " . implode(', ', $this->_groupBy);
    }

    /**
     * Build ORDER BY clause
     */
    protected function _buildOrderBy() {
        if (empty($this->_orderBy)) {
            return;
        }

        $order = array();
        foreach ($this->_orderBy as $field => $dir) {
            $order[] = "$field $dir";
        }

        $this->_query .= " ORDER BY " . implode(', ', $order);
    }

    /**
     * Build LIMIT clause
     */
    protected function _buildLimit($numRows) {
        if (isset($numRows)) {
            if (is_array($numRows)) {
                $this->_query .= ' LIMIT ' . (int) $numRows[1] . ' OFFSET ' . (int) $numRows[0];
            } else {
                $this->_query .= ' LIMIT ' . (int) $numRows;
            }
        }
    }

    /**
     * Return the last executed query
     */
    public function getLastQuery() {
        return $this->_query;
    }

    /**
     * Return the last error
     */
    public function getLastError() {
        $errorInfo = $this->pdo->errorInfo();
        return isset($errorInfo[2]) ? $errorInfo[2] : '';
    }

    /**
     * Pagination wrapper to get()
     *
     * @access public
     *
     * @param string       $table  The name of the database table to work with
     * @param int          $page   Page number
     * @param array|string $fields Array or comma separated list of fields to fetch
     *
     * @return array
     */
    public function paginate($table, $page, $fields = null) {
        $offset = $this->pageLimit * ($page - 1);
        $res = $this->withTotalCount()->get($table, array($offset, $this->pageLimit), $fields);
        $this->totalPages = ceil($this->totalCount / $this->pageLimit);
        return $res;
    }

    /**
     * Enable SQL_CALC_FOUND_ROWS in the get queries
     */
    public function withTotalCount() {
        // SQLite doesn't support SQL_CALC_FOUND_ROWS, need alternative
        // So, after executing the query, set totalCount = count of all records

        // For simplicity, we can use COUNT(*) to get total records
        // We'll implement this in get() method

        return $this;
    }

    /**
     * Map result to column value
     */
    public function map($idField) {
        $this->_mapKey = $idField;
        return $this;
    }

    /**
     * Helper function to determine data type
     */
    protected function _determineType($item) {
        switch (gettype($item)) {
            case 'NULL':
                return PDO::PARAM_NULL;
            case 'boolean':
                return PDO::PARAM_BOOL;
            case 'integer':
                return PDO::PARAM_INT;
            case 'double':
            case 'string':
            default:
                return PDO::PARAM_STR;
        }
    }

    /**
     * Helper to bind parameters
     */
    protected function _bindParams($stmt, $params) {
        foreach ($params as $key => $value) {
            $stmt->bindValue($key+1, $value, $this->_determineType($value));
        }
    }

    /**
     * Method to check if table exists
     */
    public function tableExists($tableName) {
        try {
            $result = $this->query("SELECT name FROM sqlite_master WHERE type='table' AND name=?", array($tableName));
            return count($result) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Function to fetch data
     */
    public function query($sql, $params = array()) {
        $this->_query = $sql;
        $stmt = $this->pdo->prepare($this->_query);
        $stmt->execute($params);
        return $stmt->fetchAll($this->_fetchMode);
    }

    /**
     * Function to get row count
     */
    public function getCount() {
        return $this->count;
    }
}
?>
