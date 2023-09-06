<?php

	namespace libmysqldriver;

	use \mysqli;
	use \mysqli_stmt;
	use \mysqli_result;

    // Streamline common MariaDB operations for LAMSdb3
    class MySQL extends mysqli {
		// Passing arguments to https://www.php.net/manual/en/mysqli.construct.php
        function __construct() {
			parent::__construct(...func_get_args());
		}

        // Create CSV from array
        private static function csv(array $items): string {
            return implode(",", $items);
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
			$sql = "WHERE " . implode(" AND ", $stmt);
			// Return array of SQL prepared statement string and values
			return [$sql, array_values($filter)];
		}

		/* ---- */

		// Create CSV from columns
		public static function columns(array|string $columns): string {
			return is_array($columns) 
				? self::csv($columns)
				: $columns;
		}

		// Return CSV of '?' for use with prepared statements
		public static function values(array|string $values): string {
			return is_array($values) 
				? self::csv(array_fill(0, count($values), "?"))
				: "?";
		}

		/* ---- */

		// Bind SQL statements
		private function bind_params(mysqli_stmt &$stmt, mixed $params): bool {
			// Convert single value parameter to array
			$params = is_array($params) ? $params : [$params];
			if (empty($params)) {
				return true;
			}

			// Concatenated string with types for each parameter
			$types = "";

			// Convert PHP primitves to SQL primitives
			foreach ($params as $param) {
				switch (gettype($param)) {
					case "integer":
					case "double":
					case "boolean":
						$types .= "i";
						break;

					case "string":
					case "array":
					case "object":
						$types .= "s";
						break;

					default:
						$types .= "b";
						break;
				}
			}

			return $stmt->bind_param($types, ...$params);
		}

		// Execute an SQL query with a prepared statement
		private function run_query(string $sql, mixed $params = null): mysqli_result|bool {
			$stmt = $this->prepare($sql);

			// Bind parameters if provided
			if ($params !== null) {
				$this->bind_params($stmt, $params);
			}

			// Execute statement and get retrieve changes
			$query = $stmt->execute();
			$res = $stmt->get_result();

			// Return true if an INSERT, UPDATE or DELETE was sucessful (no rows returned)
			if (!empty($query) && empty($res)) {
				return true;
			}

			// Return mysqli_result object
			return $res;
		}

		/* ---- */

		// Execute SQL query with optional prepared statement and return array of affected rows
		public function exec(string $sql, mixed $params = null): array {
			$query = $this->run_query($sql, $params);
			$res = [];

			// Fetch rows into sequential array
			while ($row = $query->fetch_assoc()) {
				$res[] = $row;
			}

			return $res;
		}

		// Execute SQL query with optional prepared statement and return true if query was successful
		private function exec_bool(string $sql, mixed $params = null): bool {
			$query = $this->run_query($sql, $params);

			return gettype($query) === "boolean"
				// Type is already a bool, so return it as is
				? $query
				// Return true if rows were matched
				: $query->num_rows > 0;
		}

		/* ---- */

        // Create Prepared Statament for SELECT with optional WHERE filters
        public function get(string $table, array|string $columns = null, ?array $filter = [], ?int $limit = null): array|bool {
            // Create CSV string of columns
            $columns_sql = $columns ? self::columns($columns) : "NULL";
			// Create LIMIT statement if argument is defined
			$limit_sql = is_int($limit) ? "LIMIT {$limit}" : "";

            // Get array of SQL WHERE string and filter values
			[$filter_sql, $filter_values] = self::where($filter);

			// Interpolate components into an SQL SELECT statmenet and execute
            $sql = "SELECT {$columns_sql} FROM {$table} {$filter_sql} {$limit_sql}";

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
