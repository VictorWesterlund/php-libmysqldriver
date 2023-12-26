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

		// Coerce input to array
		private static function to_array(mixed $input): array {
			return is_array($input) ? $input : [$input];
		}

		// Execute SQL query with optional prepared statement and return array of affected rows
		public function exec(string $sql, mixed $params = null): array {
			$query = $this->execute_query($sql, self::to_array($params));
			$res = [];

			// Fetch rows into sequential array
			while ($row = $query->fetch_assoc()) {
				$res[] = $row;
			}

			return $res;
		}

		// Execute SQL query with optional prepared statement and return true if query was successful
		public function exec_bool(string $sql, mixed $params = null): bool {
			$query = $this->execute_query($sql, self::to_array($params));

			return gettype($query) === "boolean"
				// Type is already a bool, so return it as is
				? $query
				// Return true if rows were matched
				: $query->num_rows > 0;
		}
	}
