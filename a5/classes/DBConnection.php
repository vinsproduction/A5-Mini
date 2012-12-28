<?php
abstract class DBConnection
{
	abstract function __construct($params);
	abstract function connect();
	abstract function close();
	abstract function begin();
	abstract function commit();
	abstract function rollback();
	abstract function last_error($is_human_readable = false);
	abstract function get_field_types($table_name);
	abstract protected function _check_db_extension();
	abstract protected function _query($sql);
	abstract protected function _insert($params, $table_name, $fields);
	abstract protected function _fetch($result);
	abstract protected function _result_seek($result, $offset);
	abstract protected function _num_fields($result);
	abstract protected function _num_rows($result);
	abstract protected function _affected_rows($result = null);
	abstract protected function _field_name($result, $number);
	abstract protected function _field_type($result, $number);
	abstract protected function _escape_str($string);
	abstract protected function _escape_binary($string);
	abstract protected function _set_client_encoding($encoding);

	// Символ квотирования для идентификаторов в базе данных - должен быть
	// переопределён в драйвере если отличается от данного
	protected $IDENT_QUOTE_CHAR = '"';

	// Регулярное выражение, которому должен удовлетворять идентификатор
	// в базе данных без использования символа квотирования
	protected $IDENT_UNQUOTED_REGEX = '[a-z][a-z0-9_]*';

	// Регулярное выражение, которому должен удовлетворять идентификатор
	// с использованием символа квотирования
	protected $IDENT_QUOTED_REGEX = '"([^"]|"")+"';

	// Ресурс - соединение с БД
	// По возможности используйте везде где можно одноимённый метод link()
	// для получения значения данного ресурса вместо использования его на прямую
	// т.к. метод будет автоматически устанавливать соединение если оно не установлено
	protected $link = null;

	// Обработчики (callback) ошибок базы данных
	protected $error_handlers = array();

	// Последнее сообщение об ошибке установленное вручную
	// с помощью метода run_error
	protected $last_manual_error = null;

	// Последний выполненный запрос
	protected $last_query = null;

	// Ресурс последнего выполненного запроса к БД
	protected $last_result = null;

	// id последней вставленной записи в БД
	protected $last_insert_id = null;

	// Параметры соединения с базой данных (переданные в конструктор)
	protected $connection_params = array();

	// Обработчик кэширования
	protected $cache_handler = null;

	// Триггеры (callback)
	protected $triggers = array();

	// Стандартные маркеры ("маркер" => callback)
	protected $bind_markers = array("?b" => "bool", "?i" => "int", "?B" => "blob", "?dt" => "datetime", "?d" => "date", "?t" => "time", "?r" => "real", "?" => "str");

	// Массив соотвествий: тип данных -> маркер
	protected $bind_type_markers = null;

	// Типы, которые должны быть обработаны дополнительными callback-функциями
	// после того как будут извелечены из запроса (заполняется дочерним классом)
	protected $unescape_types = array();

	// Callback для ведения лога запросов и ошибок
	protected $logger = null;

	// Callback для сбора статистики по выполненным запросам
	protected $collector = null;

	// Используется только самим классом - хранит ссылки на
	// инстанции объектов уже созданных драйверов: methods_prefix -> class instance
	static public $objects_storage = array();

