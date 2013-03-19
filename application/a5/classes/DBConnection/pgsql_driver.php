<?php
class DBConnection_pgsql_driver extends DBConnection
{
	// Уровень вложенности транзакции.
	// PostgreSQL не поддерживают "вложенные" транзакции - поэтому
	// если транзакция уже была запущена - новую запускать не следует
	// (хотя и ошибки это тоже не вызовет).
	// Вместо этого мы просто увеличим счётчик вложенности.
	// Уровень должен уменьшаться на 1 при каждом вызове
	// rollback или commit.	И только когда уровень дойдёт до 0
	// будет реально вызван rollback или commit.
	private $transaction_level = 0;

	function __construct($params)
	{
		$this->connection_params = $params;
		$this->_register_marker("?like", "like");
		$this->_register_marker("?tsq", "plainto_tsquery");
		$this->unescape_types["bytea"] = array(&$this, "_unescape_binary");
		$this->unescape_types["bool"] = array(&$this, "_unescape_bool");
	}

	// Соединения с базой данных - функция вызывается при первом же запросе к базе данных
	// Параметры соединения храняться в $this->connection_params
	function connect()
	{
		$params = $this->connection_params;

		$conn = array();
		if (is_array($params))
		{
 			if (array_key_exists("host", $params)) { $conn[] = "host='" . addslashes($params["host"]) . "'"; }
			if (array_key_exists("hostaddr", $params)) { $conn[] = "hostaddr='" . addslashes($params["hostaddr"]) . "'"; }
			if (array_key_exists("port", $params)) { $conn[] = "port='" . addslashes($params["port"]) . "'"; }
			if (array_key_exists("database", $params)) { $conn[] = "dbname='" . addslashes($params["database"]) . "'"; }
			if (array_key_exists("user", $params)) { $conn[] = "user='" . addslashes($params["user"]) . "'"; }
			if (array_key_exists("password", $params)) { $conn[] = "password='" . addslashes($params["password"]) . "'"; }
			if (array_key_exists("connect_timeout", $params)) { $conn[] = "connect_timeout='" . addslashes($params["connect_timeout"]) . "'"; }
			if (array_key_exists("options", $params)) { $conn[] = "options='" . addslashes($params["options"]) . "'"; }
			if (array_key_exists("sslmode", $params)) { $conn[] = "sslmode='" . addslashes($params["sslmode"]) . "'"; }
			if (array_key_exists("service", $params)) { $conn[] = "service='" . addslashes($params["service"]) . "'"; }
		}

		if (@$params["pconnect"]) { $this->link = @pg_pconnect(implode(" ", $conn)); }
		else { $this->link = @pg_connect(implode(" ", $conn)); }
		if (!$this->link) { $this->_error(@$php_errormsg); }

		if (@$params["search_path"])
		{
			$params["search_path"] = explode(",", $params["search_path"]);
			$this->query("SET search_path = ?", $params["search_path"]);
		}

		if (@$params["client_encoding"]) { $this->_set_client_encoding($params["client_encoding"]); }

		$this->transaction_level = 0;
		return $this->link;
	}

	function close() { if ($this->link) { @pg_close($this->link) or $this->_error(@$php_errormsg); $this->link = null; } }

	function begin()
	{
		if ($this->transaction_level <= 0) { $this->query("BEGIN"); $this->transaction_level = 1; }
		else { $this->transaction_level++; }
	}

	function commit()
	{
		if ($this->transaction_level > 0)
		{
			if ($this->transaction_level == 1) { $this->query("COMMIT"); $this->transaction_level = 0; }
			else { $this->transaction_level--; }
		}
		else { $this->transaction_level = 0; }
	}

	function rollback()
	{
		if ($this->transaction_level > 0)
		{
			if ($this->transaction_level == 1) { $this->query("ROLLBACK"); $this->transaction_level = 0; }
			else { $this->transaction_level--; }
		}
		else { $this->transaction_level = 0; }
	}

