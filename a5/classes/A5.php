<?php
class A5
{
	static public $debug = null;
	static public $cron_jobs = null;
	static public $error_reporting = null;

	static private $sql_executed = array();
	static private $sql_exec_time = 0;
	static private $sql_fetch_time = 0;
	static private $config_files = array();
	static private $config_params = array();
	static private $databases = array();
	static private $urlmapping = array();
	static private $output_data_type = null;
	static private $request_params = null;
	static private $render_view = null;
	static private $controller_path = null;
	static private $layout_path = null;
	static private $controller_vars = null;
	static private $render_vars = array();
	static private $included_views = array();
	static private $layout = null;
	static private $controller = null;
	static private $action = null;
	static private $url_for_defaults = array("@escape" => true, "@jsescape" => false, "@overwrite" => false);
	static private $current_context = null;
	static private $url_separators = array("/", ".");
	static private $memcache_engine = null;

	// При первом вызове устаналивает текущее время, при последующих возвращает его
	// Это чисто системная функция для дебаг-информации, не используйте её :)
	static function finish_time()
	{
		static $finish_time = null;
		return $finish_time === null ? ($finish_time = microtime(true)) : $finish_time;
	}

	static private function memcache_get_engine()
	{
		if (self::$memcache_engine === null)
		{
			if (extension_loaded("xcache")) { self::$memcache_engine = "xcache"; }
			elseif (extension_loaded("eaccelerator") && is_callable("eaccelerator_get") && is_callable("eaccelerator_put")) { self::$memcache_engine = "eaccelerator"; }
			elseif (extension_loaded("apc")) { self::$memcache_engine = "apc"; }
			else { self::$memcache_engine = false; }
		}
		return self::$memcache_engine;
	}

	static private function memcache_get($key)
	{
		$engine = self::memcache_get_engine();
		$data = null;
		if ($engine !== false)
		{
			switch ($engine)
			{
				case "xcache": $data = @xcache_get($key); break;
				case "eaccelerator": $data = @eaccelerator_get($key); break;
				case "apc": $data = @apc_fetch($key); break;
			}
			$data = ($data ? unserialize($data) : null);
			return $data;
		}
		return $data;
	}

	static private function memcache_store($key, $data, $time = 0)
	{
		$engine = self::memcache_get_engine();
		if ($engine !== false)
		{
			switch ($engine)
			{
				case "xcache": return @xcache_set($key, serialize($data), $time); break;
				case "eaccelerator": return @eaccelerator_put($key, serialize($data), $time); break;
				case "apc": return @apc_store($key, serialize($data), $time); break;
			}
		}
		return false;
	}

