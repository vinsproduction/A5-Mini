<?php
class DBConnection_mysql_driver extends DBConnection
{
	// Регулярные выражения для определения идентификатора и квотированного идентификатора
	// для данного драйвера базы данных
	protected $IDENT_QUOTE_CHAR = "`";
	protected $IDENT_QUOTED_REGEX = '`([^`]|``)+`';

	// Уровень вложенности транзакции.
	// MySQL не поддерживают "вложенные" транзакции - поэтому
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
	}

	// Соединения с базой данных - функция вызывается при первом же запросе к базе данных
	// Параметры соединения храняться в $this->connection_params
	function connect()
	{
		$params = $this->connection_params;

		$server = null;	$username = null; $password = null; $new_link = false; $client_flags = 0;
		if (array_key_exists("host", $params)) { $server = $params["host"]; }
		if (array_key_exists("port", $params)) { $server .= ":" . $params["port"]; }
		if (array_key_exists("user", $params)) { $username = $params["user"]; }
		if (array_key_exists("password", $params)) { $password = $params["password"]; }
		if (array_key_exists("new_link", $params)) { $new_link = $params["new_link"]; }
		if (array_key_exists("client_flags", $params)) { $client_flags = $params["client_flags"]; }

		if (@$params["pconnect"]) { $this->link = @mysql_pconnect($server, $username, $password, $new_link, $client_flags); }
		else { $this->link = @mysql_connect($server, $username, $password, $new_link, $client_flags); }
		if (!$this->link) { $this->_error(@$php_errormsg); }

		if ($this->link)
		{
			if (array_key_exists("database", $params)) { @mysql_select_db($params["database"], $this->link) or $this->_error(@$php_errormsg); }
			if (@$params["client_encoding"]) { $this->_set_client_encoding($params["client_encoding"]); }
		}

		$this->transaction_level = 0;
		return $this->link;
	}

	function close() { if ($this->link) { @mysql_close($this->link) or $this->_error(@$php_errormsg); $this->link = null; } }

	function begin()
	{
		if ($this->transaction_level <= 0)
		{ $this->query("BEGIN"); $this->transaction_level = 1; }
		else { $this->transaction_level++; }
	}

	function commit()
	{
		if ($this->transaction_level > 0)
		{
			if ($this->transaction_level == 1)
			{ $this->query("COMMIT"); $this->transaction_level = 0; }
			else { $this->transaction_level--; }
		}
		else
		{ $this->transaction_level = 0; }
	}

	function rollback()
	{
		if ($this->transaction_level > 0)
		{
			if ($this->transaction_level == 1)
			{ $this->query("ROLLBACK"); $this->transaction_level = 0; }
			else { $this->transaction_level--; }
		}
		else
		{ $this->transaction_level = 0; }
	}

	// Функция возвращает ошибку которую вызвала последняя операция с базой данных
	// Если переданный параметр - true, то функция должна возвращать чистый текст ошибки (для человека)
	// Т.е. в строке не должно содержаться различных доп.сообщений.
	// К примеру строка ошибки такая как "ERROR: Invalid syntax" должна преобразиться в "Invalid syntax"
	function last_error($parse = false) { return trim(@mysql_error($this->link())); }

	// escape-строки для использования в конструкциях var LIKE '...'
	// при условии что не используется пользовательский символ квотирования
	function like($str)
	{
		if ($str instanceof DBConnection_Raw) { return $str->__toString(); }
		return $this->str(preg_replace("/[\\\\]{2}([_%])/u", "\\\\$1", str_replace("\\", "\\\\", $str)));
	}

	// Функция для указанной таблицы должна вернуть список её полей и их типами
	function get_field_types($table)
	{
		static $cache = array();

		$table = trim($table);
		if (!isset($cache[$table]))
		{
			$fields = $this->select_all("SHOW FIELDS FROM " . $this->escape_ident($table), array("@key" => "field"));
			if ($fields !== false)
			{
				foreach ($fields as $key => $val)
				{
					if
					(
						$val["type"]  == "tinyint(1)"
						&& $val["null"] == "NO"
						&& ($val["default"] === "0" || $val["default"] === "1")
					)
					{ $fields[$key] = "bool"; }
					elseif (preg_match("/^(tinyint|smallint|mediumint|int|bigint)/u", $val["type"]))
					{ $fields[$key] = "int"; }
					elseif (preg_match("/^(tinyblob|mediumblob|blob|longblob|binary)/u", $val["type"]))
					{ $fields[$key] = "blob"; }
					elseif (preg_match("/^(datetime|timestamp)/u", $val["type"]))
					{ $fields[$key] = "datetime"; }
					elseif (preg_match("/^(date|year)/u", $val["type"]))
					{ $fields[$key] = "date"; }
					elseif (preg_match("/^(time)/u", $val["type"]))
					{ $fields[$key] = "time"; }
					elseif (preg_match("/^(float|double|decimal)/u", $val["type"]))
					{ $fields[$key] = "real"; }
					elseif (preg_match("/^(char|varchar|binary|varbinary|blob|text|enum|set)/u", $val["type"]))
					{ $fields[$key] = "string"; }
					else { $fields[$key] = preg_replace("/^([a-z0-9_]+).*$/sxu", "$1", $val["type"]); }
				}
				$cache[$table] = $fields;
			}
			else { $cache[$table] = false; }
		}
		return $cache[$table];
	}

	protected function _check_db_extension()
	{
		if (!extension_loaded("mysql")) { throw_error("MySQL extension not loaded or not compiled in. Please install \"mysql\" extension for PHP to use that database driver.", true); }
	}

	// Функция делает реальный запрос к базе данных
	protected function _query($sql)
	{
		$result = @mysql_query($sql, $this->link());
		if ($result === false) { $this->_error(@$php_errormsg); }
		return $result;
	}

	protected function _insert($params, $table_name, $fields)
	{
		$result = call_user_func_array(array(&$this, "query"), $params);
		if ($result !== false)
		{
			$this->last_insert_id = @mysql_insert_id($this->link());
			if ($this->last_insert_id === 0) { $this->last_insert_id = null; }
			return $this->last_insert_id;
		}
		return $result;
	}

	protected function _fetch($result)
	{
		$r = mysql_fetch_assoc($result);
		if ($r !== false) { $r = array_change_key_case($r, CASE_LOWER); }
		return $r;
	}

	protected function _result_seek($r, $offset) { return mysql_data_seek($r, $offset); }
	protected function _num_fields($r) { return mysql_num_fields($r); }
	protected function _num_rows($r) { return mysql_num_rows($r); }

	protected function _affected_rows($r = null)
	{
		if ($r === null && $this->_is_result($this->last_result)) { $r = $this->last_result->resource; }
		if ($r) { return @mysql_affected_rows($r); } else { return 0; }
	}

	protected function _field_name($r, $i) { return strtolower(mysql_field_name($r, $i)); }
	protected function _field_type($r, $i) { return mysql_field_type($r, $i); }
	protected function _escape_str($s) { return mysql_real_escape_string($s, $this->link()); }
	protected function _escape_binary($s) { return mysql_real_escape_string($s, $this->link()); }
	protected function _set_client_encoding($encoding) { $this->query("SET NAMES ?", $encoding); }
}