	// Функция возвращает ошибку которую вызвала последняя операция с базой данных
	// Если переданный параметр - true, то функция должна возвращать чистый текст ошибки (для человека)
	// Т.е. в строке не должно содержаться различных доп.сообщений.
	// К примеру строка ошибки такая как "ERROR: Invalid syntax" должна преобразиться в "Invalid syntax"
	function last_error($parse = false)
	{
		$pg_last_error = @pg_last_error($this->link());
		if ($parse)
		{
			// Нужно вытащить только сообщение об ошибке
			// Сообщение об ошибке начинается с "ERROR: "
			$lines = preg_split("/([\r\n])/u", $pg_last_error, -1, PREG_SPLIT_DELIM_CAPTURE);
			$errstr = null;
			$error_found = false;

			foreach ($lines as $line)
			{
				// Если строка начинается с описания типа сообщения
				if (preg_match("/^[a-zA-Z0-9_]+:\s*/sxu", $line))
				{
					// Если сообщение об ошибке было найдено ранее
					if ($error_found) { break; }
					// Если нет и это как раз и начинается сообщение об ошибке
					elseif (preg_match("/^ERROR:\s*/sixu", $line))
					{
						$errstr .= preg_replace("/^ERROR:\s*/sixu", "", $line);
						$error_found = true;
						continue;
					}
				}
				// Иначе если уже нашли ранее сообщение об ошибке - дополняем его
				elseif ($error_found) { $errstr .= $line; }
			}

			if (is_empty($errstr)) { return trim($pg_last_error); } else { return trim($errstr); }
		}
		else { return $pg_last_error; }
	}

	// Постгрес поддерживает ввод время и даты с миллисекундами и временной зоной - заменим данные методы
	function datetime($str)
	{
		if ($str instanceof DBConnection_Raw) { return $str->__toString(); }
		return !Valid::date($str) ? "NULL" : "'" . Date::format($str, "Y-m-d H:i:s.uO") . "'";
	}

	function time($str)
	{
		if ($str instanceof DBConnection_Raw) { return $str->__toString(); }
		return !Valid::date($str) ? "NULL" : "'" . Date::format($str, "H:i:s.uO") . "'";
	}

	// escape-строки для использования в конструкциях var LIKE '...'
	// при условии что не используется пользовательский символ квотирования
	function like($str)
	{
		if ($str instanceof DBConnection_Raw) { return $str->__toString(); }
		return $this->str(preg_replace("/[\\\\]{2}([_%])/u", "\\\\$1", str_replace("\\", "\\\\", $str)));
	}

	// Преобразует обычный текст в выражение to_tsquery(...) второй параметр используется для совпадения всех слов или же нескольких
	function plainto_tsquery($str, $is_or = true, $is_use_wc = true)
	{
		if ($str instanceof DBConnection_Raw) { return $str->__toString(); }
		return "to_tsquery(" . $this->to_tsquery_escape($str, $is_or, $is_use_wc) . ")";
	}

	// Строка для подстановки полнотекстового поиска составляется из обычной
	function to_tsquery_escape($str, $is_or = true, $is_use_wc = true)
	{
		if ($str instanceof DBConnection_Raw) { return $str->__toString(); }
		$str = preg_split("/[\s\t!\\\\&\|'():*]+/su", trim($str));
		$return_str = null;
		foreach ($str as $s)
		{
			$s = trim($s);
			if (!is_empty($s))
			{
				if (!is_empty($return_str)) { if ($is_or) { $return_str .= " | "; } else { $return_str .= " & "; } }
				$return_str .= $s . ($is_use_wc ? ":*" : null);
			}
		}
		return $this->str($return_str);
	}

