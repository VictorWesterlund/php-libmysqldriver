# php-libmysqldriver

This library provides abstraction methods for common operations on MySQL-like databases like `SELECT`, `UPDATE`, and `INSERT`.

This library is built on top of the PHP [`MySQL Improved`](https://www.php.net/manual/en/book.mysqli.php) extension.

## Install from composer

```
composer require victorwesterlund/libmysqldriver
```

```php
use libmysqldriver/MySQL;
```

----

`Example table name: beverages`
id|beverage_type|beverage_name|beverage_size
--|--|--|--
0|coffee|cappuccino|10
1|coffee|black|15
2|tea|green|10
3|tea|black|15

```php
use libmysqldriver\MySQL;

// Pass through: https://www.php.net/manual/en/mysqli.construct.php
$db = new MySQL($host, $user, $pass, $db);
```

# SELECT

Use `MySQL->get()` to retrieve columns from a database table

```php
$db->get(
  // Name of the database table
  string $table,
  // (Optional) array or string of column(s) names to SELECT
  array|string $columns,
  // (Optional) key, value array of column names and values to filter with WHERE <column> = <value>
  ?array $filter = null,
  // (Optional) max number of rows to return
  ?int $limit = null
): array|bool;
// Returns array of arrays for each row, or bool if $columns = null
```

### Example
```php
// (Optional) array of columns to return from table. Passing null will return a bool if rows were matched
$columns = ["beverage_name", "beverage_size"];

$beverages = $db->get("beverages", $columns);
// SELECT beverage_name, beverage_size FROM beverages
```
```
[
  [
    "beverage_name" => "cappuccino",
    "beverage_size" => 10
  ],
  [
    "beverage_name" => "black",
    "beverage_size" => 15
  ],
  // ...etc
]
```

## WHERE

```php
// (Optional) associative array of filters where "<column_name> = <value>"
$filter = ["beverage_type" => "coffee"];

$coffee = $db->get("beverages", $columns, $filter);
// SELECT beverage_name, beverage_size FROM beverages WHERE beverage_type = "coffee"
```
```php
[
  [
    "beverage_name" => "cappuccino",
    "beverage_size" => 10
  ],
  [
    "beverage_name" => "black",
    "beverage_size" => 15
  ]
]
```

## LIMIT

You can also pass an optional integer as the 4:th argument to `MySQL->get()` and `LIMIT` the rows to match

> **Note**
> Passing (int) `1` will flatten the returned array to two dimensions (k => v)

```php
$coffee = $db->get("beverages", $columns, $filter, 1);
// SELECT beverage_name, beverage_size FROM beverages WHERE beverage_type = "coffee" LIMIT 1
```
```php
[
  "beverage_name" => "cappuccino",
  "beverage_size" => 10
]
```

# INSERT

Use `MySQL->insert()` to append a new row to a database table

```php
$db->insert(
  // Name of the database table
  string $table,
  // Array of values to INSERT
  array $values
): bool
// Returns true if row was inserted
```

#### Example

```php
$db->insert("beverages", [
  null,
  "coffee",
  "latte",
  10
]);
// INSERT INTO beverages VALUES (null, "coffee", "latte", 10)
```
```
true
```

# UPDATE

Modify existing rows with `MySQL->update()`

```php
$db->get(
  // Name of the database table
  string $table,
  // Key, value array of column names and values to update
  array $fields,
  // (Optional) key, value array of column names and values to limit UPDATE to with WHERE <column> = <value>
  ?array $filter = null,
): bool;
// Returns true if at least 1 row was changed
```

### Example
```php
$db->update("beverages", ["beverage_size" => 10]);
// UPDATE beverages SET beverage_size = 10
```
```php
true
```

## WHERE

In most cases you probably want to UPDATE against a constaint. Passing an array to the 3:rd argument of `MySQL->update()` will let you define "equals AND" conditions.

```php
$filter = ["beverage_type" => "coffee"];
$update = ["beverage_size" => 10];

$db->update("beverages", $update, $filter);
// UPDATE beverages SET beverage_size = 10 WHERE beverage_type = "coffee"
```
```php
true
```
