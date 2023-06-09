<?php

	namespace libmysqldriver;

	class MySQL extends \mysqli {
		function __construct(
			// From: https://www.php.net/manual/en/mysqli.construct.php
			string|null $host = null,
			string|null $user = null,
			string|null $pass = null,
			string|null $db = null
		) {
			parent::__construct($host, $user, $pass, $db);
		}

		// Bind SQL statements
		private function bind_params(\mysqli_stmt &$stmt, mixed $params) {
			// Make single-value, non-array, param an array with length of 1
			if (gettype($params) !== "array") {
				$params = [$params];
			}

			// Concatenated string with types for each param
			$types = "";

			if (!empty($params)) {
				// Convert PHP primitve to SQL primitive for params
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

				$stmt->bind_param($types, ...$params);
			}
		}

		// Execute an SQL query with a prepared statement
		private function run_query(string $sql, mixed $params = null): \mysqli_result|bool {
			$stmt = $this->prepare($sql);

			// Bind parameters if provided
			if ($params !== null) {
				$this->bind_params($stmt, $params);
			}

			// Execute statement and get affected rows
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

		// Create comma separated list (CSV) from array
		private static function csv(array $values): string {
			return implode(",", $values);
		}

		// Create CSV from columns
		public static function columns(array|string $columns): string {
			return is_array($columns) 
				? (__CLASS__)::csv($columns)
				: $columns;
		}

		// Return CSV of '?' for use with prepared statements
		public static function values(array|string $values): string {
			return is_array($values) 
				? (__CLASS__)::csv(array_fill(0, count($values), "?"))
				: "?";
		}

		/* ---- */

		// Get result as an associative array
		public function return_array(string $sql, mixed $params = null): array {
			$query = $this->run_query($sql, $params);

			$res = [];
			while ($data = $query->fetch_assoc()) {
				$res[] = $data;
			}

			return $res;
		}

		// Get only whether a query was sucessful or not
		public function return_bool(string $sql, mixed $params = null): bool {
			$query = $this->run_query($sql, $params);

			// Return query if it's already a boolean
			if (gettype($query) === "boolean") {
				return $query;
			}
			
			return $query->num_rows > 0 ? true : false;
		}
	}
