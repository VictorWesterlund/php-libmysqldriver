# php-libmysqldriver

This library provides abstractions for parameter binding and result retrieval on MySQL(-like) databases in PHP. It is built on top of the PHP [`MySQL Improved`](https://www.php.net/manual/en/book.mysqli.php) extension.

## Install with Composer

```
composer require victorwesterlund/libmysqldriver
```

```php
use libmysqldriver/MySQL;
```

## Usage

Connect to a MySQL database

```php
use libysqldriver/MySQL;

$db = new MySQL(
  "localhost:3306",
  "username",
  "password",
  "database"
);
```

Return matching rows from query (array of arrays)

```php
$sql = "SELECT foo FROM table WHERE bar = ? AND biz = ?;

$response = $db->return_array($sql, [
  "parameter_1",
  "parameter_2
];

// Example $response with two matching rows: [["hello"],["world"]]
```

Return boolean if query matched at least one row, or if != `SELECT` query was sucessful

```php
$sql = "INSERT INTO table (foo, bar) VALUES (?, ?);

$response = $db->return_bool($sql, [
  "baz",
  "qux"
];

// Example $response if sucessful: true
