<?php

	namespace libmysqldriver\Driver;

	use \mysqli;
	use \mysqli_stmt;
	use \mysqli_result;

	// MySQL query builder and executer abstractions
	class DatabaseDriver extends mysqli {
		// Passing arguments to https://www.php.net/manual/en/mysqli.construct.php
		function __construct() {
			parent::__construct(...func_get_args());
		}

		// Create CSV from array
		private static function csv(array $items): string {
			return implode(",", $items);
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
		public function exec_bool(string $sql, mixed $params = null): bool {
			$query = $this->run_query($sql, $params);

			return gettype($query) === "boolean"
				// Type is already a bool, so return it as is
				? $query
				// Return true if rows were matched
				: $query->num_rows > 0;
		}
	}