	// Статический метод - возвращает объект соединения с базой данных и создаёт функции
	// для вызова всех public методов, функции по-умолчанию создаются с префиксом "db_"
	// В параметрах обязательно нужно передать имя драйвера базы данных
	static function create($params)
	{
		if (!array_key_exists("driver", $params)) { throw_error("\"driver\" parameter not specified", true); }
		if (!preg_match("/^[a-zA-Z0-9_-]+$/u", $params["driver"])) { throw_error("Invalid database driver specified", true); }

		// Определяем имя класса, который будем создавать
		$class_name = __CLASS__ . "_" . $params["driver"] . "_driver";

		// Проверим можем ли мы создать данный класс
		if (!class_exists($class_name))
		{
			$driver_class_filename = __DIR__ . "/DBConnection/" . $params["driver"] . "_driver.php";
			if (file_exists($driver_class_filename) && is_readable($driver_class_filename)) { require_once($driver_class_filename); }
			if (!class_exists($class_name)) { throw_error("Cannot load class for driver \"" . $params["driver"] . "\": loading failed from \"" . $driver_class_filename . "\"", true); }
		}

		if (!@is_empty($params["prefix"])) { $params["prefix"] = trim($params["prefix"]); } else { $params["prefix"] = "db"; }

		$link = new $class_name($params);
		$link->_check_db_extension();

		// После создания класса - добавляем системные маркеры
		$link->_register_marker("?filter", "*FILTER*");
		$link->_register_marker("?expr", "*EXPRESSION*");

		if (!isset(self::$objects_storage[$params["prefix"]])) { self::$objects_storage[$params["prefix"]] = $link; }
		else { throw_error("Duplicate connection with prefix '" . $params["prefix"] . "'", true); }

		// Глобализируем public-методы с указанным префиксом
		// Если вы создали несколько соединений баз данных - переключаться на нужное нужно с помощью change_connection метода
		// в него передётся имя алиаса базы данных
		$methods = get_class_methods(get_class($link));
		foreach ($methods as $met)
		{
			$function_prefix = ($params["prefix"] ? $params["prefix"] . "_" : "");
			if (!is_callable($function_prefix . $met) && strpos($met, "_") !== 0 && !preg_match("/^dbconnection(_|$)/siu", $met))
			{
				eval('function ' . $function_prefix . $met . '()
				{
					$args = func_get_args();
					return call_user_func_array(array(&DBConnection::$objects_storage["' . $params["prefix"] . '"], "' . $met . '"), $args);
				}');
			}
		}

		return $link;
	}

	// Закрывает все открытые соединения
	static function close_all() { foreach (self::$objects_storage as $prefix => $obj) $obj->close(); }

	// Получает ссылку на инициированный объект
	static function get($prefix) { return isset(self::$objects_storage[$prefix]) ? self::$objects_storage[$prefix] : null; }

	function set_param($param, $value) { $this->connection_params[$param] = $value; }

	function get_param($param)
	{
		if (array_key_exists($param, $this->connection_params)) { return $this->connection_params[$param]; }
		else { return null; }
	}

	// Устанавливает функцию логирования запросов - функция должна принимать один единственный параметр - сообщение
	function set_logger($callback)
	{
		if (is_callable($callback)) { $this->logger = $callback; }
		else { throw_error("Logger function is not callable", true); }
	}

	// Устанавливает функцию сбора запросов (обычно требуется для статистики и дебага) - функция должна принимать один единственный параметр
	// результат запроса, который является экземпляром объекта DBConnection_Result
	function set_collector($callback)
	{
		if (is_callable($callback)) { $this->collector = $callback; }
		else { throw_error("Collector function is not callable", true); }
	}

	function query()
	{
		$args = func_get_args();

		$resource = null;
		$cache_ident = null;
		$cache_data = null;
		$caller = $this->_get_caller();

		// Разбираем переданные параметры на составляющие запроса
		list($query_sql, $query_binds, $query_params) = $this->_extract_arguments($args);

		// Является ли запрос сырым, не требующим обработки
		$is_raw_query = ($query_sql instanceof DBConnection_Raw);
		if ($is_raw_query) { $query_sql = $query_sql->__toString(); }

		// Выделяем из запроса параметры для кэширования
		list($is_cache_query, $cache_id, $cache_time, $cache_tags) = $this->_parse_params($query_sql);

		// Сохраним текст запроса, т.к. могут быть проблемы при подстановке параметров
		// затем создадим реальный sql запрос и запомним его как последний
		$this->last_query = $query_sql;
		if (!$is_raw_query) { $this->last_query = $query_sql = $this->make_sql($query_sql, $query_binds); }

		// Создаём объект результат запроса
		$result = new DBConnection_Result();

		// Запоминаем параметры для выборки этого запроса
		// Эти параметры влияют только на формирование результирующих данных и используются методами select_*
		$result->select_params = $query_params;

		// Время начала выполнения запроса (или получения его из кэша)
		$start = microtime(true);

		// Если запрос нужно кэшировать - запросим данные из кэша
		if ($is_cache_query)
		{
			$cache_ident = ($cache_id === null ? md5($query_sql) : $cache_id);
			$cache_data = call_user_func($this->cache_handler["fetch"], $cache_ident);
			if ($cache_data !== null && !$this->_is_result($cache_data)) { $cache_data = null; }
		}

		// если cache_data = null то нужно выполнить реальный запрос к базе данных, т.к. либо запрос не кэшируемый, либо данных в кэше нет
		if ($cache_data === null)
		{
			$result->performed = true;
			$resource = $this->_query($query_sql);
		}
		else { $result->cached = true; }

		$finish = microtime(true);

		// Заполняем данные результата запроса
		$result->sql = $this->last_query;
		$result->exec_time = ($finish - $start);
		$result->file = isset($caller["file"]) ? $caller["file"] : "Unknown";
		$result->line = isset($caller["line"]) ? $caller["line"] : 0;
		$result->resource = $resource;

		// Если включен режим отладки или включен режим логирования долгих запросов и данный запрос таковым является
		if ($this->get_param("debug_mode") || ($this->get_param("log_long_sql") && ceil($result->exec_time * 1000) >= $this->get_param("log_long_sql")))
		{
			$log_msg = "SQL: (" . sprintf("%.3f sec", $result->exec_time);
			if ($result->cached) { $log_msg .= ", CACHED"; if ($result->performed) { $log_msg .= ", PERFORMED"; } }
			$log_msg .= ") " . $result->sql . " in file " . $result->file . " on line " . $result->line;
			$this->_write_to_log($log_msg); unset($log_msg);
		}

		// Если имеется успешный ресурс запроса - то нужно сохранить информацию о полях и возможно считать данные в кэш
		if ($resource)
		{
			$start = microtime(true);
			$fields_count = @$this->_num_fields($resource);
			if ($fields_count > 0)
			{
				$result->count = @$this->_num_rows($resource);

				for ($i = 0; $i < $fields_count; $i++)
				{
					$field_name = @$this->_field_name($resource, $i);
					$field_type = @$this->_field_type($resource, $i);
					// Записываем информацию о полях
					$result->fields[$field_name] = $field_type;
					// Сохраняем информацию о ключах, значения которых нужно обработать особым образом
					if (isset($this->unescape_types[$field_type])) { $result->unescape_fields[$field_name] = $this->unescape_types[$field_type]; }
				}

				// Если запрос нужно кэшировать и данные для кэша считаны не были
				// То всю информацию сразу считываем полностью и передаём её на сохранение обработчику
				if ($is_cache_query && $cache_data === null)
				{
					$row_number = 0;
					while (true)
					{
						$result->rows[$row_number] = $this->_fetch($resource);
						if ($result->rows[$row_number] !== false)
						{
							foreach ($result->unescape_fields as $key => $callback)
							{ $result->rows[$row_number][$key] = call_user_func($callback, $result->rows[$row_number][$key]); }
						}
						else { unset($result->rows[$row_number]); break; }
						$row_number++;
					}

					$result->cached = true;

					// Обработчик должен принимать: id запроса, время кэширования, тэги кэширования и данные для сохранения
					call_user_func($this->cache_handler["store"], $cache_ident, $result, $cache_time, $cache_tags);
				}
			}
			$finish = microtime(true);

			// Увеличиваем время фетча
			$result->fetch_time += ($finish - $start);
		}
		// Иначе если запрос кэшируемый и данные считаны
		elseif ($is_cache_query && $cache_data !== null)
		{
			$result->fields =& $cache_data->fields;
			$result->rows =& $cache_data->rows;
			$result->count =& $cache_data->count;
		}
		else { $result = false; }

		$this->last_result = $result;
		if ($result !== false && $this->collector !== null) { call_user_func($this->collector, $this->last_result); }

		return $this->last_result;
	}

	// Основной метод для фетча данных - работает с кэшем - на вход (результат запроса)
	function fetch($result)
	{
		if (!$this->_is_result($result)) { return false; }

		$start = microtime(true);
		// Если запрос закэширован
		if ($result->cached)
		{
			if (array_key_exists($result->position, $result->rows))
			{ $r = $result->rows[$result->position]; } else { $r = false; }
		}
		// Иначе его нужно считать обычным образом
		else
		{
			$r = $this->_fetch($result->resource);
			if ($r !== false)
			{
				foreach ($result->unescape_fields as $key => $callback)
				{ $r[$key] = call_user_func($callback, $r[$key]); }
			}
		}
		$finish = microtime(true);

		// Если считали
		if ($r !== false)
		{
			// Прибавляем время фетча данной строки в статистику
			$result->fetch_time += ($finish - $start);
			// Получаем следующий номер строки которую будет нужно считать
			$result->position++;
			return $r;
		}

		return false;
	}

	// Выборка одной строки запроса
	function select_row()
	{
		$args = func_get_args();
		$result = call_user_func_array(array(&$this, "query"), $args);
		if ($result !== false && false !== $r = $this->fetch($result)) { return $r; }
		else { return false; }
	}

	/*
	Выбор массива всех строк запроса, итоговый массив можно модифицировать передав специальные параметры.

	Ключ "@key" указывает способ для выбора индексов элемента массива на выходе.
	Если данный ключ не указан, по-умолчанию все элементы нумернуются по порядку начиная с индекса 0.
	Если данный ключ указан, то он должен содержать имя поля, которое будет использоваться в качестве индекса каждого элемента.

	Например:

	$peoples = select_all("SELECT id, name FROM peoples", array("@key" => "id"));

	На выходе получим что-то вроде:

	$peoples = array
	(
		143 => array("id" => 143, "name" => "Mike"),
		174 => array("id" => 174, "name" => "Angela"),
		185 => array("id" => 185, "name" => "Antony"),
	);

	Также данным ключём можно формировать более сложные вложенные структуры передав несколько имён полей через запятую.

	К примеру у нас имеется список людей, и он расформирован по группам, в итоге мы хотим получить список всех групп и для каждой
	из них список людей входящих в данную группу, для получения данного списка можно использовать этот же ключ "@key" следующий образом.

	$peoples = select_all("SELECT id, group_id, name FROM peoples", array("@key" => "group_id,id"));

	На выходе получим что-то вроде:

	$peoples = array
	(
		5 => array
		(
			143 => array("id" => 143, "group_id" => 5, "name" => "Mike"),
			174 => array("id" => 174, "group_id" => 5, "name" => "Angela"),
		),
		18 => array
		(
			185 => array("id" => 185, "group_id" => 18, "name" => "Antony"),
		)
	);

	Обратите внимание что если указанные ключи в выборке не будут являться уникальными, то каждая последующая запись будет
	перезатирать любую встретившуюся предыдущую. На деле это будет выглядеть так.

	$peoples = select_all("SELECT id, group_id, name FROM peoples", array("@key" => "group_id"));

	На выходе получим:

	$peoples = array
	(
		5 => array("id" => 174, "group_id" => 5, "name" => "Angela"),
		18 => array("id" => 185, "group_id" => 18, "name" => "Antony"),
	);

	В данном примере строка array("id" => 143, "group_id" => 5, "name" => "Mike") была перезатёрта строкой
	array("id" => 174, "group_id" => 5, "name" => "Angela") т.е. обе строки имеют одинаковый group_id по которому
	мы попросили индексировать выходной массив.

	Имя поля для данного ключа может быть более сложным, с указанием конкретных полей, которые должны содержаться в каждом
	элементе массива. В примере выше каждый конечный элемент содержит избыточное поле "group_id" по-сути оно нам не требуется, т.к.
	мы уже его знаем из индекса элемента массива. Чтобы оставить только нужные нам поля, нужно передать ключ "@key" в следующем формате.
	"@key" => "field_name_for_index[ !? (field_name)+ ( | child_nodes_key_name )?]"

	Более понятно видно на примере:

	Вызов с указанием конкретных имён полей
	$peoples = select_all("SELECT id, group_id, name FROM peoples", array("@key" => "group_id,id[id:name]"));

	или вызов с указанием какие поля исключить, оставив все остальные
	$peoples = select_all("SELECT id, group_id, name FROM peoples", array("@key" => "group_id,id[!group_id]"));

	В обоих примерах на выходе получим:

	$peoples = array
	(
		5 => array
		(
			143 => array("id" => 143, "name" => "Mike"),
			174 => array("id" => 174, "name" => "Angela"),
		),
		18 => array
		(
			185 => array("id" => 185, "name" => "Antony"),
		)
	);

	Бывают случаи когда требуется оставить информацию о группе, но всё же сформировать многомерную структуру, это можно сделать
	используя в вызове параметр "@key" с указанием child_nodes_key_name (как описано в формате выше).

	Более понятно на примере:

	$peoples = select_all("
	SELECT
		p.id,
		p.group_id,
		g.name as group_name,
		p.name
	FROM
		peoples p
		JOIN groups g ON g.id = p.group_id
	", array("@key" => "group_id[group_name | peoples],id[id:name]"));

	На выходе мы получим следующий массив:

	$peoples = array
	(
		5 => array
		(
			"group_name" => "Distributors",
			"peoples" => array
			(
				143 => array("id" => 143, "name" => "Mike"),
				174 => array("id" => 174, "name" => "Angela"),
			)
		),
		18 => array
		(
			"group_name" => "Clients",
			"peoples" => array
			(
				185 => array("id" => 185, "name" => "Antony"),
			)
		)
	);

	Ключ "@parent_key" - имя поля, которое указывает какая ветка является родительской, используется для возможности
	выбора многомерной структуры являющейся бесконечно вложенным деревом.

	Пример:
	$groups = select_all("SELECT id, parent_id, name FROM groups", array("@parent_key" => "parent_id"));

	На выходе мы получим:

	$groups = array
	(
		1 => array
		(
			"id" => 1,
			"parent_id" => null,
			"name" => "Products",
			"child_nodes" => array
			(
				2 => array
				(
					"id" => 2,
					"parent_id" => 1,
					"name" => "Vegetables",
					"child_nodes" => array()
				),
				3 => array
				(
					"id" => 3,
					"parent_id" => 1,
					"name" => "Fruits",
					"child_nodes" => array()
				),
			)
		),
		4 => array
		(
			"id" => 4,
			"parent_id" => null,
			"name" => "Accessories",
			"child_nodes" => array
			(
				5 => array
				(
					"id" => 5,
					"parent_id" => 4,
					"name" => "Bottles",
					"child_nodes" => array()
				),
			)
		)
	);

	При такой выборке добавляется специальное поле "child_nodes" которое содержит все дочерние элементы текущего элемента.
	Если вы желаете изменить имя данного поля, добавьте к параметрам выборки ключ @child_nodes_key, к примеру так
	"@child_nodes_key" => "subgroups"

	По-умолчанию предполагается что поле с именем "@parent_key" ссылается на поле с именем "id" в таблице, если это не так,
	добавьте к параметрам выборки ключ "@key" с указанием имени поля являющейся primary key. К примеру, так:

	$groups = select_all("SELECT group_id, parent_group_id, name FROM groups", array("@key" => "group_id", "@parent_key" => "parent_group_id"));
	результат данной выборки будет аналогичен как и выше, за исключением имени поля "group_id".

	Также существует возможность выбрать определённую часть/части дерева, указав это передачей ключа "@root_nodes". На примере выше
	если вызов будет выгоядеть так:

	$groups = select_all("SELECT id, parent_id, name FROM groups", array("@parent_key" => "parent_id", "@root_nodes" => "1"));

	то результат будет

	$groups = array
	(
		1 => array
		(
			"id" => 1,
			"parent_id" => null,
			"name" => "Products",
			"child_nodes" => array
			(
				2 => array
				(
					"id" => 2,
					"parent_id" => 1,
					"name" => "Vegetables",
					"child_nodes" => array()
				),
				3 => array
				(
					"id" => 3,
					"parent_id" => 1,
					"name" => "Fruits",
					"child_nodes" => array()
				),
			)
		),
	);

	Ключ "@root_nodes" может быть как текстовым с перечислением через запятую id корневых нод, так и массивом, к примеру
	"@root_nodes" => array(1, 5, 7)

	Ключ "@key" также может быть текстовым, а может быть массивом, к примеру эти оба вызова аналогичны:

	"@key" => "group_id[group_name | peoples],id[id:name]"
	и
	"@key" => array
	(
		"group_id[group_name | peoples]",
		"id[id:name]"
	)

	Существует вспомогательная функция входящая во фреймворк: tree_to_list() с её помощью можно формировать
	из многомерного вложенного дерева обычное сплошным списком, с добавлением ключа "level" указывающего на уровень
	вложенности. Подробнее о функции читайте в _helpers.php
	*/
	function select_all()
	{
		$args = func_get_args();
		$result = call_user_func_array(array(&$this, "query"), $args);
		$rows = array();
		if ($result !== false)
		{
			$params = $result->select_params;
			// Вытащим первую строку чтобы проверить наличие необходимых полей
			$r = $this->fetch($result);
			if ($r === false) { return $rows; }

			$id_key = null;
			$parent_key = null;
			$root_nodes = isset($params["@root_nodes"]) ? $params["@root_nodes"] : array();

			$keys = isset($params["@key"]) ? $params["@key"] : array();

			$column = isset($params["@column"]) ? $params["@column"] : false;
			if ($column === null) { reset($r); $column = key($r); }
			if ($column !== false && !array_key_exists($column, $r))
			{ return $this->run_error("Cannot use field name \"" . $column . "\" for resulting array: field not available"); }

			$child_nodes_key = (isset($params["@child_nodes_key"]) && is_scalar($params["@child_nodes_key"])) ? $params["@child_nodes_key"] : "child_nodes";

			if (isset($params["@parent_key"]))
			{
				$parent_key = $params["@parent_key"];
				if (!array_key_exists($parent_key, $r))	{ return $this->run_error("Cannot use field name \"" . $parent_key . "\" for resulting array: field not available"); }
				$id_key = isset($keys[0]) ? preg_replace("/^([^[]+).*$/sux", "$1", $keys[0]) : "id";
				if (!array_key_exists($id_key, $r))	{ return $this->run_error("Cannot construct tree by parent field name \"" . $parent_key . "\": \"" . $id_key . "\" field not available"); }
			}

			$relations = array();
			while (false !== $r)
			{
				// Если попросили сконструировать дерево
				if ($parent_key !== null)
				{
					// Сохраняем информацию о всех ветках
					$rows[$r[$id_key]] = $r;
					// Если нет записи об этой ветке как о родителе - создаём пустую
					if (!isset($relations[$r[$id_key]])) { $relations[$r[$id_key]] = array(); }
					// Сохраняем информацию о том кто родитель какой ветки
					$relations[$r[$parent_key]][$r[$id_key]] =& $rows[$r[$id_key]];
					// Делаем ссылку в ключе дочерних веток на список дочерних веток для текущей
					$rows[$r[$id_key]][$child_nodes_key] =& $relations[$r[$id_key]];
				}
				elseif (count($keys)) { $this->construct_keys_path($rows, $r, $keys, $column); }
				else { $rows[] = ($column === false ? $r : $r[$column]); }
				$r = $this->fetch($result);
			}

			// Если просили создать дерево веток
			if ($parent_key !== null)
			{
				$final_rows = array();
				foreach ($rows as $row)
				{
					// В финальном списке мы должны оставить только те узлы, родитель которых не существует
					// во всём списке наших нод, т.к. ноды для которых нет родителя являются корневыми.
					// Или же если явно запросили отобрать конкретные корневые ноды указав их id то оставляем только их.
					if
					(
						!count($root_nodes) && !isset($rows[$row[$parent_key]])
						||
						(count($root_nodes) && in_array($row[$id_key], $root_nodes))
					)
					{ $final_rows[$row[$id_key]] = $row; }
				}
				$rows = $final_rows;
			}
		}
		return $rows;
	}

	function select_cell()
	{
		$args = func_get_args();
		$result = call_user_func_array(array(&$this, "query"), $args);

		if ($result !== false && false !== $r = $this->fetch($result))
		{
			$params = $result->select_params;
			$key = null;
			if (array_key_exists("@key", $params))
			{
				if (!array_key_exists($params["@key"][0], $r)) { return $this->run_error("Cannot use field name \"" . $params["@key"][0] . "\" for result: field not available"); }
				$key = $params["@key"][0];
			}
			if ($key !== null) { return $r[$key]; }
			else { return reset($r); }
		}

		return false;
	}

	function select_col()
	{
		$args = func_get_args();
		$result = call_user_func_array(array(&$this, "query"), $args);

		$rows = array();
		if ($result !== false)
		{
			$params = $result->select_params;

			// Вытащим первую строку чтобы проверить наличие необходимых полей
			$r = $this->fetch($result);
			if ($r === false) { return $rows; }

			$keys = isset($params["@key"]) ? $params["@key"] : array();

			$column = isset($params["@column"]) ? $params["@column"] : null;
			if ($column === null) { reset($r); $column = key($r); }
			if ($column !== false && !array_key_exists($column, $r))
			{ return $this->run_error("Cannot use field name \"" . $column . "\" for resulting array: field not available"); }

			while (false !== $r)
			{
				if (count($keys)) { $this->construct_keys_path($rows, $r, $keys, $column); }
				else { $rows[] = ($column === false ? reset($r) : $r[$column]); }
				$r = $this->fetch($result);
			}
		}

		return $rows;
	}

	// Вспомогательная функция для конструирования многомерных массивов по ходу их извлечения
	private function construct_keys_path(&$elem, $r, $keys, $column = false)
	{
		$fields = array();
		foreach ($keys as $key)
		{
			$fields = array();
			$is_inversion = false;
			$child_nodes_key = null;

			if (preg_match("/^(.*)\[ \s* (!)? \s* ([^|]*) \s* (?: \| \s* (.+) )? \]$/sux", $key, $matches))
			{
				$key = $matches[1];
				if (@$matches[2] == "!") { $is_inversion = true; }
				if (is_empty($matches[3])) { $fields = array("*"); } else { $fields = preg_split("/\s*:\s*/su", trim($matches[3])); }
				if (isset($matches[4])) { $child_nodes_key = $matches[4]; }
			}

			if (count($fields) && $is_inversion) { $fields = array_diff(array_keys($r), $fields); }

			if ($key == "" || $key == "[]")
			{
				$elem[] = array(); end($elem);
				$elem =& $elem[key($elem)];
			}
			else
			{
				if (!array_key_exists($key, $r)) { return $this->run_error("Cannot use field name \"" . $key . "\" for resulting array: field not available"); }
				if (!array_key_exists($r[$key], $elem))
				{
					$elem[$r[$key]] = array();
					if (count($fields))
					{
						foreach ($fields as $field_name)
						{
							if ($field_name == "*") { $elem[$r[$key]] = array_merge($elem[$r[$key]], $r); continue; }
							$elem[$r[$key]][$field_name] = (array_key_exists($field_name, $r) ? $r[$field_name] : null);
						}
					}
				}
				$elem =& $elem[$r[$key]];
			}

			// Последующие элементы попросили заносить в элемент с этим ключём
			if ($child_nodes_key !== null)
			{
				if (!array_key_exists($child_nodes_key, $elem)) { $elem[$child_nodes_key] = array(); }
				elseif (!is_array($elem[$child_nodes_key])) { $elem[$child_nodes_key] = array(); }
				$elem =& $elem[$child_nodes_key];
			}
		}

		if ($column !== false) { $elem = $r[$column]; }
		else
		{
			if (count($fields))
			{
				$elem = array();
				foreach ($fields as $field_name)
				{
					if ($field_name == "*") { $elem = array_merge($elem, $r); continue; }
					$elem[$field_name] = (array_key_exists($field_name, $r) ? $r[$field_name] : null);
				}
			}
			else { $elem = $r; }
		}
	}

	/* Функция вставляет запись в таблицу $table
	 * Имена и значения полей беруться из ассоциативного массива $fields
	 * Значения полей автоматически квотируются (antiSQL-injection)
	 * в соотвествии с их типом, квотирование можно отключить передав значение поля
	 * как инстанцию DBConnection_Raw (смотри метод raw()).
	 * Функция возвращает false в случае неудачи и $id вставленной записи если его возможно было получить.
	 */
	function insert($table_name, $fields)
	{
		if (!is_array($fields)) { return $this->run_error("Invalid fields supplied: must be an array"); }

		$this->last_insert_id = null;
		$table_name = trim($table_name);
		$columns = $this->get_field_types($table_name);

		if ($columns !== false)
		{
			$realkeys = array(); $keys = array(); $values = array(); $binds = array();
			foreach ($fields as $key => $value)
			{
				$realkey = strtolower(trim($key));
				$realkeys[] = $realkey; $keys[] = $this->escape_ident($realkey);
				$values[] = $this->_get_marker(@$columns[$realkey]); $binds[] = $value;
			}

			if (count($keys) && count($values))
			{
				$sql = "INSERT INTO " . $this->escape_ident($table_name) . " (" . implode(",", $keys) . ") VALUES (" . implode(",", $values) . ")";
				$this->_fire_triggers($table_name, "before", "insert", null, array());
				$result = $this->_insert(array_merge(array($sql), $binds), $table_name, $realkeys);
				if ($result !== false) { $this->_fire_triggers($table_name, "after", "insert", $result, array()); }
				return $result;
			}
		}
		else { return false; }
	}

	/* Функция обновляет записи в таблице $table_name.
	 * Имена и значения полей беруться из ассоциативного массива $fields
	 * Какие записи обновлять - добавляется третьим параметром в $cond
	 * ВНИМАНИЕ! Если третий параметр пустой то обновятся ВСЕ записи
	 * т.к. условие WHERE просто будет отсутствовать.
	 * Возвращается количество обновлённых строк или false в случае неудачи
	 */
	function update($table_name, $fields, $cond = array())
	{
		if (!is_array($fields)) { return $this->run_error("Invalid fields supplied: must be an array"); }
		list($table_name, $separator, $table_alias) = $this->split_ident($table_name);
		$columns = $this->get_field_types($table_name);
		if ($columns !== false)
		{
			$values = array(); $binds = array();
			foreach ($fields as $key => $value)
			{
				$realkey = strtolower(trim($key));
				$values[] = $this->escape_ident($realkey) . " = " . $this->_get_marker(@$columns[$realkey]);
				$binds[] = $value;
			}
			if (count($values))
			{
				$sql = "UPDATE " . $this->escape_ident($table_name) . $separator . ($table_alias !== false ? $this->escape_ident($table_alias) : false) . " SET " . implode(",", $values);
				if (count($cond)) { $sql .= " ?filter"; $binds[] = $cond; }
				$this->_fire_triggers($table_name, "before", "update", null, $cond);
				$result = call_user_func_array(array(&$this, "query"), array_merge(array($sql), $binds));
				$query_result = $result ? $this->affected_rows($result) : false;
				if ($result !== false) { $this->_fire_triggers($table_name, "after", "update", $query_result, $cond); }
				return $query_result;
			}
		}
		else { return false; }
	}

	/* Функция удаляет записи из таблицы $table.
	 * Какие записи обновлять указываются параметром в $cond
	 * ВНИМАНИЕ! Если если параметр $cond пустой то удалятся ВСЕ записи
	 * т.к. условие WHERE просто будет отсутствовать.
	 * Возвращается количество удалёных строк или false в случае неудачи
	 */
	function delete($table_name, $cond = array())
	{
		list($table_name, $separator, $table_alias) = $this->split_ident($table_name);

		$sql = "DELETE FROM " . $this->escape_ident($table_name) . $separator . ($table_alias !== false ? $this->escape_ident($table_alias) : false); $binds = array();
		if (count($cond)) { $sql .= " ?filter"; $binds[] = $cond; }
		$this->_fire_triggers($table_name, "before", "delete", null, $cond);
		$result = call_user_func_array(array(&$this, "query"), array_merge(array($sql), $binds));
		$query_result = $result ? $this->affected_rows($result) : false;
		if ($result !== false) { $this->_fire_triggers($table_name, "after", "delete", $query_result, $cond); }

		return $query_result;
	}

	/* Устанавливает пользовательскую функцию $callback которая будет вызываться до или после ($when - "after", "before")
	 * insert, update или delete операций связанных с таблицей $table
	 * Callback-функция на вход будет получать параметры:
	 * $table - имя таблицы
	 * $when - до или после ("after" или "before")
	 * $event - произошедшее событие - ("insert", "update" или "delete")
	 * $result - в случае "before" - null, в случае "after" - результат выполнения запроса, для insert
	 * это будет значение поля primary key, для update и delete - количество обновлённых (удалённых) строк
	 * $where - условие, переданное как условие для обновления (для update и delete)
	 * Нужно учесть что вызов этой функции будет гарантирован только при использовании
	 * методов insert, update и delete, а также при точном совпадении имени таблицы
	 * передаваемой в add_trigger и эти функции
	 */
	function add_trigger($callback, $table, $when, $events)
	{
		$table = trim($table);
		if (!is_array($events)) { $events = array($events); }
		if ($when != "before" && $when != "after") { return false; }
		foreach ($events as $event)
		{
			if ($event != "insert" && $event != "update" && $event != "delete") { continue; }
			if (@!is_array($this->triggers[$table][$when][$event])) { $this->triggers[$table][$when][$event] = array(); }
			$this->triggers[$table][$when][$event][] = $callback;
		}
	}

	// Убирает все триггеры для указанной таблицы ситуации и события
	function remove_trigger($table, $when, $events)
	{
		$table = trim($table);
		if (!is_array($events)) { $events = array($events); }
		if ($when != "before" && $when != "after") { return false; }
		foreach ($events as $event)
		{
			if ($event != "insert" && $event != "update" && $event != "delete") { continue; }
			unset($this->triggers[$table][$when][$event]);
		}
	}

	function num_rows($result)
	{
		if (!$this->_is_result($result)) { return -1; }
		return $result->count;
	}

	// Affected rows возвращается только на НЕ-SELECT запросах
	// А такие запросы мы не кэшируем, поэтому resource всегда будет определён
	// $result могут и не передать - тогда что возвращать решает метод от драйвера
	function affected_rows($result = null)
	{
		if ($result !== null && !$this->_is_result($result)) { return false; }
		return $this->_affected_rows($result === null ? null : $result->resource);
	}

	function data_seek($result, $offset)
	{
		if (!$this->_is_result($result)) { return false; }

		// Если это закэшированный запрос
		if ($result->cached)
		{
			if ($offset >= $result->count || $offset < 0) { return false; }
			$result->position = $offset;
		}
		else { return @$this->_result_seek($result->resource, $offset); }

		return true;
	}

	function set_cache_handlers($fetch_handler, $store_handler)
	{
		if (!$fetch_handler || !$store_handler) { $this->cache_handler = null; }
		elseif (!is_callable($fetch_handler)) { throw_error("Provided cache fetch handler is not callable", true); }
		elseif (!is_callable($store_handler)) { throw_error("Provided cache store handler is not callable", true); }
		else
		{
			$this->cache_handler["fetch"] = $fetch_handler;
			$this->cache_handler["store"] = $store_handler;
		}
		return true;
	}

	function set_client_encoding($encoding) { $this->_set_client_encoding($encoding); }

	// Функция установки обработчика ошибок базы данных
	// Обработчик ошибок должен вернуть true если успешно обработал ошибку
	// Иначе запустится стандартный обработчик
	function add_error_handler($callback) { array_push($this->error_handlers, $callback); }

	// Удаляет последний обработчик добавленный error_handler
	function clear_error_handler() { if (count($this->error_handlers)) { array_pop($this->error_handlers); } }

	function run_error($mess)
	{
		$this->last_manual_error = $mess;
		$this->_error($mess, true);
		return false;
	}

	function last_manual_error() { return $this->last_manual_error; }

	function last_query() { return $this->last_query; }
	function last_result() { return $this->last_result; }

	// Внимание возвращается id если он был определён после последнего успешно
	// выполненного метода "insert" - если INSERT был выполнен на прямую через query
	// то вернётся не то что вы ожидаете
	function last_insert_id() { return $this->last_insert_id; }

	// Функция возвращает true если переданное значение является просто именем (идентификатором)
	// это может быть имя таблицы или имя поля например, иначе возвращает false
	function is_ident($val)
	{
		// Имя поля может быть например email, A."name" или "users"."field" или "public".table."field"
		// Ищем первый правильный кусок в начале строки, если находим
		// То вырезаем его и смотрим следующий за ним символ - если это точка - вырезаем её
		// и повторяем итерацию, иначе если это пустая строка - то возвращаем true (верный идентификатор)
		// иначе false (не верный идентификатор)
		$val = trim($val);
		while (true)
		{
			if (preg_match('/^ ( ' . $this->IDENT_QUOTED_REGEX . ' | ' . $this->IDENT_UNQUOTED_REGEX . ' )/sixu', $val, $matches))
			{
				$val = substr($val, strlen($matches[0]));
				if (substr($val, 0, 1) == ".") { $val = substr($val, 1); continue; }
				if (!strlen($val)) { return true; }
				break;
			}
			break;
		}
		return false;
	}

	// Функция разбивает выражение "users as u" на три элемента
	// первый - имя таблицы, второй - разделитель, третий алиас
	// если алиас не указан - возвращается только первый элемент, остальные - false
	// Например если на вход передать "users as u" вернётся массив array("users", " as ", "u")
	function split_ident($val)
	{
		if (preg_match("/^ \s* (.+) (\s+[aA][sS]\s+) ([^.]+) \s* $/sxu", $val, $regs)) { return array($regs[1], $regs[2], $regs[3]); }
		elseif (preg_match("/^ \s* (.+) (\s+) ([^.]+) \s* $/sxu", $val, $regs)) { return array($regs[1], $regs[2], $regs[3]); }
		else { return array($val, false, false); }
	}

	// Возвращает ресурс соединения с дб (если не соединены - пытается соединится)
	// По возможности нужно везде использовать этот метод для получения ресурса соединения
	// Внимание! Не используйте вызов внутри вызова функций для которых используется подавление ошибок!
	// Т.к. при ошибке соединения с БД вы никогда не увидите данную ошибку!
	function link()
	{
		static $is_recursion = false;
		if (!$this->link && !$is_recursion)
		{
			$saved_query = $this->last_query;
			$is_recursion = true;
			$this->link = $this->connect();
			$is_recursion = false;
			$this->last_query = $saved_query;
		}
		return $this->link;
	}

	// Стандартное экранирование строки
	final function escape($s) { return $this->_escape_str($s); }

	// Стандартное экранирование бинарных данных
	final function escape_binary($s) { return $this->_escape_binary($s); }

	// Имя идентификатора может быть разбито точками
	final function escape_ident($s)
	{
		$chunks = explode(".", strtolower($s));
		foreach ($chunks as $i => $chunk) { $chunks[$i] = $this->IDENT_QUOTE_CHAR . str_replace($this->IDENT_QUOTE_CHAR, $this->IDENT_QUOTE_CHAR . $this->IDENT_QUOTE_CHAR, $chunk) . $this->IDENT_QUOTE_CHAR; }
		return implode(".", $chunks);
	}

	final function escape_list($arr, $type = "str")
	{
		if (!method_exists($this, $type)) { $type = "str"; }
		$arr = is_array($arr) ? $arr : array($arr);
		if (!count($arr)) { return "NULL"; }
		foreach ($arr as $i => $val) { $arr[$i] = call_user_func(array(&$this, $type), $val); }
		return implode(", ", $arr);
	}

	// Возвращает объект RAW-значения - используется в некоторых методах для передачи значений "как есть"
	final function raw($value) { return new DBConnection_Raw($value); }

	function str($str)
	{
		if ($str instanceof DBConnection_Raw) { return $str->__toString(); }
		return !is_empty($str) ? "'" . $this->escape(trim($str)) . "'" : "NULL";
	}

	function blob($str)
	{
		if ($str instanceof DBConnection_Raw) { return $str->__toString(); }
		return strlen($str) ? "'" . $this->escape_binary($str) . "'" : "NULL";
	}

	function bool($str)
	{
		if ($str instanceof DBConnection_Raw) { return $str->__toString(); }
		return trim($str) ? "1" : "0";
	}

	function int($str)
	{
		if ($str instanceof DBConnection_Raw) { return $str->__toString(); }
		$str = strval($str);
		return Valid::integer($str) ? trim($str) : "NULL";
	}

	function real($str)
	{
		if ($str instanceof DBConnection_Raw) { return $str->__toString(); }
		return Valid::float($str) ? "'" . trim($str) . "'" : "NULL";
	}

	function date($str)
	{
		if ($str instanceof DBConnection_Raw) { return $str->__toString(); }
		return !Valid::date($str) ? "NULL" : "'" . Date::format($str, "Y-m-d") . "'";
	}

	function datetime($str)
	{
		if ($str instanceof DBConnection_Raw) { return $str->__toString(); }
		return !Valid::date($str) ? "NULL" : "'" . Date::format($str, "Y-m-d H:i:s") . "'";
	}

	function time($str)
	{
		if ($str instanceof DBConnection_Raw) { return $str->__toString(); }
		return !Valid::date($str) ? "NULL" : "'" . Date::format($str, "H:i:s.u") . "'";
	}

	function markers_count($sql)
	{
		static $cache = array();
		if (!isset($cache[$sql]))
		{
			$lexemes = $this->_get_lexemes($sql);
			$count = 0;
			foreach ($lexemes as $lexeme)
			{
				list($type, $chunk) = $lexeme;
				if ($type == "PLACEHOLDER") { $count++; }
			}
			$cache[$sql] = $count;
		}
		return $cache[$sql];
	}

	function make_sql($sql, $bind_values = array())
	{
		$lexemes = $this->_get_lexemes($sql);
		$final_sql = null;
		foreach ($lexemes as $lexeme)
		{
			list($type, $chunk) = $lexeme;

			if ($type == "PLACEHOLDER")
			{
				$bind_type = $this->bind_markers[$chunk];
				// Получаем значение для этого параметра
				$bind_value = each($bind_values);
				if ($bind_value === false)
				{
					$error_message = "Bind parameters mismatch, need: " . $this->markers_count($sql) . ", provided: " . count($bind_values);
					if ($sql != $this->last_query) { $error_message .= "\nSQL Fragment: " . $sql; }
					$this->run_error($error_message);
				}
				else
				{
					$chunk = $bind_value["value"];
					if ($bind_type == "*FILTER*") { $chunk = $this->get_filter($chunk); }
					elseif ($bind_type == "*EXPRESSION*")
					{
						$expression = $this->get_filter($chunk, array("expression"));
						if (!is_empty($expression)) { $chunk = "(" . $expression . ")"; } else { $chunk = null; }
					}
					else { $chunk = is_array($chunk) ? $this->escape_list($chunk, $bind_type) : call_user_func(array(&$this, $bind_type), $chunk); }
				}
			}

			$final_sql .= $chunk;
		}
		return $final_sql;
	}

	/**
	* Функция из переданного массива создаёт SQL строку.
	* Включаемые в фильтр условия зависят от второго параметра.
	* По-умолчанию метод создаёт полную SQL строку WHERE ... GROUP BY ... HAVING ... ORDER BY ... LIMIT ... OFFSET ...
	* Пример:
	* array
	* (
	*   "id" => 50,
	*	"time = NOW()",
	*	"name = ? or email = ?" => array("Roman", "test@test.ru"),
	*	"size > ?" => 500
	* )
	* Вернёт: array
	* (
	* 	"WHERE id = ? AND (time = NOW()) AND (name = ? or email = ?) AND size > ?",
	*	array(50, "Roman", "test@test.ru", 500)
	*/
	function get_filter($array, $parts = array("where", "group", "having", "order", "limit"))
	{
		if (!is_array($array)) { $array = array($array); }

		$having_cond = isset($array["@having"]) ? (array) $array["@having"] : array();
		$order_cond = isset($array["@order"]) ? $array["@order"] : null;
		$group_cond = isset($array["@group"]) ? $array["@group"] : null;
		$limit_cond = isset($array["@limit"]) ? $array["@limit"] : null;
		$offset_cond = isset($array["@offset"]) ? $array["@offset"] : null;
		$or_cond = isset($array["@or"]) ? $array["@or"] : false;

		// Убираем все спец.параметры
		foreach ($array as $key => $value) { if (preg_match("/^@[a-zA-Z0-9_]+$/", $key)) unset($array[$key]); }

		$fullsql = null;
		foreach ($parts as $part)
		{
			switch ($part)
			{
				case 'where':
				case 'expression':
				case 'having':
					$items = array();
					if ($part == "having") { $process =& $having_cond; } else { $process =& $array; }
					foreach ($process as $key => $val)
					{
						$key = trim($key);
						if (!strlen($key)) { continue; }
						// Если ключ не числовой - значит это просто имя поля или сложное выражение с bind параметрами
						if (!is_numeric($key))
						{
							// Если ключ является просто именем поля - то переданное значение либо скаляр либо массив
							if ($this->is_ident($key))
							{
								$sql = "$key";
								if (is_array($val)) { $sql .= " IN (" . $this->escape_list($val) . ")"; }
								else
								{
									$escaped = $this->str((string) $val);
									$sql .= $escaped == "NULL" ? " IS NULL" : " = " . $escaped;
								}
							}
							// Если не просто имя, значит составное SQL выражение и скорее всего с bind-параметрами
							// Бинд-маркеры в этом случае выставляет человек, нам нужно лишь дополнить массив $binds
							// Значениями для бинда - получаем их количество и дополняем массив
							else
							{
								$need_bind = $this->markers_count($key);
								// Требуется более одного маркера - параметры ожидаются в виде массива
								if ($need_bind > 1) { $sql = "(" . $this->make_sql($key, (array) $val) . ")"; }
								// Передан ровно один маркер - параметр ожидается в виде строки
								elseif ($need_bind == 1) { $sql = "(" . $this->make_sql($key, array($val)) . ")"; }
								// Ничего не передано - добавляем в конец
								elseif ($need_bind == 0) { $sql = "(" . $key . " " . $this->str($val) . ")"; }
							}
						}
						// Если ключ числовой - и это массив, значит добавляют вложенное sql выражение
						elseif (is_array($val))
						{
							$sql = $this->get_filter($val, array("expression"));
							if (!is_empty($sql)) { $sql = "(" . $sql . ")"; }
						}
						// Иначе это sql выражение переданное простым текстом
						elseif (!is_empty($val)) { $sql = "($val)"; }
						else { $sql = null; }

						if (!is_empty($sql)) { $items[] = $sql; }
					}

					if (count($items))
					{
						if ($part == "expression") { $fullsql .= implode($or_cond ? " OR " : " AND ", $items); }
						else { $fullsql .= " " . strtoupper($part) . " " . implode($or_cond ? " OR " : " AND ", $items); }
					}
					break;

				case 'group':
					$items = array();
					if ($group_cond !== null)
					{
						if (is_array($group_cond)) { $items = $group_cond; }
						else
						{
							if ($group_cond instanceof DBConnection_Raw) { $items = array($group_cond); }
							else { $items = preg_split("/\s*,\s*/u", $group_cond); }
						}
						foreach ($items as $i => $item)
						{
							if ($item instanceof DBConnection_Raw) { $items[$i] = $item->to_string(); }
							else
							{
								$item = trim($item);
								if (!preg_match("/^\d+$/u", $item)) { $item = $this->escape_ident($item); }
								$items[$i] = $item;
							}
						}
					}
					if (count($items)) { $fullsql .= " GROUP BY " . implode(", ", $items); }
					break;

				case 'order':
					$items = array();
					if ($order_cond !== null)
					{
						if (is_array($order_cond)) { $items = $order_cond; }
						else
						{
							if ($order_cond instanceof DBConnection_Raw) { $items = array($order_cond); }
							else { $items = preg_split("/\s*,\s*/u", $order_cond); }
						}
						foreach ($items as $i => $item)
						{
							if ($item instanceof DBConnection_Raw) { $items[$i] = $item->to_string(); }
							else
							{
								$item = trim($item);
								if (preg_match("/^(.*)\s+(asc|desc)$/siu", $item, $regs)) { $order_name = $regs[1]; $order_dir = $regs[2]; }
								else { $order_name = $item; $order_dir = null; }
								if (!preg_match("/^\d+$/u", $order_name)) { $order_name = $this->escape_ident($order_name); }
								$items[$i] = $order_name . ($order_dir ? " " . $order_dir : "");
							}
						}
					}
					if (count($items)) { $fullsql .= " ORDER BY " . implode(", ", $items); }
					break;

				case 'limit':
					$items = array();
					if ($limit_cond !== null) { $items[] = "LIMIT " . $this->int($limit_cond); }
					if ($offset_cond !== null) { $items[] = "OFFSET " . $this->int($offset_cond); }
					if (count($items)) { $fullsql .= " " . implode(" ", $items); }
					break;
			}
		}

		return ltrim($fullsql);
	}

	/********************************************************************************************
	* ПРИВАТНЫЕ ФУНКЦИИ - ИСПОЛЬЗУЮТСЯ ТОЛЬКО САМИМ КЛАССОМ
	********************************************************************************************/

	// Функция обработки ошибок базы данных, вызывается при любой ошибке
	protected function _error($phperror, $is_manual = false)
	{
		$last_query = $this->last_query;

		if ($is_manual) { $db_error = $phperror; }
		else
		{
			$db_error = $this->last_error();
			if (is_empty($db_error)) { $db_error = decode_h(strip_tags($phperror)); }
		}

		$call = $this->_get_caller();

		// Независимо от того включен ли режим отладки или нет - для ошибок всегда ведётся лог
		$log_msg = "SQL ERROR: " . $db_error;
		if (!is_empty($last_query)) { $log_msg .= ", QUERY: " . ltrim_lines($last_query); }
		$log_msg .= " in file " . @$call["file"] . " on line " . @$call["line"];
		$this->_write_to_log($log_msg); unset($log_msg);

		// Если метод был вызван с префиксом "@", т.е. подавление ошибок, то никаких ошибок не обрабатываем
		if (error_reporting())
		{
			// По-умолчанию всегда срабатывает стандартный обработчик
			$is_standard_handler = true;
			if (count($this->error_handlers))
			{
				foreach ($this->error_handlers as $handler)
				{
					// Если обработчик вернул true то стандартный запускать не нужно
					if (call_user_func($handler, $is_manual) === true)
					{ $is_standard_handler = false; }
				}
			}
			if (!$is_standard_handler) { return true; }

			// Если дебажный режим - выводим подробную информацию об ошибке

			$is_console_mode = !isset($_SERVER["GATEWAY_INTERFACE"]);
			$mess = $is_console_mode ? "" : "<xmp>";
			if ($this->get_param("debug_mode") || $is_console_mode)
			{
				$mess .= "DATABASE ERROR: " . $db_error . "\n";
				// Если ошибка вызвана не вручную, показываем последний запрос
				if (!is_empty($last_query))
				{
					$mess .= "</xmp><hr><xmp>";
					$mess .= ltrim_lines($last_query);
					$mess .= "</xmp><hr><xmp>";
				}
				$mess .= (@$call["file"]) . " on line " . @$call["line"] . ".\n\n";
			}
			// Иначе выводим общую ошибку (для усложнения жизни хакерам)
			else { $mess .= "DATABASE ERROR: Use debug mode for detailed description"; }
			$mess .= $is_console_mode ? "" : "</xmp>";
			die($mess);
		}
	}

	protected function _write_to_log($message) { if ($this->logger !== null) call_user_func($this->logger, $message); }
	protected function _is_result($result) { return ($result instanceof DBConnection_Result); }

	// Находим место откуда самый первый раз вызвали метод нашего класса
	protected function _get_caller()
	{
		$trace = debug_backtrace(); $call = null;
		$trace = array_reverse($trace);
		foreach ($trace as $item)
		{
			$class = strtolower(__CLASS__);
			$func = @strtolower($item["function"]);
			if (@strtolower($item["class"]) == $class) { $call = $item; break; }
			if ($func == "call_user_func" || $func == "call_user_func_array")
			{ if (preg_match("/^DBConnection(_|$)/siu", @get_class($item["args"][0][0]))) break; }
			$call = $item;
		}
		return $call;
	}

	// На вход - тип поля, на выход - маркер соотвествующего типа
	protected function _get_marker($type) { return (isset($this->bind_type_markers[$type])) ? $this->bind_type_markers[$type] : "?"; }

	// Регистрация нового маркера
	protected function _register_marker($marker, $type)
	{
		if (array_key_exists($marker, $this->bind_markers)) { throw_error("Marker '" . $marker . "' is already registered", true); }
		$this->bind_markers[$marker] = $type;
		$this->bind_type_markers = array_flip($this->bind_markers);
		uksort($this->bind_markers, function($a, $b) { $al = strlen($a); $bl = strlen($b); if ($al == $bl) { return 0; } return ($al > $bl) ? -1 : 1; });
	}

	protected function _strpbrkpos($haystack, $char_list, $offset = 0)
	{
		$result = strcspn($haystack, $char_list, $offset);
		if (($result + $offset) != strlen($haystack)) { return $result + $offset; }
	    return false;
	}

	/* Функция на вход принимает текст запроса с возможными вставками bind-параметров
	 * и на выход возвращает массив состоящий из массивов с двумя элементами, первый
	 * из которых сообщает тип содержания, которое находится во втором параметре
	 * Тип содержания бывает следующим:
	 * null - отрезок текста не относящегося ни к одному из типов лексем
	 * IDENTIFIER - идентификатор заключённый в квотирующие кавычки
	 * STRING - строка заключённая в одинарные кавычки
	 * PLACEHOLDER - простой маркер
	 * NAMED_PLACEHOLDER - именованный маркер
	 * callback-функцию принимает первым параметром тип сущности и вторым параметром само её значение
	 */
	protected function _get_lexemes($sql)
	{
		static $markers_regexp = null;
		$lexemes = array();
		while (false !== $pos = $this->_strpbrkpos($sql, "'?:" . $this->IDENT_QUOTE_CHAR))
		{
			$lexemes[] = array(null, substr($sql, 0, $pos));
			$sql = substr($sql, $pos);
			switch ($sql[0])
			{
				case "'":
					if (preg_match("/^ (' (?: [^'] | '' )* ')/sux", $sql, $m))
					{ $lexemes[] = array("STRING", $m[1]); $sql = substr($sql, strlen($m[1])); }
					else { $lexemes[] = array("STRING", $sql); $sql = null;	}
					break;

				case $this->IDENT_QUOTE_CHAR:
					$quote = $this->IDENT_QUOTE_CHAR;
					if (preg_match("/^ ($quote (?: [^$quote] | $quote{2} )* $quote)/sux", $sql, $m))
					{ $lexemes[] = array("IDENTIFIER", $m[1]); $sql = substr($sql, strlen($m[1])); }
					else { $lexemes[] = array("IDENTIFIER", $sql); $sql = null;	}
					break;

				case "?":
					if (!$markers_regexp)
					{
						$markers = array();
						foreach ($this->bind_markers as $marker => $type) { $markers[] = preg_quote($marker, "/"); }
						$markers_regexp = implode("|", $markers);
					}
					if (preg_match("/^ ($markers_regexp) /sux", $sql, $m)) { $bind_marker = $m[1]; } else { $bind_marker = "?"; }
					$lexemes[] = array("PLACEHOLDER", $bind_marker);
					$sql = substr($sql, strlen($bind_marker));
					break;

				case ":":
					if (substr($sql, 1, 1) == ":")
					{
						$lexemes[] = array(null, "::");
						$sql = substr($sql, 2);
					}
					elseif (preg_match("/^ (:[a-zA-Z][a-zA-Z0-9_]*) /sux", $sql, $m))
					{
						$bind_marker = $m[1];
						$lexemes[] = array("NAMED_PLACEHOLDER", $bind_marker);
						$sql = substr($sql, strlen($bind_marker));
					}
					else
					{
						$lexemes[] = array(null, ":");
						$sql = substr($sql, 1);
					}
					break;

			}
		}
		if (strlen($sql)) { $lexemes[] = array(null, $sql); }
		return $lexemes;
	}

	// Запускает триггеры назначенные для указанной таблицы и событий
	// Вручную вызывать данный метод не стоит - поэтому он protected :)
	protected function _fire_triggers($table, $when, $event, $result, $cond = array())
	{
		if (($when == "before" || $when == "after") && ($event == "insert" || $event == "update" || $event == "delete"))
		{
			$table = trim($table);
			if (isset($this->triggers[$table][$when][$event]))
			{
				foreach ($this->triggers[$table][$when][$event] as $callback)
				{
					if (is_callable($callback))
					{ call_user_func($callback, $table, $when, $event, $result, $cond); }
				}
			}
		}
	}

	/* Функция получает набор аргументов передаваемых методам "select_*" или "query"
	 * На выход функция возвращает массив:
	 * первый элемент - текст sql запроса с бинд-маркерами
	 * второй элемент - массив параметров для бинда в текст запроса, включая именованные параметры
	 * третий элемент - параметры для выборки такие как "@key", "@col" и прочее (используются методами select_*)
	 * Примеры для функции select_all:
	 * select_all("SELECT * FROM table", array("id" => 500, "@key" => "id"))
	 * select_all("SELECT * FROM table WHERE id = ?", 500, array("@key" => "id"))
	 * select_all("SELECT id, ? as test FROM table", "Hello", array("id" => 500, "@key" => "id"))
	 * Если обощить, то первым параметром всегда передаётся текст запроса
	 * Если текст запроса содержит bind-маркеры, то следующие аргументы являются параметрами для бинда
	 * их должно быть не менее чем количество bind-маркеров.
	 * И последний аргумент (следующий за последним нужным бинд-параметром) может быть передан и должен быть массивом.
	 * Означает что мы хотим добавить доп. условие (WHERE) в конец текста запроса переданного запроса
	 * плюс в него можно передать спец параметры вроде "@key", "@offset".
	 * Также именно в него передаются значения для именованных параметров бинда.
	 */
	protected function _extract_arguments($args)
	{
		if (!isset($args[0])) { $args[0] = null; }

		// Полный текст запроса
		$full_sql = $args[0];

		// Оставшиеся элементы - бинд-параметры и/или дополнительное выражение
		$bind_values = array_slice($args, 1);

		// Количество переданных аргументов
		$provided_count = count($bind_values);

		// Параметры выборки
		$select_params = array();

		// Требуется ли какая-либо обработка самого текста запроса
		$is_raw_query = ($full_sql instanceof DBConnection_Raw);

		// Число маркеров, которое нужно биндить в запрос
		$markers_count = ($is_raw_query ? 0 : $this->markers_count($full_sql));

		// Если количество переданных параметров больше или равно количеству требуемых, это означает
		// что последним аргументом вероятно передали доп.условие. Выделим его, а также попутно выделим все дополнительные ключи для выборки.
		if ($provided_count > $markers_count)
		{
			$additional_condition = (array) end($bind_values);
			$bind_values = array_slice($bind_values, 0, $markers_count);

			foreach (array("@key", "@root_nodes") as $key_name)
			{
				if (array_key_exists($key_name, $additional_condition))
				{
					$select_params[$key_name] = $additional_condition[$key_name];
					unset($additional_condition[$key_name]);
					if (!is_array($select_params[$key_name])) { $select_params[$key_name] = preg_split("/\s*,\s*/sux", trim($select_params[$key_name])); }
				}
			}

			foreach (array("@column", "@parent_key", "@child_nodes_key") as $key_name)
			{
				if (array_key_exists($key_name, $additional_condition))
				{
					if (is_scalar($additional_condition[$key_name])) { $select_params[$key_name] = $additional_condition[$key_name]; }
					unset($additional_condition[$key_name]);
				}
			}

			if (!$is_raw_query && count($additional_condition))
			{
				if (!preg_match("/\s$/", $full_sql)) { $full_sql .= " "; }
				$full_sql .= "?filter";
				$bind_values[] = $additional_condition;
			}
		}

		return array($full_sql, $bind_values, $select_params);
	}

	// Возвращает параметры запроса, нужно ли его кэшировать, а также id и tags запроса если они указаны
	protected function _parse_params($sql)
	{
		$is_need_cache = false;
		$cache_id = null;
		$cache_time = 60;
		$cache_tags = array();
		// Просят ли кэшировать данный запрос.
		// В САМОМ НАЧАЛЕ тела запроса должен быть комментарий в виде
		// -- cache или -- cache=[число] или /* cache */ или /* cache=[число] */
		// Также возможны дополнительные указания на тэги кэша перечисленные через запятую
		// к примеру -- cache tags=user_1333,category_11223
		// Эти тэги также будут переданы в обработчик кэша и в дальнейшем могут быть использованы для
		// удаления кэш-записей связанных с указанными тэгами
		if
		(
			preg_match("~^ \s* -- \s+ (\b cache \b .*) (\n | $)~sixu", $sql, $regs)
			|| preg_match("~^ \s* /\* \s+ (\b cache \b .*) \*/ ~sixu", $sql, $regs)
		)
		{
			$cache_string = $regs[1];

			$cache_id = null;
			$cache_time = 60;
			$cache_tags = array();

			if (preg_match("/\b id \b \s* = \s* ([a-z0-9_-]+)/sixu", $cache_string, $regs)) { $cache_id = $regs[1]; }
			if (preg_match("/\b cache \b \s* = \s* (\d+)/sixu", $cache_string, $regs)) { $cache_time = intval($regs[1]); }
			if (preg_match("/\b tags \b \s* = \s* ([a-z0-9_]+ (?: \s* , \s* [a-z0-9_-]+)* )/sixu", $cache_string, $regs)) { $cache_tags = preg_split("/\s*,\s*/u", trim($regs[1])); }

			if ($this->cache_handler !== null) { $is_need_cache = true; }
		}

		return array($is_need_cache, $cache_id, $cache_time, $cache_tags);
	}
}

class DBConnection_Result
{
	public $sql = null;
	public $position = 0;
	public $count = 0;
	public $rows = array();
	public $exec_time = 0;
	public $fetch_time = 0;
	public $file = null;
	public $line = null;
	public $resource = null;
	public $performed = false;
	public $cached = false;
	public $fields = array();
	public $unescape_fields = array();
	public $select_params = array();
}

// Специальный класс - используется для передачи значений "как есть" в таких функциях
// как insert, update, delete, "@order", "@group" конструкциях
class DBConnection_Raw
{
	private $value = null;
	function __construct($raw) { $this->value = $raw; }
	function __toString() { return $this->value; }
	function to_string() { return $this->__toString(); }
}