	// Функция для указанной таблицы должна вернуть список её полей и их типами
	function get_field_types($table)
	{
		static $cache = array();

		$table = trim($table);
		if (!isset($cache[$table]))
		{
			$fields = $this->select_all("
			SELECT
				a.attname as name,
				FORMAT_TYPE(a.atttypid, null) as type,
				pg_get_expr(d.adbin, a.attrelid, true) as default_value,
				CASE WHEN a.attnotnull THEN 1 ELSE 0 END as is_notnull
			FROM
				pg_attribute a
				LEFT JOIN pg_attrdef d ON a.attrelid = d.adrelid AND a.attnum = d.adnum
			WHERE
				a.attrelid = " . $this->str($this->escape_ident($table)) . "::regclass
				AND a.attnum > 0
				AND NOT a.attisdropped
			ORDER BY
				a.attnum
			", array("@key" => "name"));

			if ($fields !== false)
			{
				foreach ($fields as $key => $val)
				{
					if
					(
						(
							$val["type"] == "smallint"
							&& $val["is_notnull"]
							&& ($val["default_value"] === "0" || $val["default_value"] === "1")
						)
						|| $val["type"] == "boolean"
					)
					{ $fields[$key] = "bool"; }
					elseif (in_array($val["type"], array("bigint", "integer", "smallint")))
					{ $fields[$key] = "int"; }
					elseif ($val["type"] == "bytea")
					{ $fields[$key] = "blob"; }
					elseif ($val["type"] == "date")
					{ $fields[$key] = "date"; }
					elseif (strpos($val["type"], "timestamp ") === 0)
					{ $fields[$key] = "datetime"; }
					elseif (strpos($val["type"], "time ") === 0)
					{ $fields[$key] = "time"; }
					elseif (in_array($val["type"], array("double precision", "real", "numeric")))
					{ $fields[$key] = "real"; }
					elseif (in_array($val["type"], array("character varying", "varchar", "character", "char", "text")))
					{ $fields[$key] = "string"; }
					else { $fields[$key] = $val["type"]; }
				}
				$cache[$table] = $fields;
			}
			else { $cache[$table] = false; }
		}
		return $cache[$table];
	}

	protected function _check_db_extension()
	{
		if (!extension_loaded("pgsql")) { throw_error("PgSQL extension not loaded or not compiled in. Please install \"pgsql\" extension for PHP to use that database driver.", true); }
	}

	// Функция делает реальный запрос к базе данных
	protected function _query($sql)
	{
		$result = @pg_query($this->link(), $sql);
		if ($result === false) { $this->_error(@$php_errormsg); }
		return $result;
	}

	// Для указанной таблицы возвращает имя поля являющееся primary key и
	// имя последовательности, которая генерит для него значение
	private function _get_pk_and_seqname($table)
	{
		static $cache = array();
		$table = trim($table);
		if (!array_key_exists($table, $cache))
		{
			/*
			У PostgreSQL нет поля подобного MySQL auto_increment
			Поэтому таким полем мы будем считать то, которое удовлетворяет следующим условиям:
			  - primary key
			  - unique
			  - тип поля - целое число
			  - дефолтное значение - функция nextval для последовательности
			*/
			$saved_query = $this->last_query;
			$cache[$table] = $this->select_row("
			SELECT
				a.attname as \"0\",
				split_part(pg_get_expr(d.adbin, a.attrelid, true), '''', 2) as \"1\"
			FROM
				pg_index i
				JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey) AND a.attnum > 0 AND NOT a.attisdropped
				JOIN pg_attrdef d ON d.adrelid = a.attrelid AND d.adnum = a.attnum
			WHERE
				i.indrelid = ?::regclass
				AND i.indisprimary
				AND i.indisunique
				AND FORMAT_TYPE(a.atttypid, null) IN ('bigint', 'integer', 'smallint')
				AND pg_get_expr(d.adbin, a.attrelid, true) ILIKE 'nextval(%'
			", $this->escape_ident($table));
			$this->last_query = $saved_query;
		}
		return $cache[$table];
	}

	protected function _insert($params, $table_name, $fields)
	{
		$result = call_user_func_array(array(&$this, "query"), $params);
		if ($result !== false)
		{
			$pk_and_seq = $this->_get_pk_and_seqname($table_name);
			if ($pk_and_seq === false) { return null; }
			else
			{
				list($pk, $seq) = $pk_and_seq;
				if (!in_array($pk, $fields))
				{
					$saved_query = $this->last_query;
					$this->last_insert_id = @$this->select_cell("SELECT currval(?)", $seq);
					if ($this->last_insert_id === false) { $this->last_insert_id = null; }
					$this->last_query = $saved_query;
				}
				return $this->last_insert_id;
			}
		}
		return $result;
	}

	protected function _fetch($result) { return pg_fetch_assoc($result); }
	protected function _result_seek($r, $offset) { return pg_result_seek($r, $offset); }
	protected function _num_fields($r) { return pg_num_fields($r); }
	protected function _num_rows($r) { return pg_num_rows($r); }

	protected function _affected_rows($r = null)
	{
		if ($r === null && $this->_is_result($this->last_result)) { $r = $this->last_result->resource; }
		if ($r) { return @pg_affected_rows($r); } else { return 0; }
	}

	protected function _field_name($r, $i) { return pg_field_name($r, $i); }
	protected function _field_type($r, $i) { return pg_field_type($r, $i); }
	protected function _escape_str($s) { return pg_escape_string($this->link(), $s); }
	protected function _escape_binary($s) { return pg_escape_bytea($this->link(), $s); }
	protected function _unescape_binary($s) { return pg_unescape_bytea($s); }
	protected function _unescape_bool($s) { return in_array($s, array("f", "false", "n", "no", "0")) ? false : true; }
	protected function _set_client_encoding($encoding) { if (@pg_set_client_encoding($this->link(), $encoding) === -1) $this->_error(@$php_errormsg); }
}