# php-libmysqldriver

This library provides abstraction methods for common operations on MySQL-like databases like `SELECT`, `UPDATE`, and `INSERT` using method chaining for the various MySQL features.

For example:
```php
MySQL->for(string $table)
  ->with(?array $model)
  ->where(?array ...$conditions)
  ->order(?array $order_by)
  ->limit(int|array|null $limit)
  ->select(array $columns): array|bool;
```
which would be equivalent to the following in MySQL:
```sql
SELECT $columns FROM $table WHERE $filter ORDER BY $order_by LIMIT $limit;
```

> [!IMPORTANT]
> This library is built on top of the PHP [`MySQL Improved`](https://www.php.net/manual/en/book.mysqli.php) extension and requires PHP 8.0 or newer.

## Install from composer

```
composer require victorwesterlund/libmysqldriver
```

```php
use libmysqldriver/MySQL;
```

# Example / Documentation

Available statements
Statement|Method
--|--
`SELECT`|[`select()`](#select)
`UPDATE`|[`update()`](#update)
`INSERT`|[`insert()`](#insert)
`WHERE`|[`where()`](#where)
`ORDER BY`|[`order()`](#order-by)
`LIMIT`|[`limit()`](#limit)

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

All executor methods [`select()`](#select), [`update()`](#update), and [`insert()`](#insert) will return a [`mysqli_result`](https://www.php.net/manual/en/class.mysqli-result.php) object or boolean.

# SELECT

Use `MySQL->select()` to retrieve columns from a database table.

Pass an associative array of strings, CSV string, or null to this method to filter columns.

```php
MySQL->select(
  array|string|null $columns
): mysqli_result|bool;
```

In most cases you probably want to select with a constraint. Chain the [`where()`](#where) method before `select()` to filter the query

### Example
```php
$beverages = MySQL->for("beverages")->select(["beverage_name", "beverage_size"]); // SELECT beverage_name, beverage_size FROM beverages
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

## Flatten array to single dimension

If you don't want an array of arrays and would instead like to access each key value pair directly. Chain the `MySQL->flatten()` anywhere before `MySQL->select()`.
This will return the key value pairs of the first entry directly.

> **Note**
> This method will not set `LIMIT 1` for you. It is recommended to chain `MySQL->limit(1)` anywhere before `MySQL->select()`. [You can read more about it here](https://github.com/VictorWesterlund/php-libmysqldriver/issues/14)

```php
$coffee = MySQL->for("beverages")->limit(1)->flatten()->select(["beverage_name", "beverage_size"]); // SELECT beverage_name, beverage_size FROM beverages WHERE beverage_type = "coffee" LIMIT 1
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
MySQL->insert(
  // Array of values to INSERT
  array $values
): mysqli_result|bool
// Returns true if row was inserted
```

#### Example

```php
MySQL->for("beverages")->insert([
  null,
  "coffee",
  "latte",
  10
]);
// INSERT INTO beverages VALUES (null, "coffee", "latte", 10);
```
```
true
```

# UPDATE

Modify existing rows with `MySQL->update()`

```php
MySQL->update(
  // Key, value array of column names and values to update
  array $fields,
): mysqli_result|bool;
// Returns true if at least 1 row was changed
```

### Example
```php
MySQL->for("beverages")->update(["beverage_size" => 10]); // UPDATE beverages SET beverage_size = 10
```
```php
true
```

In most cases you probably want to UPDATE against a constaint. Chain a [`where()`](#where) method before `update()` to set constraints


# WHERE

Filter a `select()` or `update()` method by chaining the `MySQL->where()` method anywhere before it.

```php
MySQL->where(
  ?array ...$conditions
): self;
```

### Example
```php
$coffee = MySQL->for("beverages")->where(["beverage_type" => "coffee"])->select(["beverage_name", "beverage_size"]); // SELECT beverage_name, beverage_size FROM beverages WHERE (beverage_type = "coffee");
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

## Advanced filtering

You can do more detailed filtering by passing more constraints into the same array, or even futher by passing multiple arrays each with filters.

### AND

Add additional key value pairs to an array passed to `where()` and they will all be compared as AND with each other.

```php
MySQL->where([
  "beverage_type" => "coffee",
  "beverage_size" => 15
]);
```
```sql
WHERE (beverage_type = 'coffee' AND beverage_size = 15)
```

### OR

Passing an additional array of key values as an argument will OR it with all other arrays passed.

```php
$filter1 = [
  "beverage_type" => "coffee",
  "beverage_size" => 15
];

$filter2 = [
  "beverage_type" => "tea",
  "beverage_name" => "black"
];

MySQL->where($filter1, $filter2, ...);
```
```sql
WHERE (beverage_type = 'coffee' AND beverage_size = 15) OR (beverage_type = 'tea' AND beverage_name = 'black')
```


# ORDER BY

Chain the `order()` method before a `select()` statement to order by a specific column

```php
MySQL->order(
  ?array $order_by
): self;
```

```php
$coffee = MySQL->for("beverages")->order(["beverage_name" => "ASC"])->select(["beverage_name", "beverage_size"]); // SELECT beverage_name, beverage_size FROM beverages ORDER BY beverage_name ASC
```
```php
[
  [
    "beverage_name" => "tea",
    "beverage_size" => 10
  ],
  [
    "beverage_name" => "tea",
    "beverage_size" => 15
  ],
  // ...etc for "beverage_name = coffee"
]
```

# LIMIT

Chain the `limit()` method before a `select()` statement to limit the amount of columns returned

```php
MySQL->limit(
  ?int $limit,
  ?int $offset = null
): self;
```

> **Note**
> You can also flatten to a single dimensional array from the first entity by chaining [`MySQL->flatten()`](#flatten-array-to-single-dimension)

## Passing a single integer argument
This will simply `LIMIT` the results returned to the integer passed

```php
$coffee = MySQL->for("beverages")->limit(1)->select(["beverage_name", "beverage_size"]); // SELECT beverage_name, beverage_size FROM beverages WHERE beverage_type = "coffee" LIMIT 1
```
```php
[
  [
    "beverage_name" => "cappuccino",
    "beverage_size" => 10
  ]
]
```

## Passing two integer arguments
This will `OFFSET` and `LIMIT` the results returned. The first argument will be the `LIMIT` and the second argument will be its `OFFSET`.

```php
$coffee = MySQL->for("beverages")->limit(3, 2)->select(["beverage_name", "beverage_size"]); // SELECT beverage_name, beverage_size FROM beverages LIMIT 3 OFFSET 2
```
```php
[
  [
    "beverage_name" => "tea",
    "beverage_size" => 10
  ],
  [
    "beverage_name" => "tea",
    "beverage_size" => 15
  ],
  // ...etc
]
```

----

# Restrict affected/returned database columns to table model

Chain and pass an array to `MySQL->with()` before a `select()`, `update()`, or `insert()` method to limit which columns will be returned/affected. It will use the **values** of the array so it can be either sequential or associative.

**This method will cause `select()`, `update()`, and `insert()` to ignore any columns that are not present in the passed table model.**

You can remove an already set table model by passing `null` to `MySQL->with()`