	static function read_ini_file($path, $is_use_sections = true)
	{
		$config = array();
		$config_name = normalize_path($path);
		if (!file_exists($config_name) || !is_readable($config_name)) { return false; }

		self::$config_files[] = $config_name;
		$lines = @file($config_name);
		$section = null;
		if ($lines !== false)
		{
			while (list($i, $line) = each($lines))
			{
				$line = trim($line);
				$first_char = substr($line, 0, 1);
				// Игнорируем комментарии и пустые линии
				if ($first_char == ";" || $line == "") { continue; }
				if ($is_use_sections && preg_match("/^\[(.*)\]$/", $line, $regs))
				{
					$section = strtolower($regs[1]);
					if (@!is_array($config[$section])) { $config[$section] = array(); }
				}
				elseif (preg_match('/^\s* (.+?) (?: \s*=\s* (.*) \s* )? $/sx', $line, $regs))
				{
					$key = strtolower($regs[1]);

					$parts = preg_split('/ ("(?:[^"]|\\")*" | \s * [A-Z][A-Z0-9_]* \s* ) /sx', @$regs[2], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
					$value = null; $is_quoted = false;
					foreach ($parts as $part)
					{
						if (preg_match("/^ (\s*) ([A-Z][A-Z0-9_]*) (\s*) $/sx", $part, $regs) && defined($regs[2])) { $value .= constant($regs[2]); $is_quoted = true; continue; }
						if (substr($part, 0, 1) == '"') { $part = str_replace('\\"', '"', substr($part, 1, -1)); $value .= $part; $is_quoted = true; continue; }
						$value .= $part;
					}

					if (!$is_quoted)
					{
						switch (strtolower($value))
						{
							case "true": case "yes": case "on": $value = 1; break;
							case "false": case "no": case "off": $value = 0; break;
							case "null": $value = null; break;
						}
					}

					if ($key == "include_config")
					{
						$include_config_name = $value;
						if (!is_absolute_path($value)) { $include_config_name = dirname($config_name) . "/" . $include_config_name; }
						$merge = self::read_ini_file($include_config_name, $is_use_sections);
						if ($merge !== false) { $config = array_merge($config, $merge); }
						continue;
					}
					elseif (preg_match("/_config$/", $key) && (is_scalar($value) || $value === null))
					{
						if (!is_absolute_path($value)) { $value = dirname($config_name) . "/" . $value; }
						$value = normalize_path($value);
					}
					elseif (preg_match("/_(dir|file)$/", $key) && (is_scalar($value) || $value === null))
					{
						if (!is_absolute_path($value)) { $value = PUBLIC_DIR . "/" . $value; }
						$value = normalize_path($value);
					}

					if ($section !== null) { $config[$section][$key] = $value; } else { $config[$key] = $value; }
				}
			}
		}
		return $config;
	}

	// Функция установки дефолтных конфиг-параметров и считывания пользовательских конфиг-файлов
	static function config_read_ini_files()
	{
		$cache_key = md5("configs_" . APP_DIR);
		if (null === $cached_data = self::memcache_get($cache_key))
		{
			self::$config_files = array();
			self::$config_params = array();
			self::$databases = array();
			self::$urlmapping = array();

			$config =& self::$config_params;

			// Время хранения конфигурации в памяти с помощью APC (для исключения считывания конфигов при каждом запуске скриптов)
			// Используеться только если APC-модуль присутствует
			$config["config_cache_time"] = 600;

			// Автоматическое соединение с базами данных при старте
			// true или auto - создавать
			// false, null, 0 и прочее - не создавать
			$config["db_connections"] = true;

			// По умолчанию - отладочный режим
			// В рабочем конфиге нужно устанавливать DEBUG_MODE = 0
			// Во избежании появления подробных сообщений ошибок на сайте
			// Иначе это повышает риск уязвимости сайта хакерам
			// Даный параметр администратор должен устанавливать в "1"
			// ТОЛЬКО по просьбе разработчика, при обнаружении ошибок на рабочей версии
			// сайта - для их устанения.
			$config["debug_mode"] = 1;

			// Вы можете определить debug-файл для вывода туда всех сообщений используя функцию debug (см. debug.php)
			$config["debug_file"] = null;

			// Дефолтный логин и пароль для включения debug-режима на сайте
			$config["debug_login"] = "DebugMode";
			$config["debug_password"] = "DebugMode_3000";

			// Дефолтная локаль - если не указано другого
			$config["default_locale"] = "en_US.UTF-8";

			// Имя домена, для скриптов запускаемых из консоли.
			// Поскольку при запуске скриптов из консоли, домен не
			// определяется, нужен этот параметр.
			// Обязательно ставить слэш в конце!
			// Данный параметр не обязательно прописывать в рабочем конфиг-файле
			// т.к. перед выкладыванием на рабочий сервер разработчик обычно
			// должен проставить здесь рабочий адрес сайта
			$config["console_base_url"] = "http://" . gethostname() . "/";

			// Дефолтное расположение конфига соединений с базой данных
			$config["databases_config"] = normalize_path(APP_DIR . "/config/databases.ini");
			// Дефолтное расположение конфига url-мапов
			$config["urlmapping_config"] = normalize_path(APP_DIR . "/config/urlmapping.ini");

			// Различные системные пути
			$config["classes_dir"] = normalize_path(APP_DIR . "/classes");
			$config["core_classes_dir"] = normalize_path(CORE_DIR . "/classes");
			$config["controllers_dir"] = normalize_path(APP_DIR . "/controllers");
			$config["helpers_dir"] = normalize_path(APP_DIR . "/helpers");
			$config["views_dir"] = normalize_path(APP_DIR . "/views");
			$config["layouts_dir"] = normalize_path(APP_DIR . "/layouts");
			$config["application_controller_file"] = $config["controllers_dir"] . "/application.php";
			$config["application_helper_file"] = $config["helpers_dir"] . "/application.php";

			// Считываем конфиг приложения
			$merge = self::read_ini_file(APP_DIR . "/config/main.ini", false);
			if ($merge !== false) { $config = array_merge($config, $merge); }

			$databases = self::read_ini_file($config["databases_config"]);
			if ($databases !== false) { self::$databases = $databases; }

			$urlmapping = self::read_ini_file($config["urlmapping_config"]);
			if ($urlmapping !== false) { self::$urlmapping = $urlmapping; }

			$cached_data = array
			(
				"config_files" => self::$config_files,
				"config_params" => self::$config_params,
				"databases" => self::$databases,
				"urlmapping" => self::$urlmapping,
			);

			self::memcache_store($cache_key, $cached_data, $config["config_cache_time"]);
		}
		else
		{
			self::$config_files = $cached_data["config_files"];
			self::$config_params = $cached_data["config_params"];
			self::$databases = $cached_data["databases"];
			self::$urlmapping = $cached_data["urlmapping"];
		}
	}

	static function config_set_runtime_params()
	{
		$config =& self::$config_params;

		// Запуск из консоли или под веб-сервером?
		$config["console_mode"] = (isset($_SERVER["GATEWAY_INTERFACE"]) ? 0 : 1);

		$console_base_url = encoded_url_parts($config["console_base_url"]);

		// Определяем базовые параметры УРЛ
		if (!$config["console_mode"]) { $base_uri = preg_replace("~/" . preg_quote(basename($_SERVER["SCRIPT_FILENAME"]), "~") . "$~", "/", $_SERVER["SCRIPT_NAME"]); }
		else { $base_uri = $console_base_url["path"]; }
		$config["base_uri"] = $base_uri;

		$config["base_scheme"] = (@$_SERVER["HTTPS"] == "on" || @$_SERVER["HTTP_X_FORWARDED_PROTO"] == "https") ? "https://" : "http://";

		$base_host = null; $base_port = null;
		// Если запускаемся из под апача
		if (!$config["console_mode"])
		{
			if (isset($_SERVER["HTTP_HOST"])) { @list($base_host, $base_port) = explode(":", $_SERVER["HTTP_HOST"]); }
			elseif (isset($_SERVER["SERVER_NAME"])) { $base_host = $_SERVER["SERVER_NAME"]; }
			else { $base_host = $_SERVER["SERVER_ADDR"]; $base_port = $_SERVER["SERVER_PORT"]; }
		}
		else { $base_host = @$console_base_url["host"]; $base_port = @$console_base_url["port"]; }

		$config["base_host"] = strtolower($base_host);
		if (!$base_port && $config["base_scheme"] == "http://") { $base_port = "80"; }
		if (!$base_port && $config["base_scheme"] == "https://") { $base_port = "443"; }
		$config["base_port"] = $base_port;

		// Если в конфиге указано что дебажный режим разрешён и мы не в консоли
		if (@$config["debug_mode"])
		{
			if (!$config["console_mode"])
			{
				// Включаем только если попросили включить или включено в куках, иначе - выключаем
				if (isset($_GET["debug"])) { $config["debug_mode"] = $_GET["debug"] === "0" ? 0 : 1; }
				else { $config["debug_mode"] = @$_COOKIE["debug_mode"] ? 1 : 0; }
				setcookie("debug_mode", $config["debug_mode"], 0x7FFFFFFF, $config["base_uri"]);
			}
		}
		// Если запрещён или отсуствует вообще то по-умолчанию рабочий режим
		else { $config["debug_mode"] = 0; }

		// Если в конфигах нет директив debug-авторизации - выключаем debug-режим
		if (!isset($config["debug_login"]) || !isset($config["debug_password"])) { $config["debug_mode"] = 0; }

		// Если DEBUG-режим включен, и мы не в консоли - то требуется DEBUG-авторизация
		if ($config["debug_mode"] && !$config["console_mode"])
		{
			// Если ключ авторизации не установлен или не верный
			if (@$_COOKIE["debug_key"] != md5($config["debug_login"] . ":" . $config["debug_password"]))
			{
				// Если ввели правильный логин и пароль - ставим авторизационный ключик debug-режима
				if (@$_POST["debug_login"] == $config["debug_login"] && @$_POST["debug_password"] == $config["debug_password"])
				{ setcookie("debug_key", md5($config["debug_login"] . ":" . $config["debug_password"]), @$_POST["debug_save"] ? time() + 86400 * 30: 0, $config["base_uri"]); }
				// Если не правильный, но уже сабмитили форму вырубаем DEBUG-режим молча
				elseif (isset($_POST["debug_login"]) && isset($_POST["debug_password"]))
				{ setcookie('debug_mode', 0, 0x7FFFFFFF, $config["base_uri"]); $config["debug_mode"] = 0; }
				// Иначе всегда выводим форму запроса пароля и стопаем скрипт
				else
				{
					while (@ob_end_clean());
					?>
					<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
					<html>
					<head>
						<title>Отладочный режим</title>
						<style type="text/css" media="screen">body, table, tr, td, form, select, input { font-family: Ms Sans Serif, Tahoma, Arial; font-size: 11px; }</style>
					</head>
					<body>
					<p><b>Для включения данного режима требуется логин и пароль</b></p>
					<form action="<?= h($_SERVER["REQUEST_URI"]) ?>" method="post">
					Логин: <input type="text" name="debug_login" value="<?= h(@$_POST["debug_login"]) ?>">
					Пароль: <input type="password" name="debug_password">
					<input type="checkbox" name="debug_save">Запомнить
					<input type="submit" value="Вперёд!">
					</form>
					</body>
					</html>
					<?
					exit;
				}
			}
		}
		$config["ajax_request"] = (@strtoupper($_SERVER["HTTP_X_REQUESTED_WITH"]) == "XMLHTTPREQUEST") ? $_SERVER["HTTP_X_REQUESTED_WITH"] : false;
	}

	// Возвращает конфигурационные параметры
	static function get_config_params() { return self::$config_params; }

	// Возвращает конфигурационные параметры баз данных
	static function get_config_databases() { return self::$databases; }

	// Функция автоматической подгрузки классов
	// Сначала ищет среди пользовательских классов, затем среди системных классов
	// И в последнюю очередь среди классов-хелперов
	// В имени класса все "_" превращаются в "/".
	static function autoload($classname)
	{
		$classname = str_replace("_", "/", $classname);
		if (file_exists($class_file = CLASSES_DIR . "/" . $classname . ".php")) { include_once($class_file); return true; }
		elseif (file_exists($class_file = CORE_CLASSES_DIR . "/" . $classname . ".php")) { include_once($class_file); return true; }
	}

	static function config_setup_constants()
	{
		$config =& self::$config_params;

		// Преобразование ini-параметров в константы ПХП
	    foreach ($config as $k => $v)
		{
			$k = strtoupper($k);
			if ((is_scalar($v) || $v === null) && !defined($k)) { define($k, $v); }
		}

		if (!defined("BASE_URL"))
		{
			$base_port = BASE_PORT;
			if ($base_port == "80" && BASE_SCHEME == "http://") { $base_port = null; }
			if ($base_port == "443" && BASE_SCHEME == "https://") { $base_port = null; }
			define("BASE_URL", BASE_SCHEME . BASE_HOST . ($base_port ? ":" . $base_port : null) . BASE_URI);
		}
	}

	// Возвращает или устанавливает тип отдаваемого контента
	static function output_type($type = null, $header_type = null)
	{
		if ($type === null) { return self::$output_data_type; }
		else
		{
			$old = self::$output_data_type;
			self::$output_data_type = $type;
			if (!CONSOLE_MODE)
			{
				switch ($type)
				{
					case "html": header("Content-Type: " . ($header_type ? $header_type : "text/html") . "; charset=utf-8"); break;
					case "xml": header("Content-Type: " . ($header_type ? $header_type : "application/xml") . "; charset=utf-8"); break;
					case "javascript": header("Content-Type: " . ($header_type ? $header_type : "text/javascript") . "; charset=utf-8"); break;
					case "json": header("Content-Type: " . ($header_type ? $header_type : "application/json") . "; charset=utf-8"); break;
				}
			}
			return $old;
		}
	}

	// Собирает информацию обо всех выполненных запросах к БД
	static function collect_sql($result)
	{
		if (DEBUG_MODE && !CONSOLE_MODE) { self::$sql_executed[] = $result; }
		self::$sql_exec_time += $result->exec_time;
		self::$sql_fetch_time += $result->fetch_time;
	}

	// Возвращает массив со списком sql запросов выполненных на данный момент
	static function get_sql_executed() { return self::$sql_executed; }
	static function get_sql_exec_time() { return self::$sql_exec_time; }
	static function get_sql_fetch_time() { return self::$sql_fetch_time; }

	// Создаёт соединения со всеми опредёнными в конфиге базами данных - возвращает false при ошибке
	static function create_db_connections()
	{
		if (count(self::$databases))
		{
			// Находим базу данных, которая будет использоваться по-умолчанию - это либо та у которой указан
			// параметр "default" - либо если ни у одной не указан - то первая из списка
			$default_database = null;
			foreach (self::$databases as $key => $params)
			{ if (isset($params["default"]) && $params["default"]) { $default_database = $key; break; } }

			// Не нашли базу, у которой чётко указано что она дефолтная - берём первую из списка
			if ($default_database === null)
			{
				reset(self::$databases);
				list($default_database, $params) = each(self::$databases);
			}

			self::create_db_connection($default_database);

			foreach (self::$databases as $dbname => $params)
			{
				if ($dbname == $default_database) { continue; }
				self::create_db_connection($dbname);
			}
		}
	}

	// Создаёт объект соединения с определённой базой данных
	static function create_db_connection($dbname)
	{
		if (isset(self::$databases[$dbname]))
		{
			$params = self::$databases[$dbname]; unset($params["default"]);

			if (isset($params["methods_prefix"])) { $methods_prefix = $params["methods_prefix"]; } else { $methods_prefix = null; }

			// Если у приложения активирован режим отладки указываем базе войти в режим отладки тоже
			if (DEBUG_MODE) { $params["debug_mode"] = true; }

			// Создаём объект соединения
			$instance = DBConnection::create($params, $methods_prefix);

			// Указываем стандартный логгер в файл если включен режим отладки
			if (DEBUG_MODE)
			{
				$instance->set_logger(array(self::$debug, "log"));
				$instance->set_collector(array("A5", "collect_sql"));
			}
		}
		else { throw_error("Cannot create database connection named \"$dbname\": not exists.", true); }
	}

	static private $cache_engine = null;
	static private $cache_context = null;
	static private $cache_namespace = APP_DIR;

	// Функция устанавливает используемый движок кэширования, это должен быть экземпляр класса пример
	// которого вы можете найти в папке Cache. Если передать null - будет произведена проверка установлен
	// ли класс кэширования и если нет - будет выдана ошибка - иначе вернёт true
	static function cache_engine($instance = null)
	{
		if ($instance === null)
		{
			if (self::$cache_engine === null)
			{ return throw_error("Cache class not registered, please, use " . __CLASS__ . "::cache_engine(\$instance) to register it.", true); }
			else { return true; }
		}
		else
		{
			if (get_class($instance) === false)
			{ return throw_error("You provided invalid cache class instance.", true); }
			else { self::$cache_engine = $instance; }
		}
		return true;
	}

	// Контекст кэширования запросов - используется например если SQL запросы зависят от каких-то общих факторов
	// которые явно в запросе не указываются, например текущий язык сайта, видимость объектов и другое
	static function cache_context()
	{
		$args = func_get_args();
		if (count($args)) { return (self::$cache_context = md5(serialize($args))); }
		else { return self::$cache_context; }
	}

	// Функции генерации id ключей для кэширования
	static function cache_generate_id($id) { return md5(self::$cache_namespace . $id . self::cache_context()); }
	static function cache_generate_tag_id($id) { return md5(self::$cache_namespace . "tags" . $id . self::cache_context()); }

	// Устанавливает namespace для генерации ключей кэша - null дефолтный
	static function cache_namespace($namespace = null) { return (self::$cache_namespace = ($namespace === null ? APP_DIR : $namespace)); }

	// Обработчик для чтения данных в кэше.
	static function cache_fetch($id)
	{
		if (self::cache_engine())
		{
			$cache_id = self::cache_generate_id($id);
			return self::$cache_engine->fetch($cache_id);
		} else { return null; }
	}

	// Обработчик для записи данных в кэше.
	static function cache_store($id, $data, $time = 0, $tags = array())
	{
		if (self::cache_engine())
		{
			$cache_id = self::cache_generate_id($id);
			// Создаём для каждого тэга соответствующий ему ключ
			if (!is_array($tags)) { $tags = array($tags); }
			foreach ($tags as $i => $tag_id) { $tags[$i] = self::cache_generate_tag_id($tag_id); }
			// Если запросили сохранение данных в кэш - вызовем функцию сохранения
			return self::$cache_engine->store($cache_id, $data, $time, $tags);
		} else { return false; }
	}

	// Удаляет данные из кэша по имени ключа
	static function cache_delete($id)
	{
		if (self::cache_engine())
		{
			// Ключ идентифицирующий данную запись
			$cache_id = self::cache_generate_id($id);
			self::$cache_engine->delete($cache_id);
		} else { return false; }
	}

	// Удаляет данные из кэша по имени тэга(ов)
	static function cache_delete_tags($tags)
	{
		if (self::cache_engine())
		{
			// Создаём для каждого тэга соответствующий ему ключ
			if (!is_array($tags)) { $tags = array($tags); }
			foreach ($tags as $i => $tag_id) { $tags[$i] = self::cache_generate_tag_id($tag_id); }
			self::$cache_engine->delete_tags($tags);
		} else { return false; }
	}

	// Функция разбирает urlmapping на все возможные мапы сохраняя
	// информацию о необязательных параметрах требуемых и условных
	static function setup_url_maps()
	{
		static $all_maps = null;
		if ($all_maps !== null) { return $all_maps; }
		$all_maps = array();
		$map_name_number = 0;
		foreach (self::$urlmapping as $map => $map_params)
		{
			$source_map_name = $map;
			$shorthand_default_params = array();
			if (preg_match("/^ (.*?) \s* => \s* (.+)$/sux", $map, $matches))
			{
				$map = $matches[1];
				$chunks = explode("#", $matches[2], 2);
				if (!@is_empty($chunks[0])) { $shorthand_default_params["controller"] = $chunks[0]; }
				if (!@is_empty($chunks[1])) { $shorthand_default_params["action"] = $chunks[1]; }
			}

			$chunks = explode(":", $map, 2);

			if (count($chunks) == 1) { $map_name = null; }
			else { $map_name = $chunks[0]; $map = $chunks[1]; }

			$require_params = array();
			$require_regex_params = array();
			$default_params = array("action" => "index");

			foreach ((array) $map_params as $param_name => $param_value)
			{
				// Если параметр указан без флага, значит указано его дефолт-значение
				if (strpos($param_name, ".") === false) { $flag = "default"; }
				else { list($flag, $param_name) = explode(".", $param_name, 2); }
				switch ($flag)
				{
					case 'default': $default_params[$param_name] = $param_value; break;
					case 'require_regex': $require_regex_params[$param_name] = $param_value; break;
					case 'require': $require_params[$param_name] = $param_value; break;
					default: throw_error("Unknown flag '" . $flag . "' for parameter '" . $param_name . "' in map: " . $source_map_name, true); break;
				}
			}

			// Если в мапе параметры контроллера и действия указаны с помощью краткого способа
			// то они имеют приоритет над параметрами, которые указаны далее в конфигурации мапа
			$default_params = array_merge($default_params, $shorthand_default_params);

			// Составляем массивы возможных урл для данного мапа с учётом опциональных параметров
			$possible_maps = array();
			$map_chunks = preg_split("/([@*][a-zA-Z0-9_]+)/", $map, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
			$possible_maps[] = $map_chunks;
			$i = count($map_chunks) - 1;
			while ($i >= 0)
			{
				$chunk = $map_chunks[$i];
				if (in_array(substr($chunk, 0, 1), array("@", "*")))
				{
					$param_name = substr($chunk, 1);
					// Если параметр не имеет требуемого значения, значит указывать его не обязательно
					if (!array_key_exists($param_name, $require_params) && !array_key_exists($param_name, $require_regex_params))
					{
						// Если перед опциональным параметром имеется ещё какая-то
						// часть урл заканчивающаяся на разделительный символ
						if (isset($map_chunks[$i - 1]) && in_array(substr($map_chunks[$i - 1], -1, 1), self::$url_separators))
						{
							// Удаляем все эти разделительные символы
							$map_chunks[$i - 1] = rtrim($map_chunks[$i - 1], implode("", self::$url_separators));
							// Если в итоге часть оказалось пустой - то её пропустим
							if (is_empty($map_chunks[$i - 1])) { $i--; }
						}
						$possible_maps[] = array_slice($map_chunks, 0, $i); $i--; continue;
					}
				}
				break;
			}

			if ($map_name === null) { $map_index = $map_name_number; } else { $map_index = $map_name; }
			if (!isset($all_maps[$map_index])) { $all_maps[$map_index] = array(); }
			elseif ($map_name !== null) { throw_error("Duplicate name '" . $map_index . "' for map '" . $map . "'", true); }

			if ($map_name !== null && !preg_match("/^[a-zA-Z0-9_]+$/sx", $map_name))
			{ throw_error("Invalid name '" . $map_name . "' for map '" . $map . "'", true); }

			if ($map_name !== null)
			{
				// Если это именованный map - создаём функцию его формирования
				if ($map_name !== null)
				{
					// Определяем какие параметры участвуют в составлении url - все они
					// будут обязательными для вызова будующих функций - помощников
					$call_args = array();
					foreach ($map_chunks as $chunk)
					{
						if (!in_array(substr($chunk, 0, 1), array("@", "*"))) { continue; }
						$call_args[substr($chunk, 1)] = true;
					}

					$call_args_code = array_keys($call_args);
					$call_args_code = array_map(function($n) { return '$' . $n . ' = null'; }, $call_args_code);
					$call_args_code = implode(", ", $call_args_code);

					$args_assign_code = array_keys($call_args);
					$args_assign_code = array_map(function($n) { return 'if (isset($' . $n . ')) { $args["' . $n . '"] = $' . $n . '; }'; }, $args_assign_code);
					$args_assign_code = implode("; ", $args_assign_code);

					$function_code = ltrim_lines('
					function ' . $map_name . '_url(' . $call_args_code . ')
					{
						$args = func_get_args();
						$args = array_slice($args, ' . count($call_args) . ');
						if (count($args) % 2 == 0) { $args = list2hash($args); }
						elseif (count($args) == 1 && is_array($args[0])) { $args = $args[0]; }
						elseif (!is_array($args)) { $args = array($args); }
						$args["@map_name"] = "' . $map_name . '";
						' . $args_assign_code . ';
						$args["@only_path"] = false;
						return call_user_func(array("A5", "url_for"), $args);
					}');

					eval($function_code);

					$function_code = ltrim_lines('
					function ' . $map_name . '_path(' . $call_args_code . ')
					{
						$args = func_get_args();
						$args = array_slice($args, ' . count($call_args) . ');
						if (count($args) % 2 == 0) { $args = list2hash($args); }
						elseif (count($args) == 1 && is_array($args[0])) { $args = $args[0]; }
						elseif (!is_array($args)) { $args = array($args); }
						$args["@map_name"] = "' . $map_name . '";
						' . $args_assign_code . ';
						$args["@only_path"] = true;
						return call_user_func(array("A5", "url_for"), $args);
					}');

					eval($function_code);
				}
			}

			foreach ($possible_maps as $possible_map)
			{
				// Определяем какие параметры должны быть в любом случае чтобы можно было составить данный урл
				$need_for_construct_params = array();
				foreach ($possible_map as $chunk)
				{
					if (!in_array(substr($chunk, 0, 1), array("@", "*"))) { continue; }
					$need_for_construct_params[substr($chunk, 1)] = true;
				}

				// Перебираем указанные по-умолчанию параметры
				// Если среди них есть такие, которые не требуются для конструирования url мапа
				// и при этом они не являются обязательными или их обязательное значение отличается от дефолтного, то указываем их как обязательные
				$map_require_params = $require_params;
				foreach ($default_params as $name => $value)
				{
					if (!isset($need_for_construct_params[$name]) && @$map_require_params[$name] != $value)
					{ $map_require_params[$name] = $value; }
				}

				$all_maps[$map_index][] = array
				(
					"url" => implode(null, $possible_map),
					"url_chunks" => $possible_map,
					"need_for_construct_params" => $need_for_construct_params,
					"require_params" => $map_require_params,
					"require_regex_params" => $require_regex_params,
					"default_params" => $default_params,
				);
			}

			if ($map_name === null) { $map_name_number++; }
		}
		return $all_maps;
	}

	// Возвращает текущий URI приложения (может не совпадать с полным URI)
	static function get_current_uri() { return trim(substr_ltrim(self_url(), BASE_URI), "/"); }

	// Функция устанавливает значения страницы вынимая их из текущего УРЛ
	// на основе urlmapping конфиг-файла
	static function setup_request()
	{
		// Дефолт-параметры
		$page = array("controller" => "404", "action" => "index");

		// Пробегаемся по всем мапам - подбираем подходящий
		// затем из тех что подошли выбираем наиболее "глубокий" контроллер
		$all_maps = self::setup_url_maps();

		$found_page = array(); $found_count_max = -1; $possible_controllers = array();
		foreach ($all_maps as $map_name => $maps)
		{
			foreach ($maps as $map)
			{
				$param_idx = 1; $pos_params = array();
				$uri_regex = "/^";
				foreach ($map["url_chunks"] as $chunk)
				{
					// Если это параметр - сохраняем номер позиции на которой он должен быть найден
					// в шаблоне для uri и добавляем соответствующее regex-выражение
					if (in_array(substr($chunk, 0, 1), array("@", "*")))
					{
						$pos_params[$param_idx] = substr($chunk, 1);
						if ($chunk == "@controller") { $uri_regex .= "([a-zA-Z0-9_\/-]+)"; }
						elseif ($chunk == "@action") { $uri_regex .= "([a-zA-Z0-9_-]+)"; }
						elseif (substr($chunk, 0, 1) == "*") { $uri_regex .= "(.+)"; }
						else { $uri_regex .= "([^" . preg_quote(implode("", self::$url_separators), "/") . "]+)"; }
						$param_idx++;
					}
					else { $uri_regex .= preg_quote($chunk, "/"); }
				}
				$uri_regex .= "$/s";

				// Сравниваем текущий ури с получившимся шаблоном - если совпал - проверяем все ли параметры верны
				if (preg_match($uri_regex, self::get_current_uri(), $param_values))
				{
					// Заполняем default значениями
					$page_params = $map["default_params"];

					// Заменяем значения найдеными в урл
					for ($i = 1; $i < count($param_values); $i++)
					{
						if (isset($pos_params[$i]))
						{ $page_params[$pos_params[$i]] = rawurldecode($param_values[$i]); }
					}

					// Проверяем все обязательные параметры
					foreach ($map["require_params"] as $param_name => $param_value)
					{
						if (is_empty($param_value) && !isset($page_params[$param_name])) { continue 2; }
						elseif (!is_empty($param_value) && @$page_params[$param_name] != $param_value) { continue 2; }
					}

					// Проверяем все обязательные regex-параметры
					foreach ($map["require_regex_params"] as $param_name => $require_regex)
					{
						// Если не совпало рег.выражение или вообще оно не верное
						$result = @preg_match($require_regex, $page_params[$param_name]);
						if ($result === false && error_reporting())
						{ throw_error("Invalid regular expression for parameter \"" . $param_name . "\" in map \"" . implode("", $map["url_chunks"]) . "\""); }
						if (!$result) { continue 2; }
					}

					if (isset($page_params["controller"]))
					{
						if (!isset($possible_controllers[$page_params["controller"] . "#" . $page_params["action"]]))
						{
							// Запоминаем что такой контроллер уже проверялся
							$possible_controllers[$page_params["controller"] . "#" . $page_params["action"]] = $page_params;
							// Если всё нормально - все параметры проверены - значит мап подходит - проверяем
							// существование такого контроллера и если есть - записываем этот мап как один из подходящих
							$controller_found = @self::test_controller($page_params["controller"]);
							// Выводим в дебаг-консоль
							debug($page_params["controller"] . "#" . $page_params["action"] . " (" . ($controller_found ? "found" : "not found") . ")", "Possible controllers");
							// Отбираем контроллер который содержит больше всего "/"
							if ($controller_found)
							{
								$count = count(explode("/", $page_params["controller"]));
								if ($count > $found_count_max) { $found_page = $page_params; $found_count_max = $count; }
							}
						}
					}
				}
			}
		}

		if (count($found_page)) { $page = array_merge($page, $found_page); }

		// Параметры которые переданы через QUERY_STRING приоритетнее параметров на основе УРЛ
		foreach ($page as $key => $val)
		{
			if (in_array($key, array("controller", "action"))) { continue; }
			if (!isset($_GET[$key]) && !is_empty($val)) $_GET[$key] = $val;
		}

		if (array_key_exists("controller", $page)) { self::$controller = $page["controller"]; }
		if (array_key_exists("action", $page)) { self::$action = $page["action"]; }

		self::$request_params = $_GET;
	}

	// Добавляет к html содержимому <base> тэг и исправляет ссылки там где указаны анкоры
	static function add_base_tag($text)
	{
		if (!preg_match('/<base\s+/si', $text))
		{
			if (preg_match('/<head\s*.*?>/si', $text)) { $text = preg_replace("{(<head\s*.*?>)}si", '$1<base href="' . h(BASE_URL) . '" />', $text); }
			else { $text = preg_replace("{(<html\s*.*?>)}si", '$1<head><base href="' . h(BASE_URL) . '" /></head>', $text); }
		}

		$text = preg_replace('/(?> (<a.+?href\s*=\s*) ) (?> \'(\#[^\']*)\' ) /six', "$1'" . h($_SERVER["REQUEST_URI"]) . "$2'", $text);
		$text = preg_replace('/(?> (<a.+?href\s*=\s*) ) (?> "(\#[^"]*)" ) /six', "$1\"" . h($_SERVER["REQUEST_URI"]) . "$2\"", $text);
		$text = preg_replace('/(?> (<a.+?href\s*=\s*) ) (?> (\#[^\s]*) ) /six', "$1" . h($_SERVER["REQUEST_URI"]) . "$2", $text);

		return $text;
	}

	static function ob_add_base_tag($buffer) { return in_array(self::output_type(), array("html")) ? self::add_base_tag($buffer) : $buffer; }

	// Если вызван без параметров - возвращает текущий контекст
	// Если вызван с параметром - устанавливает текущий контекст и возвращает предыдущий
	static function current_context()
	{
		$args = func_get_args();
		if (count($args) > 0)
		{
			$prev_context = self::$current_context;
			self::$current_context = $args[0];
			return $prev_context;
		}
		return self::$current_context;
	}

	// Без параметра - возвращается текущий контроллер
	static function get_controller_path($controller = null)
	{
		if (!$controller) { $controller = self::controller(); }
		return normalize_path(CONTROLLERS_DIR . normalize_path("/" . $controller . "_controller.php"));
	}

	// Возвращает true если такой контроллер есть - false иначе
	static function test_controller($controller = null) { return file_exists(self::get_controller_path($controller)); }

	// Без параметра - возвращается путь к хелперу текущего контроллера
	static function get_helper_path($helper = null)
	{
		if (!$helper) { $helper = self::controller(); }
		return normalize_path(HELPERS_DIR . normalize_path("/" . $helper . "_helper.php"));
	}

	// Возвращает true - если указанный helper существует - иначе false
	static function test_helper($helper = null) { return file_exists(self::get_helper_path($helper)); }

	// Запускает на основе текущего окружения соответствующий контроллер, вьюшку и остальное
	static function process_request($controller = null, $action = null, $is_full_process = false)
	{
		// Перед началом обработки любого контроллера - неизвестно что будем рендерить
		self::$render_view = null;

		if ($controller !== null || $action !== null)
		{
			if ($controller !== null) { self::controller($controller); }
			if ($action !== null) { self::action($action); } else { self::action("index"); }
		}

		// Если запросили полную отработку (что обычно не требуется) - подключает контроллер приложения
		if ($is_full_process) { self::include_application_controller(); }

		// Информация о запускаемом контроллере и его действии
		debug(self::controller() . "#" . self::action(), "Selected controller");

		// Подключаем контроллер
		if (self::test_controller()) { self::include_controller(); }
		elseif (self::controller() != "404") { self::process_request("404"); }
		else { throw_error("Controller not found '" . self::get_controller_path() . "'", true); }

		// Если вьюха, которую нужно будет рендерить не существует - выдаём 404
		// если это обычный режим - иначе - выдаём ошибку
		if (!self::test_view())
		{
			if (DEBUG_MODE || self::controller() == "404") { throw_error("View '" . self::get_view_path() . "' not found", true); }
			else { self::process_request("404"); }
		}

        // Если у страницы есть "раскладка" - включаем её - раскладка - это та же вьюшка
        // но внутри неё вы ДОЛЖНЫ вставить конструкцию include_view() иначе вьюшка просто напросто не подключится. :)
        // Включается вьюшка в раскладку обычно где-то в середине контента раскладки, это избавляет от необходимости
        // включать в каждой вьюшке "header" и "footer" страницы. Особенно это удобно если они везде одинаковые.
        // Контроллер (что логично) может повлиять на раскладку если это требуется, т.к. он включается перед раскладкой

		// layout === true или null - берётся дефолтный
		// layout === false - не использовать layout
		// Иначе если не пустая строка - используется указанный
		$layout_path = null;
		if (self::layout() === true || self::layout() === null)
		{
			$layout_path = normalize_path(LAYOUTS_DIR . normalize_path("/" . self::controller() . ".phtml"));
			if (!file_exists($layout_path)) { $layout_path = null; }
		}
		elseif (self::layout() !== false && !is_empty(self::layout()))
		{ $layout_path = normalize_path(LAYOUTS_DIR . normalize_path("/" . self::layout() . ".phtml")); }

		self::$layout_path = $layout_path;

		// Перед началом вывода данных сохраняем уровень вывода ошибок
		self::$error_reporting = error_reporting();

		// Подключаем либо layout либо view
		if ($layout_path) { self::include_layout(); } else { self::include_view(); }

		exit;
	}

	static function include_application_controller()
	{
		self::current_context("controller");
		self::url_for_default("controller");
		self::include_application_helper();
		if (is_readable(APPLICATION_CONTROLLER_FILE)) { require(APPLICATION_CONTROLLER_FILE); }
	}

	static function include_application_helper() { if (is_readable(APPLICATION_HELPER_FILE)) require_once(APPLICATION_HELPER_FILE); }

	static function include_controller()
	{
		self::current_context("controller");
		self::url_for_default("controller");
		self::inherit_helpers(self::controller());
		
		// Все глобальные переменные автоматически доступны в контроллере		
		@extract($GLOBALS, EXTR_SKIP | EXTR_REFS);
		
		// Получаем все переменные
		self::$controller_vars = get_defined_vars();

		// Подключаем файл контроллера
		require(self::get_controller_path());
	
		// Отделяем переменные контроллера от всех остальных объявленных вне его
		self::$controller_vars = array_diff_key(get_defined_vars(), self::$controller_vars);
		
		// Мёрджим с переменными которые можем передавать через render_view()
		self::$controller_vars = array_merge(self::$render_vars, self::$controller_vars);
	

	}

	static private function inherit_helpers($controller)
	{
		$segments = explode("/", $controller);
		$helper_name = null;
		foreach ($segments as $segment)
		{
			if ($helper_name) { $helper_name .= "/"; }
			$helper_name .= $segment;
			if (self::test_helper($helper_name)) { self::include_helper($helper_name); }
		}
	}

	// Подключает помощник текущего (или указанного первым параметром) контроллера
	static function include_helper()
	{
		if (func_num_args() > 0) { require_once(self::get_helper_path(func_get_arg(0))); }
		else { require_once(self::get_helper_path()); }
	}

	static function include_layout()
	{
		if (file_exists(self::$layout_path))
		{
			// Если тип выходного потока не был установлен - ставим
			if (!self::output_type()) { self::output_type((AJAX_REQUEST && @$_SERVER["HTTP_ACCEPT"] == "text/javascript") ? "javascript" : "html"); }

			// Все глобальные переменные автоматически доступны в раскладке
			@extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

			self::current_context("view");
			self::url_for_default("view");
			include(self::$layout_path);
		}
		else { echo "<b>Warning:</b> Layout <b>" . self::$layout_path . "</b> not found."; }
	}

	// Функция включения произвольного файла - чисто системная - используется при включении вьюшки или каких-либо частей
	// Вызывать нужно после включения контроллера
	// На вход передаётся путь к файлу который нужно включить - в файле будут видны все глобальные переменные и переменные контроллера
	static function include_file()
	{
		// Если передан массив для импорта переменных - импортируем их
		if (func_num_args() > 1 && is_array(func_get_arg(1))) { @extract(func_get_arg(1), EXTR_SKIP | EXTR_REFS); }

		// Во вьюшке доступны все переменные из контроллера
		@extract(self::$controller_vars, EXTR_SKIP | EXTR_REFS);

		// Глобальные переменные тоже всегда доступны
		@extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		include(func_get_arg(0));
	}

	// Путь формируется следующим образом
	// - Если имя вьюхи начинается на "./" и мы вызваны находясь внутри другой вьюхи - формируем путь отноительно
	// папки, в которой находится родительская вьюха
	// - Если в имени вьюхи есть знак "/" - путь формируется относительно корневой папки вьюх
	// - Иначе формируется относительно текущего контроллера
	static function get_view_path($view = null)
	{
		if ($view === null) { $view = (self::$render_view !== null ? self::$render_view : self::action()); }
		if (strpos($view, "./") === 0 && count(self::$included_views)) { $root = dirname(end(self::$included_views)); }
		elseif (strpos($view, "/") !== false) { $root = VIEWS_DIR; }
		else { $root = VIEWS_DIR . "/" . self::controller(); }
		return normalize_path($root . normalize_path("/" . $view . ".phtml"));
	}

	// Возвращает true - если указанная view существует - иначе false
	// Если имя view не передать - берётся текущая
	static function test_view($view = null) { return file_exists(self::get_view_path($view)); }

	// Вызывается после отработки контроллера или вручную во-вьюшках или layouts
	// Если на вход ничего не передали или null то включает текущую вьюшку
	// Если передали что-то без "/" в имени то включает вьюшку с таким именем текущего контроллера
	// Если передали что-то с "/" в имени то включает этот путь относительно VIEWS_DIR
	// Вторым параметром можно передать массив для импорта различных переменных, которые
	// будут приоритетнее чем переменные от контроллера.
	// Примеры вызова:
	// include_view("index") - просто включит view с данным именем, в которой будут видны все переменные
	// контроллера
	// include_view("index", array("products" => $products)) - тоже самое, но во вьюшке будет доступна
	// переменная $products переданная во втором параметре, она заменит переменную контроллера с таким же именем
	// если таковая была.
	static function include_view($view_name = null, $import_vars = array())
	{
		// Пробуем стандартный способ
		$view_path = self::get_view_path($view_name);
		if (file_exists($view_path))
		{
			// Если тип выходного потока не был установлен - ставим
			if (!self::output_type()) { self::output_type((AJAX_REQUEST && @$_SERVER["HTTP_ACCEPT"] == "text/javascript") ? "javascript" : "html"); }

			// Возвращаем уровень вывода ошибок в исходное состояние если оно было отключено
			if (self::$error_reporting !== null) { error_reporting(self::$error_reporting); }
			self::current_context("view");
			self::url_for_default("view");
			self::$included_views[] = $view_path;
			self::include_file($view_path, $import_vars);
			array_pop(self::$included_views);
		}
		elseif (error_reporting()) { echo "<b>Warning:</b> View <b>" . $view_path . "</b> not found."; }
	}

	// Принцип работы полностью аналогичен include_view() - однако вместо отображения - возвращается контент вьюшки
	static function render_to_string($view_name = null, $import_vars = array())
	{
		// Пробуем стандартный способ
		$view_path = self::get_view_path($view_name);
		if (file_exists($view_path))
		{
			ob_start();
			if (self::$error_reporting !== null) { error_reporting(self::$error_reporting); }
			$prev_current_context = self::current_context("view");
			$prev_url_for_default = self::url_for_default("view");
			self::$included_views[] = $view_path;
			self::include_file($view_path, $import_vars);
			array_pop(self::$included_views);
			self::current_context($prev_current_context);
			self::url_for_default($prev_url_for_default);
			return ob_get_clean();
		}
		elseif (error_reporting()) { echo "<b>Warning:</b> View <b>" . $view_path . "</b> not found."; }
		return null;
	}

	// Запуск субконтроллера и его субвьюшки
	// По принципу работы очень похож на на include_view, за исключением того что включается не просто вьюшка но и мини-контроллер для неё
	// возвращает false и warning если не удалось ничего включить
	// также поддерживает второй параметра как локальные переменные
	static function include_block($block_name, $import_vars = array())
	{
		// Запоминаем статус вывода ошибок до начала работы - от этого зависит
		// выводить ли предупреждение о невозможности включения блока или нет
		$error_reporting = error_reporting();
		// Количество включаемых файлов
		$included_count = 0;

		if (!is_empty($block_name))
		{
			$controller_path = normalize_path(CONTROLLERS_DIR . normalize_path("/" . $block_name . ".php"));
			if (file_exists($controller_path) && is_readable($controller_path)) { $included_count++; } else { $controller_path = null; }

		    // Включаем вьюшку если есть
			$view_path = normalize_path(VIEWS_DIR . normalize_path("/" . $block_name . ".phtml"));
			if (file_exists($view_path) && is_readable($view_path)) { $included_count++; } else { $view_path = null; }

			// Возвращаем уровень вывода ошибок в исходное состояние если оно было отключено
			// при вызове данного метода, это нужно чтобы увидеть ошибки в самих файлах
			if (self::$error_reporting !== null) { error_reporting(self::$error_reporting); }

			// Сохранем все контексты до начала работы
			$prev_current_context = self::current_context();
			$prev_url_for_defaults = self::url_for_defaults();
			if ($included_count) { self::include_block_files($controller_path, $view_path, $import_vars); }
			self::current_context($prev_current_context);
			self::url_for_default($prev_url_for_defaults);
		}

		// Если мы ничего не пытались включать и вывод ошибок был включен перед вызовом метода - выводим предупреждение
		if (!$included_count && $error_reporting) { return throw_error("Nothing found for '" . $block_name . "'"); }
		return $included_count;
	}

	// Функция запуска блочного процесса
	// Первый параметр - путь к файлу контроллера
	// Второй параметр - путь к файлу вьюхи
	// Третий параметр - локальные переменные
	static function include_block_files()
	{
		// Если передан массив для импорта переменных - импортируем их
		if (func_num_args() > 2 && is_array(func_get_arg(2))) { @extract(func_get_arg(2), EXTR_SKIP | EXTR_REFS); }

		// Также видны все глобальные переменные
		@extract($GLOBALS, EXTR_SKIP | EXTR_REFS);

		if (func_num_args() > 0 && func_get_arg(0) !== null)
		{
			self::current_context("controller");
			self::url_for_default("controller");
			require(func_get_arg(0));
		}

		if (func_num_args() > 1 && func_get_arg(1) !== null)
		{
			self::current_context("view");
			self::url_for_default("view");
			self::$included_views[] = func_get_arg(1);
			require(func_get_arg(1));
			array_pop(self::$included_views);
		}
	}

	// Диспетчер - собственно самая главная функция определяющая параметры страницы и запускающая контроллер - вьюшку и прочее
	static function dispatch()
	{
		// self::get_page() определяет и устанавливает все параметры текущей страницы
		self::setup_request();
		self::process_request(null, null, true);
	}

	// Возвращает текущие умолчания
	static function url_for_defaults() { return self::$url_for_defaults; }

	/* Вызов может иметь несколько вариантов
	 *
	 * Первый: url_for_default("@overwrite", true)
	 * Просто устанавливает отдельный параметр в отдельное значение по-умолчанию
	 *
	 * Второй: url_for_default("controller") | url_for_default("view") | url_for_default("javascript")
	 * Устанавливает группу параметров (@overwrite, @escape, @jsescape) в соответствующие значения для указанного контекста
	 *
	 * Третий: url_for_default(array("@overwrite" => true, "@escape" => false))
	 * Устаналивает сразу несколько параметров за один вызов
	 *
	 * Четвёртый: url_for_default() или url_for_defaults(null)
	 * Сбрасывает все умолчания, оставляя нетронутыми только: @overwrite, @escape, @jsescape
	 *
	 * Возвращает предыдущие значения по-умолчанию
	 */
	static function url_for_default($key = null, $value = null)
	{
		$prev_url_for_defaults = self::url_for_defaults();

		if ($key === null)
		{
			self::$url_for_defaults = array
			(
				"@escape" => self::$url_for_defaults["@escape"],
				"@jsescape" => self::$url_for_defaults["@jsescape"],
				"@overwrite" => self::$url_for_defaults["@overwrite"],
			);
		}
		elseif (is_array($key))
		{
			foreach ($key as $k => $v)
			{ self::$url_for_defaults[$k] = $v; }
		}
		elseif ($key == "controller" && $value === null)
		{
			self::$url_for_defaults["@escape"] = false;
			self::$url_for_defaults["@jsescape"] = false;
			self::$url_for_defaults["@overwrite"] = false;
		}
		elseif ($key == "view" && $value === null)
		{
			self::$url_for_defaults["@escape"] = true;
			self::$url_for_defaults["@jsescape"] = false;
			self::$url_for_defaults["@overwrite"] = false;
		}
		elseif ($key == "javascript" && $value === null)
		{
			self::$url_for_defaults["@escape"] = false;
			self::$url_for_defaults["@jsescape"] = true;
			self::$url_for_defaults["@overwrite"] = false;
		}
		elseif ($key == "@escape") { self::$url_for_defaults["@escape"] = (bool) $value; }
		elseif ($key == "@jsescape") { self::$url_for_defaults["@jsescape"] = (bool) $value; }
		elseif ($key == "@overwrite") { self::$url_for_defaults["@overwrite"] = (bool) $value; }
		else { self::$url_for_defaults[$key] = $value; }

		return $prev_url_for_defaults;
	}

	/*
	Функция выдаёт урл для страницы
	Вызов функции может быть разным.
	Если количество аргументов нечётное, то первый аргумент считается строкой УРЛ или её части если передано строковое значение,
	остальные аргументы беруться парами как ключ - значение, пример: url_for("http://www.example.com/images/test.jpg", "@host", "www.test.com")
	если же первый аргумент - массив, то он считается параметрами для составления урл, остальные аргументы игнорируются
	если же количество аргументов - чётное то они просто считаются парами ключ - значение
	Внимание: параметры имеющие значения null - не используются в формируемом УРЛ
	т.е. УРЛ формируется так как-будто бы вы эти параметры не передали
	Эту фичу очень удобно использовать совместно с параметром "@overwrite", передав значение какого-либо
	параметры как пустую как null - УРЛ сформируется на основе текущего контекста (с сохранением всех остальных параметров),
	а указанный параметр исчезнет
	*/
	static function url_for()
	{
		$args = func_get_args();
		$url = null; $params = array();

		if (count($args) % 2 == 0) { $url = null; $params = list2hash($args); }
		elseif (is_array($args[0])) { $params = $args[0]; }
		else { $url = $args[0]; $params = list2hash(array_slice($args, 1)); }

		// Берём дефолтные параметры на данный момент и смешиваем с переданными
		$params = array_merge(self::url_for_defaults(), $params);

		$cond = array();
		foreach (array("@map_name", "@anchor", "@scheme", "@host", "@port", "@root", "@only_path", "@escape", "@jsescape", "@overwrite", "@authorization") as $p)
		{ if (isset($params[$p])) { $cond[$p] = $params[$p]; unset($params[$p]); } }

		// Если не передали ни одного параметра кроме специальных (исключая @map_name) - это значит нужно формировать тот же самый url
		if ($url === null && !count($params) && !isset($cond["@map_name"])) { $cond["@overwrite"] = true; }

		$base_scheme = BASE_SCHEME;
		$base_authorization = null;
		$base_host = BASE_HOST;
		$base_port = BASE_PORT;
		$base_uri = BASE_URI;

		// Если url равен null и есть хоть один параметр - значит составлять нужно на основе мап-параметров
		if ($url === null)
		{
			// Специальный параметр "конец имени контроллера", к примеру
			// Если текущий контроллер "admin/misc/products" и указан "-controller" => "items"
			// То параметр "controller" должен стать "admin/misc/items".
			// Или если "-controller" => "other/products" то текущим должен стать
			// "admin/other/products"
			if (isset($params["-controller"]))
			{
				if (!isset($params["controller"]))
				{
					$current_segments = explode("/", self::controller());
					$relative_segments = !is_empty($params["-controller"]) ? explode("/", $params["-controller"]) : array();
					$current_count = count($current_segments);
					$relative_count = count($relative_segments);
					if ($relative_count < $current_count)
					{
						$current_segments = array_slice($current_segments, 0, $current_count - $relative_count);
						$relative_segments = array_slice($relative_segments, -$relative_count);
						$params["controller"] = implode("/", array_merge($current_segments, $relative_segments));
					}
					else { $params["controller"] = $params["-controller"]; }
				}
				unset($params["-controller"]);
				unset($current_segments);
				unset($relative_segments);
				unset($current_count);
				unset($relative_count);
			}

			// Специальный параметр "добавить к имени контроллера", к примеру
			// Если текущий контроллер "admin/misc/products" и указан "controller+" => "items"
			// То параметр "controller" должен стать "admin/misc/products/items".
			if (isset($params["controller+"]))
			{
				if (!isset($params["controller"]))
				{ $params["controller"] = self::controller() . (!is_empty($params["controller+"]) ? "/" . $params["controller+"] : ""); }
				unset($params["controller+"]);
			}

			// Если передан параметр перезаписи - восстанавливаем все переменные,
			// так как-будто они были переданы - замещая переданными
			if (@$cond["@overwrite"])
			{
				$prev_params = self::$request_params;
				$prev_params["controller"] = self::controller();
				$prev_params["action"] = self::action();
				$params = array_merge($prev_params, $params);
			}

			// Убираем параметры с пустыми значениями
			foreach ($params as $key => $val) { if ($val === null) unset($params[$key]); }

			// Нужно пробежаться по всем переданным параметрам и составить из них полный УРЛ
			// На основе urlmapping настроек - находим правило, которое поглотит максимальное количество параметров
			$matched_map = array();
			$all_maps = self::setup_url_maps();

			if (isset($cond["@map_name"]))
			{
 				if (isset($all_maps[$cond["@map_name"]])) { $all_maps = array($cond["@map_name"] => $all_maps[$cond["@map_name"]]); }
 				else { $all_maps = array(); }
			}
			else
			{
				// Для любого мапа контроллер и действие должно быть указано
				if (!array_key_exists("controller", $params) && !array_key_exists("action", $params))
				{
					$params["controller"] = self::controller();
					$params["action"] = self::action();
				}
				elseif (!array_key_exists("controller", $params) && array_key_exists("action", $params)) { $params["controller"] = self::controller(); }
				elseif (array_key_exists("controller", $params) && !array_key_exists("action", $params)) { $params["action"] = "index"; }
			}

			foreach ($all_maps as $map_name => $maps)
			{
				foreach ($maps as $map)
				{
					// Параметры переданные в вызов функции
					$params_provided = $params;

					// Если это запрос для конкретного мапа - заполняем недостающие значения дефолтными
					if (isset($cond["@map_name"]))
					{
						$require_params = array_merge($map["need_for_construct_params"], $map["require_params"], $map["require_regex_params"]);
						foreach ($require_params as $need_name => $need_value)
						{
							if (!array_key_exists($need_name, $params_provided) && array_key_exists($need_name, $map["default_params"]))
							{ $params_provided[$need_name] = $map["default_params"][$need_name]; }
						}

						if (!array_key_exists("controller", $params_provided) && !array_key_exists("action", $params_provided))
						{
							$params_provided["controller"] = self::controller();
							$params_provided["action"] = self::action();
						}
						elseif (!array_key_exists("controller", $params_provided) && array_key_exists("action", $params_provided)) { $params_provided["controller"] = self::controller(); }
						elseif (array_key_exists("controller", $params_provided) && !array_key_exists("action", $params_provided)) { $params_provided["action"] = "index"; }
					}

					// Полный набор имеющихся в данный момент параметров
					$params_total = array_merge($map["default_params"], $params_provided);

					// Теперь проверим все ли обязательные параметры удовлетворяют их жёстко заданным значениям
					foreach ($map["require_params"] as $need_name => $need_value)
					{
						if (is_empty($need_value) && !isset($params_total[$need_name])) { continue 3; }
						elseif (!is_empty($need_value) && @$params_total[$need_name] != $need_value) { continue 3; }
					}

					// Проверяем есть ли у нас все требуемые параметры для составления урл
					foreach ($map["need_for_construct_params"] as $need_name => $need_value) { if (!isset($params_total[$need_name])) continue 2; }

					// Теперь проверим на соответствие регулярным выражениям
					foreach ($map["require_regex_params"] as $need_name => $need_value)
					{
						$result = @preg_match($need_value, $params_total[$need_name]);
						if ($result === false && error_reporting())
						{ echo "<b>Warning:</b> Invalid regular expression for parameter \"" . $need_name . "\" in map \"" . implode("", $map["url_chunks"]) . "\""; }
						if (!$result) { continue 3; }
					}

					// Если мы сюда добрались, значит все параметры переданы верно и правило нам подходит
					$chunks = $map["url_chunks"]; $params_absorbed = 0;
					foreach ($chunks as $i => $chunk)
					{
						if (!in_array(substr($chunk, 0, 1), array("@", "*"))) { continue; }

						// Имя параметра
						$param_name = substr($chunk, 1);

						// Если параметр передали в вызове функции - значит мы его поглотили
						if (isset($params_provided[$param_name]))
						{
							$param_value = $params_provided[$param_name];
							unset($params_provided[$param_name]);
							$params_absorbed++;
						} else { $param_value = $params_total[$param_name]; }

						// Если параметр требуется для составления УРЛ - то кодируем оставляя "/" не тронутыми
						if (@$map["need_for_construct_params"][$param_name])
						{
							$param_segments = explode("/", (string) $param_value);
							array_walk($param_segments, function(&$item) { $item = rawurlencode($item); });
							$chunks[$i] = implode("/", $param_segments);
						}
						else { $chunks[$i] = rawurlencode($param_value); }
					}

					// Из всех оставшихся параметров считаем поглащёнными также те, которые являются обязательными для данного мапа
					foreach ($params_provided as $param_name => $param_value)
					{
						if (array_key_exists($param_name, $map["require_params"]) || array_key_exists($param_name, $map["require_regex_params"]))
						{ $params_absorbed++; unset($params_provided[$param_name]); }
					}

					$url = implode("", $chunks) . build_query_string($params_provided);

					// Если ранее было найдено подходящее правило из этой же группы и текущее
					// поглощает меньше параметров, то прекращаем искать среди этой группы
					if ($matched_map && (string) $matched_map[2] == (string) $map_name && $params_absorbed < $matched_map[1]) { continue 2; }

					// Если ранее было найдено подходящее правило из другой группы и текущее поглощает меньше или столько же
					// параметров прекращаем искать среди этой группы
					if ($matched_map && (string) $matched_map[2] != (string) $map_name && $params_absorbed <= $matched_map[1]) { continue 2; }

					// Записываем последнее найденное подходящее правило
					$matched_map = array($url, $params_absorbed, $map_name);
				}
			}

			// Нашли подходящее правило
			if ($matched_map) { $url = $matched_map[0]; } else { $url = null; }
		}
		// Иначе если $url указан в первом параметре - разбираем его
		elseif ($url !== null && false !== $parts = encoded_url_parts($url))
		{
			$url_params = array();
			if (isset($parts["query"])) { parse_str($parts["query"], $url_params); }

			// Совмещаем указанные параметры в урл с переданными в массиве
			$params = array_merge($url_params, $params);

			// Убираем параметры с пустыми значениями
			foreach ($params as $key => $val) { if ($val === null) unset($params[$key]); }
			$query_string = build_query_string($params);
			if (!is_empty($query_string)) { $parts["query"] = preg_replace("/^\?/", "", $query_string); }
			else { unset($parts["query"]); }

			if (isset($parts["host"]))
			{
				$base_scheme = $parts["scheme"] . "://";
				$base_host = strtolower($parts["host"]);
				$base_port = (isset($parts["port"]) ? $parts["port"] : null);
				if ($base_host != BASE_HOST) { $base_uri = "/"; }
				if (isset($parts["user"])) { $base_authorization = $parts["user"] . (isset($parts["pass"]) ? ":" . $parts["pass"] : ""); }
				unset($parts["scheme"]);
				unset($parts["host"]); unset($parts["port"]);
				unset($parts["user"]); unset($parts["pass"]);
			}
			// Если в урл нет имени хоста, то и схемы и авторизации тем более быть не должно
			else { unset($parts["scheme"]); unset($parts["user"]); unset($parts["pass"]); }

			// Не указан path ? Значит это корень
			if (!isset($parts["path"])) { $parts["path"] = "/"; }

			$parts["path"] = substr_ltrim($parts["path"], $base_uri);

			$url = make_url_from_parts($parts);
		}

		// Если в итоге мы так и не определили что же это за урл такой - берём текуший
		if ($url === null) { $url = $_SERVER["REQUEST_URI"]; }

		$scheme_default_port = array
		(
			"http://" => "80",
			"https://" => "443",
			"ftp://" => "21",
		);

		// Если в итоге у нас образовался не абсолютный путь (не со схемой и не с доменом)
		// это означает что это наш родной урл - составляем из него полный
		if (!is_absolute_url($url))
		{
			if (isset($cond["@scheme"]))
			{
				$cond["@scheme"] = mb_strtolower($cond["@scheme"]);
				if ($cond["@scheme"] != $base_scheme && !isset($cond["@port"]))	{ $cond["@port"] = $scheme_default_port[$cond["@scheme"]]; }
				$base_scheme = $cond["@scheme"];
			}
			if (isset($cond["@host"])) { $base_host = $cond["@host"]; }
			if (isset($cond["@port"])) { $base_port = $cond["@port"]; }
			if (isset($cond["@root"])) { $base_uri = $cond["@root"]; }
			if (isset($cond["@authorization"])) { $base_authorization = $cond["@authorization"]; }
			// Добавляем корневой путь если нужно
			if (substr($url, 0, 1) !== "/") { $url = $base_uri . $url; }
			if (@!$cond["@only_path"])
			{
				if ($base_port == $scheme_default_port[$base_scheme]) { $base_port = null; }
				$url = $base_scheme . (!is_empty($base_authorization) ? $base_authorization . "@" : "") . $base_host . ($base_port ? ":" . $base_port : null) . $url;
			}
		}

		if (!is_empty(@$cond["@anchor"]))
		{
			if (preg_match("/#[^#]*$/", $url)) { $url = preg_replace("/#[^#]*$/", "#" . $cond["@anchor"], $url); }
			else { $url .= "#" . $cond["@anchor"]; }
		}

		if (@$cond["@jsescape"]) { $url = j($url); }
		if (@$cond["@escape"]) { $url =  h($url); }

		return $url;
	}

	// Функция делает редирект на указанный адрес, принимаемые параметры аналогичны url_for
	static function redirect_to()
	{
		$args = func_get_args();
		$url = call_user_func_array("url_for", $args);
		header("Location: $url");
		exit;
	}

	// Получить или установить текущий контроллер
	static function controller()
	{
		$args = func_get_args();
		if (!count($args)) { return self::$controller; }
		else { return self::$controller = $args[0]; }
	}

	// Получить или установить текущее действие
	static function action()
	{
		$args = func_get_args();
		if (!count($args)) { return self::$action; }
		else { return self::$action = $args[0]; }
	}

	// Получить или установить текущий layout
	// Если вызвано с одним аргументом - устанавливает указанный layout и возвращает его
	// Если вызвано с двумя и второй аргумент true, то не устанавливает layout если он уже был установлен - возвращает текущий
	// Если вызвано без аргументов просто возвращает текущий layout
	static function layout()
	{
		$args = func_get_args();
		if (!count($args) || (@$args[1] && self::$layout !== null)) { return self::$layout; }
		else { return self::$layout = $args[0]; }
	}

	// Внимание!!! - функция только устанавливает что рендерить - но не прекращает работу контроллера
	static function render_view($view,  $import_vars = array())
	{	
		if( !empty($import_vars) ){ self::$render_vars = $import_vars; }

		self::$render_view = $view;
		return 0;
	}

	// Удобно для AJAX-запросов
	static function render_json($data) { self::output_type("json"); echo json_encode($data); exit; }

	// Удобно для дебага
	static function render_text($text) { echo $text; exit; }

	// Для стандартизации и только :)
	static function render_nothing() { exit; }

	// Стандартные хелперы любого приложения
	static function flash() { return call_user_func_array(array("Session", "flash"), func_get_args()); }

	// Создаёт функции ссылки общего пользования
	static function import_functions
	(
		$methods = array
		(
			"include_application_controller",
			"include_application_helper",
			"include_helper",
			"include_view",
			"include_block",
			"url_for",
			"url_for_default",
			"redirect_to",
			"controller",
			"action",
			"layout",
			"render_view",
			"render_to_string",
			"render_json",
			"render_text",
			"render_nothing",
			"flash",
		)
	)
	{
		foreach ($methods as $method)
		{
			if (!is_callable($method) && method_exists("A5", $method))
			{ eval('function ' . $method . '() { $args = func_get_args(); return call_user_func_array(array("A5", "' . $method . '"), $args); }'); }
		}
	}
}