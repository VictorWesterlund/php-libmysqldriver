<?php

	namespace libmysqldriver;

	use \Exception;

	use \mysqli;
	use \mysqli_stmt;
	use \mysqli_result;

	// Interface for MySQL_Driver with abstractions for data manipulation
	class MySQL extends mysqli {
		private string $table;
		private ?array $model = null;

		private bool $flatten = false;
		private ?string $order_by = null;
		private ?string $filter_sql = null;
		private array $filter_values = [];
		private ?string $limit = null;

		// Pass constructor arguments to driver
		function __construct() {
			parent::__construct(...func_get_args());
		}

		/*
			# Helper methods
		*/

		private function throw_if_no_table() {
			if (!$this->table) {
				throw new Exception("No table name defined");
			}
		}

		// Coerce input to single dimensional array
		private static function to_list_array(mixed $input): array {
			return array_values(is_array($input) ? $input : [$input]);
		}

		// Convert value to MySQL tinyint
		private static function filter_boolean(mixed $value): int {
			return (int) filter_var($value, FILTER_VALIDATE_BOOLEAN);
		}

		// Convert all boolean type values to tinyints in array
		private static function filter_booleans(array $values): array {
			return array_map(fn($v): mixed => gettype($v) === "boolean" ? self::filter_boolean($v) : $v, $values);
		}

		// Return value(s) that exist in $this->model
		private function in_model(string|array $columns): ?array {
			// Place string into array
			$columns = is_array($columns) ? $columns : [$columns];
			// Return columns that exist in table model
			return array_filter($columns, fn($col): string => in_array($col, $this->model));
		}

		/*
			# Definers
			These methods are used to build an SQL query by chaining methods together.
			Defined parameters will then be executed by an Executer method.
		*/

		// Use the following table name
		public function for(string $table): self {
			$this->table = $table;
			return $this;
		}

		// Restrict query to array of column names
		public function with(?array $model = null): self {
			// Remove table model if empty
			if (!$model) {
				$this->model = null;
				return $this;
			}

			// Reset table model
			$this->model = [];

			foreach ($model as $k => $v) {
				// Column values must be strings
				if (!is_string($v)) {
					throw new Exception("Key {$k} must have a value of type string");
				}

				// Append column to model
				$this->model[] = $v;
			}

			return $this;
		}

		// Create a WHERE statement from filters
		public function where(?array ...$conditions): self {
			// Unset filters if null was passed
			if ($conditions === null) {
				$this->filter_sql = null;
				$this->filter_values = null;

				return $this;
			}

			$values = [];
			$filters = [];

			// Group each condition into an AND block
			foreach ($conditions as $condition) {
				$filter = [];

				// Move along if the condition is empty
				if (empty($condition)) {
					continue;
				}

				// Create SQL string and append values to array for prepared statement
				foreach ($condition as $col => $value) {
					if ($this->model && !$this->in_model($col)) {
						continue;
					}

					// Value is null so it does not need to be added to the prepared statement
					if (is_null($value)) {
						$filter[] = "`{$col}` IS NULL";
						continue;
					}

					// Create SQL for prepared statement
					$filter[] = "`{$col}` = ?";
					// Append value to array with all other values
					$values[] = $value;
				}

				// AND together all conditions into a group
				$filters[] = "(" . implode(" AND ", $filter) . ")";
			}

			// Do nothing if no filters were set
			if (empty($filters)) {
				return $this;
			}

			// OR all filter groups
			$this->filter_sql = implode(" OR ", $filters);
			// Set values property
			$this->filter_values = $values;

			return $this;
		}

		// SQL LIMIT string
		public function limit(?int $limit, ?int $offset = null): self {
			// Unset row limit if null was passed
			if ($limit === null) {
				$this->limit = null;
				return $this;
			}

			// Set LIMIT without range directly as integer
			if (is_int($limit)) {
				$this->limit = $limit;
				return $this;
			}

			// No offset defined, set limit property directly as string
			if (is_null($offset)) {
				$this->limit = (string) $limit;
				return $this;
			}

			// Set limit and offset as SQL CSV
			$this->limit = "{$offset},{$limit}";
			return $this;
		}

		// Flatten returned array to first entity if set
		public function flatten(bool $flag = true): self {
			$this->flatten = $flag;
			return $this;
		}

		// Return SQL SORT BY string from assoc array of columns and direction
		public function order(?array $order_by): self {
			// Unset row order by if null was passed
			if ($order_by === null) {
				$this->order_by = null;
				return $this;
			}

			// Create CSV from columns
			$sql = implode(",", array_keys($order_by));
			// Create pipe DSV from values 
			$sql .= " " . implode("|", array_values($order_by));

			$this->order_by = $sql;
			return $this;
		}

		/*
			# Executors
			These methods execute various statements that each return a mysqli_result
		*/

		// Create Prepared Statament for SELECT with optional WHERE filters
		public function select(array|string|null $columns = null): mysqli_result|bool {
			$this->throw_if_no_table();

			// Create array of columns from CSV
			$columns = is_array($columns) || is_null($columns) ? $columns : explode(",", $columns);

			// Filter columns that aren't in the model if defiend
			if ($columns && $this->model) {
				$columns = $this->in_model($columns);
			}

			// Create CSV from columns or default to SQL NULL as a string
			$columns_sql = $columns ? implode(",", $columns) : "NULL";

			// Create LIMIT statement if argument is defined
			$limit_sql = !is_null($this->limit) ? " LIMIT {$this->limit}" : "";

			// Create ORDER BY statement if argument is defined
			$order_by_sql = !is_null($this->order_by) ? " ORDER BY {$this->order_by}" : "";

			// Get array of SQL WHERE string and filter values
			$filter_sql = !is_null($this->filter_sql) ? " WHERE {$this->filter_sql}" : "";

			// Interpolate components into an SQL SELECT statmenet and execute
			$sql = "SELECT {$columns_sql} FROM {$this->table}{$filter_sql}{$order_by_sql}{$limit_sql}";

			// Return array of matched rows
			$exec = $this->execute_query($sql, self::to_list_array($this->filter_values));
			// Return array if exec was successful. Return as flattened array if flag is set
			return empty($exec) || !$this->flatten ? $exec : $exec[0];
		}

		// Create Prepared Statement for UPDATE using PRIMARY KEY as anchor
		public function update(array $entity): mysqli_result|bool {
			$this->throw_if_no_table();

			// Make constraint for table model if defined
			if ($this->model) {
				foreach (array_keys($entity) as $col) {
					// Throw if column in entity does not exist in defiend table model
					if (!in_array($col, $this->model)) {
						throw new Exception("Column key '{$col}' does not exist in table model");
					}
				}
			}

			// Create CSV string with Prepared Statement abbreviations from length of fields array.
			$changes = array_map(fn($column) => "{$column} = ?", array_keys($entity));
			$changes = implode(",", $changes);

			// Get array of SQL WHERE string and filter values
			$filter_sql = !is_null($this->filter_sql) ? " WHERE {$this->filter_sql}" : "";

			// Get values from entity and convert booleans to tinyint
			$values = self::filter_booleans(array_values($entity));
			
			// Append values to filter property if where() was chained
			if ($this->filter_values) {
				array_push($values, ...$this->filter_values);
			}

			// Interpolate components into an SQL UPDATE statement and execute
			$sql = "UPDATE {$this->table} SET {$changes} {$filter_sql}";
			return $this->execute_query($sql, self::to_list_array($values));
		}

		// Create Prepared Statemt for INSERT
		public function insert(array $values): mysqli_result|bool {
			$this->throw_if_no_table();

			// A value for each column in table model must be provided
			if ($this->model && count($values) !== count($this->model)) {
				throw new Exception("Values length does not match columns in model");
			}

			/*
				Use array keys from $values as columns to insert if array is associative.
				Treat statement as an all-columns INSERT if the $values array is sequential.
			*/
			$columns = !array_is_list($values) ? "(" . implode(",", array_keys($values)) . ")" : "";

			// Convert booleans to tinyint
			$values = self::filter_booleans($values);

			// Create CSV string with Prepared Statement abbreviatons from length of fields array.
			$values_stmt = implode(",", array_fill(0, count($values), "?"));

			// Interpolate components into an SQL INSERT statement and execute
			$sql = "INSERT INTO {$this->table} {$columns} VALUES ({$values_stmt})";
			return $this->execute_query($sql, self::to_list_array($values));
		}

		// Execute SQL query with optional prepared statement and return mysqli_result
		public function exec(string $sql, mixed $params = null): mysqli_result {
			return $this->execute_query($sql, self::to_list_array($params));
		}
	}
