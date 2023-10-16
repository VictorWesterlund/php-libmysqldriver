<?php

	namespace libmysqldriver;

	use libmysqldriver\Driver\DatabaseDriver;

	require_once "DatabaseDriver.php";

    // Interface for MySQL_Driver with abstractions for data manipulation
    class MySQL extends DatabaseDriver {
		// Pass constructor arguments to driver
        function __construct() {
			parent::__construct(...func_get_args());
		}

		// Create WHERE AND clause from assoc array of "column" => "value"
		private static function where(?array $filter = null): array {
			// Return array of an empty string and empty array if no filter is defined
			if (!$filter) {
				return ["", []];
			}

			// Format each filter as $key = ? for prepared statement
			$stmt = array_map(fn($k): string => "`{$k}` = ?", array_keys($filter));

			// Separate each filter with ANDs
			$sql = " WHERE " . implode(" AND ", $stmt);
			// Return array of SQL prepared statement string and values
			return [$sql, array_values($filter)];
		}

		// Return SQL SORT BY string from assoc array of columns and direction
		private static function order_by(array $order_by): string {
			$sql = " ORDER BY ";

			// Create CSV from columns
			$sql .= implode(",", array_keys($order_by));
			// Create pipe DSV from values 
			$sql .= " " . implode("|", array_values($order_by));

			return $sql;
		}

		// Return SQL LIMIT string from integer or array of [offset => limit]
		private static function limit(int|array $limit): string {
			$sql = " LIMIT ";

			// Return LIMIT without range directly as string
			if (is_int($limit)) {
				return $sql . $limit;
			}

			// Use array key as LIMIT range start value
			$offset = (int) array_keys($limit)[0];
			// Use array value as LIMIT range end value
			$limit = (int) array_values($limit)[0];

			// Return as SQL LIMIT CSV
			return $sql . "{$offset},{$limit}";
		}

		/* ---- */

        // Create Prepared Statament for SELECT with optional WHERE filters
        public function get(string $table, array|string $columns = null, ?array $filter = [], ?array $order_by = null, int|array|null $limit = null): array|bool {
            // Create CSV string of columns if argument defined, else return bool
            $columns_sql = $columns ? self::columns($columns) : "NULL";
			// Create LIMIT statement if argument is defined
			$limit_sql = $limit ? self::limit($limit) : "";
			// Create ORDER BY statement if argument is defined
			$order_by_sql = $order_by ? self::order_by($order_by) : "";

            // Get array of SQL WHERE string and filter values
			[$filter_sql, $filter_values] = self::where($filter);

			// Interpolate components into an SQL SELECT statmenet and execute
            $sql = "SELECT {$columns_sql} FROM {$table}{$filter_sql}{$order_by_sql}{$limit_sql}";

			// No columns were specified, return true if query matched rows
			if (!$columns) {
				return $this->exec_bool($sql, $filter_values);
			}

			// Return array of matched rows
			$exec = $this->exec($sql, $filter_values);
			// Flatten array if $limit === 1
			return empty($exec) || $limit !== 1 ? $exec : $exec[0];
        }

        // Create Prepared Statement for UPDATE using PRIMARY KEY as anchor
        public function update(string $table, array $fields, ?array $filter = null): bool {
            // Create CSV string with Prepared Statement abbreviations from length of fields array.
            $changes = array_map(fn($column) => "{$column} = ?", array_keys($fields));
            $changes = implode(",", $changes);

			// Get array of SQL WHERE string and filter values
			[$filter_sql, $filter_values] = self::where($filter);

            $values = array_values($fields);
			// Append filter values if defined
			if ($filter_values) {
				array_push($values, ...$filter_values);
			}

			// Interpolate components into an SQL UPDATE statement and execute
			$sql = "UPDATE {$table} SET {$changes} {$filter_sql}";
            return $this->exec_bool($sql, $values);
        }

        // Create Prepared Statemt for INSERT
        public function insert(string $table, array $values): bool {
            // Return CSV string with Prepared Statement abbreviatons from length of fields array.
            $values_stmt = self::csv(array_fill(0, count($values), "?"));

			// Interpolate components into an SQL INSERT statement and execute
            $sql = "INSERT INTO {$table} VALUES ({$values_stmt})";
            return $this->exec_bool($sql, $values);
        }
    }
