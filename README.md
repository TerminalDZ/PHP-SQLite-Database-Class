# PHP SQLite Database Class

This is a highly reusable and efficient PHP database class designed for SQLite. The class is structured to simplify your interactions with SQLite databases, offering powerful methods for performing `CRUD` operations, joining tables, building complex queries, and more, with minimal configuration required. It also follows best practices such as the singleton design pattern for optimal performance.

## Key Features

- **Singleton Design Pattern**: Ensures a single database connection instance throughout the application lifecycle, improving resource management.
- **Flexible Query Building**: Supports complex SQL queries with options for `JOIN`, `WHERE`, `GROUP BY`, `HAVING`, and `ORDER BY` clauses.
- **Dynamic Conditions**: Easily combine `AND` and `OR` conditions in `WHERE` clauses.
- **Complete CRUD Functionality**: Methods for selecting, inserting, updating, and deleting records.
- **Extensible**: Easily adapt and extend the class for custom functionality.

## Requirements

- PHP 7.0 or higher.
- SQLite extension enabled in PHP.

## Installation

1. **Clone the Repository** or copy the `Database.php` file into your project.
2. **Enable SQLite**: Ensure that the SQLite extension is enabled in your PHP installation.
3. **Define the SQLite Database Path**: Specify the path to your SQLite database file in your project.

```php
define('DB_PATH', __DIR__ . '/your_database_file.sqlite');
```

## Usage Instructions

### 1. Establish a Database Connection

The class follows the singleton pattern to handle database connections efficiently.

```php
require_once 'Database.php';  // Include the Database class

$db = Database::getInstance();  // Create and reuse the same database instance
```

### 2. Select Data

Retrieve data using the `get()`, `getOne()`, or `getValue()` methods. You can also add `WHERE` conditions using the `where()` and `orWhere()` methods.

```php
// Get all rows from a table
$rows = $db->get('tableName');

// Get rows with a condition
$rows = $db->where('column', 'value')->get('tableName');

// Get a single value
$value = $db->getValue('tableName', 'column');

// Get one row
$row = $db->getOne('tableName');
```

### 3. Insert Data

Add a new record to the database using the `insert()` method.

```php
$data = [
    'column1' => 'value1',
    'column2' => 'value2'
];

$lastInsertId = $db->insert('tableName', $data);
```

### 4. Update Data

Update existing records by chaining `where()` with `update()`.

```php
$data = [
    'column1' => 'newValue',
    'column2' => 'newValue2'
];

$updated = $db->where('column', 'value')->update('tableName', $data);
```

### 5. Delete Data

Remove records by chaining the `where()` and `delete()` methods.

```php
$deleted = $db->where('column', 'value')->delete('tableName');
```

### 6. Join Tables

Combine data from multiple tables using the `join()` method.

```php
$rows = $db->join('otherTable', 'tableName.id = otherTable.foreign_id')->get('tableName');
```

### 7. Group By

Group query results using the `groupBy()` method.

```php
$rows = $db->groupBy('column')->get('tableName');
```

### 8. Reset Query

The query state is reset automatically after execution, but you can manually reset it with the `reset()` method.

```php
$db->reset();
```

## Full Example

Here is a complete example demonstrating the class's functionality, including inserting, selecting, updating, and deleting data.

```php
$db = Database::getInstance();

// Insert a new user
$newData = ['name' => 'John Doe', 'email' => 'john@example.com'];
$lastId = $db->insert('users', $newData);

// Retrieve all users
$users = $db->get('users');

// Update the user's email
$db->where('id', $lastId)->update('users', ['email' => 'newemail@example.com']);

// Delete the user
$db->where('id', $lastId)->delete('users');
```

## Contribution

Contributions to improve the class and add new features are always welcome! Feel free to submit pull requests or raise issues to make this class even more powerful and flexible.

---

This `Database` class is built with extensibility in mind. It serves as a robust starting point for SQLite-based projects, ensuring code reuse, maintainability, and simplicity. Whether you're developing small applications or larger systems, this class will seamlessly integrate with your workflow while providing flexibility for custom functionality.
