<?php
/*
Класс для работы с пользовательскими сессиями.
Основные возможности:
- возможность ведения нескольких сессий одновременно с разным временем жизни
- возможность установки разного времени хранения для конкретного пользователя в пределах одной и той же сессии
- встроенный механизм реализации flash-переменных

ПОЯСНЕНИЯ О ПРИНЦИПЕ РАБОТЫ
---------------------------
Старт новой сессии производится вызовом Session::start(string $name[, array $params])
Например, при вызове Session::start("test") будет произведена попытка чтения данных сессии.
Данные буду восстановлены в массив $_SESSION["test"]. Если данных нет, или они устарели, либо
есть другая причина по которой данные не могут быть восстановлены, то $_SESSION["test"] будет
неопределена.

После старта сессии, при втором и последующих вызовах - ничего происходить не будет.

При старте новой сессии вторым параметром можно передать ключи для уточнения параметров сессии.

Существуют следующие параметры:
"expire"- время в секундах, время жизни сессии, 0 - означает до закрытия браузера, по-умолчанию: 0
"path" - путь по которому сессия будет доступна (используется в setcookie), например: /admin, по-умолчанию: /
"domain" - домен по которому сессия будет доступна (используется в setcookie), например: .example.com
"secure" -  true или false. Использовать ли SSL (используется в setcookie), по-умолчанию false.
"protected" - true или false. Защищённая ли сессия, если true то при считывании данных и сохранении данных
              сессии будет проверяться IP-адрес с которого сессия стартовала, если он не совпадает - данные восстановлены не будут.
              по-умолчанию: false
"ip" - IP-адрес для защищённых сессий, по-умолчанию: текущий ($_SERVER['REMOTE_ADDR'])


Session::alter()
С помощью данного вызова (но только после вызова Session::start()) вы можете
изменить время хранения данной сессии, а также остальные параметры перечисленые выше.
Таким образом используя Session::alter() для уже инициализированной сессии вы измените её время
хранения на новое КОНКРЕТНО ДЛЯ ТЕКУЩЕГО пользователя. Все сессии с данным именем стартовавшие ранее затронуты не будут.
При следующей загрузке страницы при вызове функции Session::start(), и при наличии данной сессии будет использоваться
время хранения которое было указано для неё в последний раз.
Если вы хотите удалить данную сессию - вызовите функцию Session::alter() с отрицательным параметром sess_expire, либо вызовите
Session::destroy(string $session_id) - $session_id можно получить вызовом Session::id(string $session_name)

ИСПОЛЬЗОВАНИЕ Flash-переменных
------------------------------
Чтобы использовать flash-переменные нужно инициализировать механизм при старте приложения вызовом:
Session::flash_init();

После данного вызова можно использовать Session::flash().
В приложении рекомендуется создать более удобный хелпер flash() который будет вызывать данный метод.

Что такое flash-переменные? Это переменные которые сохраняются в специальной сессии, которые будут доступны при следующей загрузке страницы.
После этого эти переменные исчезают навсегда. Это удобно например для оповещения пользователя каким-то сообщением единжды.
К примеру после сохранения каких либо данных в контроллере можно вызвать: flash("notice", "Данные успешно сохранены!") и затем
перенаправить браузер на какую-нибудь страницу где используется вызов flash("notice").
После редиректа flash("notice") - вернёт "Данные успешно сохранены!" и это можно оформить в виде:
<? if (flash("notice")): ?>
	<script>alert('<?= flash("notice") ?>');</script>
<? endif ?>

Остальные методы и их описания далее в комментариях по коду.
*/
class Session
{
	static private $shutdown_registered = false;
	static private $storage = array();
	static private $flash_storage = array();
	static private $flash_session_name = null;
	static private $standard_save_handler = null;
	static private $save_handlers = array();

	static function start($sess_name, $sess_params = array())
	{
		if (error_reporting())
		{
			if (!isset($sess_name)) { return throw_error("Session variable name not specified"); }
			elseif (!is_scalar($sess_name)) { return throw_error("Invalid session variable name, need a scalar value"); }
			elseif (!preg_match("/^[a-zA-Z0-9_]+$/su", $sess_name)) { return throw_error("Invalid session variable name"); }
		}

		$sess_id = null;
		$sess_cookie_name = self::cookie_name($sess_name);

		if (isset($_COOKIE[$sess_cookie_name])) { $sess_id = $_COOKIE[$sess_cookie_name]; }
		if (!preg_match("/^[a-f0-9]{64}$/u", $sess_id)) { $sess_id = self::generate_id(); }

		// При самом первом вызове функции - устанавливаем функцию для сохранения данных сессии
		if (!self::$shutdown_registered)
		{
			self::$shutdown_registered = true;
			register_shutdown_function(array("Session", "close_all"));
			if (get_probability(1)) { self::gc(); }
		}

		// Если сессия уже считана - не делаем ничего
		if (!isset(self::$storage[$sess_name]))
		{
			if (!isset($_SESSION)) { $_SESSION = array(); }

			// Обнуляем любые данные сессии перед стартом
			unset($_SESSION[$sess_name]);

			// Считываем данные сессии
			$sess = self::read_raw($sess_id);

			// Если они есть и не проходят проверку безопасности
			if ($sess !== null && !self::is_valid($sess)) { $sess_id = self::generate_id(); $sess = null; }

			// Если с сессией всё в порядке - восстанавливаем её данные и параметры
			if ($sess !== null) { $_SESSION[$sess_name] = $sess["data"]; $sess_params = $sess["params"]; }
			// Иначе используем параметры переданные при вызове (т.е. умолчательные)
			else { $sess_params = self::sanitize($sess_params); }

			// Сохраняем данные об этой сессии для функции сохранения данных
			self::$storage[$sess_name] = array
			(
				"id" => $sess_id,
				"params" => $sess_params
			);

			// Сохраняем параметры - переустанавливаем куки
			self::alter($sess_name, $sess_params);
		}
	}

	// Возвращает id сессии по её имени, или null если сессия не стартовала
	static function id($sess_name) { return isset(self::$storage[$sess_name]) ? self::$storage[$sess_name]["id"] : null; }

	// Формирует имя куки для сессии по имени сессии
	static function cookie_name($sess_name) { return "us_$sess_name"; }

	// Возвращает значение параметра сессии по имени сессии и имени параметра, или null если сессия не стартовала
	static function param($sess_name, $param_name)
	{
		if (isset(self::$storage[$sess_name]))
		{
			if (isset(self::$storage[$sess_name]["params"][$param_name])) { return self::$storage[$sess_name]["params"][$param_name]; }
			else { return null; }
		}
		else { return null; }
	}

	// Функция генерации уникального id сессии
	static function generate_id() { return md5(microtime(true) . random() . uniqid() . @$_SERVER["REMOTE_ADDR"] . getmypid()) . md5(microtime(true) . random() . uniqid()); }

	// Перегенерить id для текущей сессии
	static function regenerate_id($sess_name)
	{
		if (isset(self::$storage[$sess_name]))
		{
			self::$storage[$sess_name]["id"] = self::generate_id();
			self::alter($sess_name, self::$storage[$sess_name]["params"]);
			return self::$storage[$sess_name]["id"];
		}
	}

	// Изменения данных о сессии - должен вызываться после Session::start - изменяет данные только существующих сессий
	// Изменяет только переданные параметры, остальные остаются без изменений
	static function alter($sess_name, $sess_params = array())
	{
		if (isset(self::$storage[$sess_name]))
		{
			$sess_id = self::$storage[$sess_name]["id"];
			$sess_cookie_name = self::cookie_name($sess_name);
			$sess_params = (self::$storage[$sess_name]["params"] = self::sanitize(array_merge(self::$storage[$sess_name]["params"], $sess_params)));
			// Переустанавливаем куки, т.к. мог измениться любой из параметров + нам нужно обновить время хранения сессии в куках
			setcookie($sess_cookie_name, $sess_id, $sess_params["expire"] ? time() + $sess_params["expire"] : 0, $sess_params["path"], $sess_params["domain"], $sess_params["secure"]);
		}
	}

	// Метод чтения данных сессии - возвращает данные сессии. Попутно можно проверить на безопасность используя второй ключ (если это требуется).
	// Данный метод можно безопасно использовать для считывания данных сессии без необходимости её старта - данные сессии этот метод вернёт вам,
	// а супер-глобальный массив $_SESSION затронут не будет. Однако следует учесть что если вы используете данный метод, то скорее всего
	// данные этой сессии будут заблокированы до тех пор пока вы не вызовете write() или destroy()
	static function read($sess_id, $is_check = false)
	{
		$data = null;
		$sess = self::read_raw($sess_id);
		if ($sess !== null && $is_check && !self::is_valid($sess)) { $sess = null; }
		if ($sess !== null) { $data = $sess["data"]; }
		return $data;
	}

	// Функция записи данных сессии
	static function write($sess_id, $data, $params)
	{
		$params = self::sanitize($params);
		self::store($sess_id, array("params" => $params, "data" => $data), $params["expire"]);
	}

	// Уничтожение данных сессии
	static function destroy($sess_id) { self::delete($sess_id); }

	static function standard_save_handler()
	{
		if (self::$standard_save_handler === null) { self::$standard_save_handler = new Session_StandardHandler(); }
		return self::$standard_save_handler;
	}

	// Метод для установки user-defined методов для управления данными сессий
	// Если вызван с одним первым параметром - значит этот параметр должен быть объектом, с публичными
	// методами: fetch, store, delete, gc (не обязательно)
	static function save_handlers($fetch_or_object, $store = null, $delete = null, $gc = null)
	{
		if (is_object($fetch_or_object))
		{
			$fetch = array(&$fetch_or_object, "fetch");
			$store = array(&$fetch_or_object, "store");
			$delete = array(&$fetch_or_object, "delete");
			if (is_callable(array(&$fetch_or_object, "gc"))) { $gc = array(&$fetch_or_object, "gc"); }
		}
		else { $fetch = $fetch_or_object; }

		if (!is_callable($fetch)) { throw_error("Handler for 'fetch' operation is not callable.", true); }
		if (!is_callable($store)) { throw_error("Handler for 'store' operation is not callable.", true); }
		if (!is_callable($delete)) { throw_error("Handler for 'delete' operation is not callable.", true); }
		if ($gc !== null && !is_callable($gc)) { throw_error("Handler for 'gc' operation is not callable.", true); }

		// Метод должен принимать один параметр: $sess_id и возвращать данные сессии (несериализованные) или null если данных нет
		self::$save_handlers["fetch"] = $fetch;

		// Метод должен принимать три параметра: $sess_id, $session_data, $ttl
		self::$save_handlers["store"] = $store;

		// Метод должен принимать один параметр: $sess_id - должен удалять данные сессии
		self::$save_handlers["delete"] = $delete;

		// Метод для сборки мусора - вероятность запуска: 1%
		self::$save_handlers["gc"] = $gc;
	}

	// Функция сохранения данных для всех открытых в данный момент сессий
	static function close_all()
	{
		if (is_array(self::$storage))
		{ foreach (self::$storage as $name => $sess_info) self::close($name); }
	}

	// Закрывает ранее открытую сессиию (с сохранением данных)
	static function close($sess_name)
	{
		if (isset(self::$storage[$sess_name]))
		{
			$sess = self::$storage[$sess_name];
			unset(self::$storage[$sess_name]);

			// Если данные этой сессии существуют и время хранения положительное - то данные нужно сохранить
			if (array_key_exists($sess_name, $_SESSION) && $sess["params"]["expire"] >= 0)
			{
				$sess["params"]["ip"] = @$_SERVER["REMOTE_ADDR"];
				self::write($sess["id"], $_SESSION[$sess_name], $sess["params"]);
			}
			// Если данных нет или время хранения отрицательное
			// значит данную сессию хотят уничтожить или же не было смысла её создавать
			else { self::destroy($sess["id"]); }
		}
	}

	// Функцию нужно вызывать в самом начале приложения для инициализации flash-переменных - лучше вызывать всегда
	// Стартует новую сессию с имеем ___flashdata___ или именем указанным первым параметром в данной функции
	static function flash_init($sess_name = "___flashdata___")
	{
		if (self::$flash_session_name === null) { self::$flash_session_name = $sess_name; }
		self::start(self::$flash_session_name);
		if (isset($_SESSION[self::$flash_session_name]))
		{
			foreach ($_SESSION[self::$flash_session_name] as $name => $value) { self::$flash_storage[$name] = $value; }
			unset($_SESSION[self::$flash_session_name]);
		}
	}

	// Получение или установка flash-переменной
	static function flash($name, $value = null)
	{
		if ($value === null)
		{
			if (isset(self::$flash_storage[$name])) { return self::$flash_storage[$name]; }
			return null;
		}
		else { return ($_SESSION[self::$flash_session_name][$name] = $value); }
	}

	// Метод чтения данных сессии - считывает данные с помощью обработчика и приводит их к нормальному виду.
	static private function read_raw($sess_id)
	{
		// Считываем данные этой сессии
		$sess = self::fetch($sess_id);

		// Проверим формат данных - если сессия повреждена - возвращаем null (данных нет)
		if ($sess !== null && is_array($sess) && array_key_exists("data", $sess) && @is_array($sess["params"]))
		{ $sess["params"] = self::sanitize($sess["params"]); } else { $sess = null; }

		return $sess;
	}

	// Сессия не проходит проверку если она защищённая и ай-пи не совпадает
	static private function is_valid($sess)
	{
		if (@$sess["params"]["protected"] && @$sess["params"]["ip"] != @$_SERVER["REMOTE_ADDR"]) { return false; }
		return true;
	}

	// Функция из переданного массива создаёт массив только с реально правильными параметрыми сессии или их дефолт-значениями
	static private function sanitize($params)
	{
		$return_params = array();
		if (!is_array($params)) { $params = array(); }
		if (!array_key_exists("expire", $params)) { $return_params["expire"] = 0; } else { $return_params["expire"] = @intval($params["expire"]); }
		if (!array_key_exists("path", $params)) { $return_params["path"] = "/"; } else { $return_params["path"] = @strval($params["path"]); }
		if (!array_key_exists("domain", $params)) { $return_params["domain"] = ""; } else { $return_params["domain"] = @strval($params["domain"]); }
		if (!array_key_exists("secure", $params)) { $return_params["secure"] = false; } else { $return_params["secure"] = !!$params["secure"]; }
		if (!array_key_exists("protected", $params)) { $return_params["protected"] = false; } else { $return_params["protected"] = !!$params["protected"]; }
		if (!array_key_exists("ip", $params)) { $return_params["ip"] = @$_SERVER["REMOTE_ADDR"]; } else { $return_params["ip"] = @strval($params["ip"]); }
		return $return_params;
	}

	/******* ОБРАБОТЧИКИ ДАННЫХ СЕССИЙ ***********/

	// Функция считывания данных сессии - функция должна вернуть null если сессии нет или она просрочена
	// в противном случае функция должна вернуть теже самые данные что были переданы ей на сохранение
	// т.е. сериализацией/десериализацией (если необходимо) функция должна заниматься сама
	// формат возвращаемых данный прост: array("params" => array(...), "data" => ...)
	static private function fetch($sess_id)
	{
		if (array_key_exists("fetch", self::$save_handlers)) { return call_user_func(self::$save_handlers["fetch"], $sess_id); }
		else { return self::standard_save_handler()->fetch($sess_id); }
	}

	// Функция сохранения данных сессии - на вход - id сессии, время хранения и её данные
	// ВНИМАНИЕ!!! Время хранения - это число секунд указанное при инициализации сессии - оно может быть равно 0
	// поэтому нужно учитывать этот вариант - он означает что сессия хранится на время открытия браузера
	static private function store($sess_id, $sess_data, $sess_expire)
	{
		if (array_key_exists("store", self::$save_handlers)) { return call_user_func(self::$save_handlers["store"], $sess_id, $sess_data, $sess_expire); }
		else { return self::standard_save_handler()->store($sess_id, $sess_data, $sess_expire); }
	}

	// Функция удаления сессии и её данных - на вход id сессии
	static private function delete($sess_id)
	{
		if (array_key_exists("delete", self::$save_handlers)) { return call_user_func(self::$save_handlers["delete"], $sess_id); }
		else { return self::standard_save_handler()->delete($sess_id); }
	}

	static private function gc()
	{
		if (array_key_exists("gc", self::$save_handlers)) { return (self::$save_handlers["gc"] !== null ? call_user_func(self::$save_handlers["gc"]) : null); }
		else { return self::standard_save_handler()->gc(); }
	}
}

// Стандартный класс-обработчик работы с данными сессий
class Session_StandardHandler
{
	private $save_path = null;
	private $file_handlers = array();
	private $sessions_data = array();

	// Возвращает полный путь к файлу данных сессии по её id
	private function session_file_path($sess_id) { return $this->save_path() . "/us_" . $sess_id; }

	// Функция для получения или установки пути для сохранения файлов сессий
	function save_path()
	{
		$func_args = func_get_args();

		// Хотим переназначить путь или вернуть к дефолтному (null)
		if (count($func_args)) { $this->save_path = $func_args[0]; }

		// Путь ещё не выбран - выбираем
		if ($this->save_path === null)
		{
			$tmp_dirs = array();

			if (!is_empty(session_save_path())) { $tmp_dirs[] = preg_replace("/^([^;]+;){0,2}/sux", "", session_save_path()); }
			$tmp_dirs[] = sys_get_temp_dir();
			$tmp_dirs[] = @$_ENV["TMP"] ? $_ENV["TMP"] : getenv("TMP");
			$tmp_dirs[] = @$_ENV["TEMP"] ? $_ENV["TEMP"] : getenv("TEMP");
			$tmp_dirs[] = @$_ENV["TMPDIR"] ? $_ENV["TMPDIR"] : getenv("TMPDIR");
			$tmp_dirs[] = "/var/tmp";
			$tmp_dirs[] = "/tmp";

			foreach ($tmp_dirs as $tmp) { if (is_dir($tmp) && is_writeable($tmp)) { $this->save_path = $tmp; break; } }
			if ($this->save_path === null) { $this->save_path = $tmp; }
		}

		return $this->save_path;
	}

	// Функция считывания данных сессии - функция должна вернуть null если сессии нет или она просрочена
	// в противном случае функция должна вернуть теже самые данные что были переданы ей на сохранение
	// т.е. сериализацией/десериализацией (если необходимо) функция должна заниматься сама
	// формат возвращаемых данный прост: array("params" => array(...), "data" => ...)
	function fetch($sess_id)
	{
		$sess_data = null;
		$sess_file_name = $this->session_file_path($sess_id);

		$fp = null;
		// Если мы файл уже открыли - берём его указатель
		if (array_key_exists($sess_id, $this->file_handlers)) { $fp = $this->file_handlers[$sess_id]; }
		// Иначе открываем файл и сохраняем его указатель если успешно открыли
		else
		{
			$fp = @fopen($sess_file_name, "r+");
			if ($fp) { $this->file_handlers[$sess_id] = $fp; }
		}

		// Если у нас есть успешно открытый указатель файла сессии
		if ($fp)
		{
			// Эксклюзивный блок - нельзя считывать сессию пока в неё не сохранили данные
			$is_locked = flock($fp, LOCK_EX | LOCK_NB);

			// Если у нас уже имеются считанные данные этой сессии и файл заблокирован, значит он заблокирован нами
			// Может спокойно вернуть данные, т.к. блокировка "до записи" из-за этого не снимется
			if ($is_locked && array_key_exists($sess_id, $this->sessions_data))
			{ $sess_data = $this->sessions_data[$sess_id]; }
			// Иначе если ещё нет данных (сессия заблокирована кем-то другим)
			// или мы можем заблокировать данные сессии (мы первые кто будет читать и писать данные)
			else
			{
				flock($fp, LOCK_EX);
				// Первой строчкой хранится время хранения сессии
				$sess_expire = @intval(trim(fgets($fp, 1024)));
				// Если сессия не просроченная - читаем её данные
				if (time() <= $sess_expire)
				{
					// Читаем файл
					$buffer = null; while (!feof($fp)) { $buffer .= @fread($fp, 1024); }
					if (!is_empty($buffer)) { $sess_data = @unserialize($buffer); }
					if ($sess_data === false) { $sess_data = null; }
				}
				$this->sessions_data[$sess_id] = $sess_data;
			}
		}

		return $sess_data;
	}

	// Функция сохранения данных сессии - на вход - id сессии, время хранения и её данные
	// Время хранения сессии
	function store($sess_id, $sess_data, $sess_expire)
	{
		$sess_file_name = $this->session_file_path($sess_id);
		if (isset($this->file_handlers[$sess_id])) { $fp = $this->file_handlers[$sess_id]; }

		// Если файл сессии уже открыт - обнуляем
		if (isset($fp) && $fp) { ftruncate($fp, 0); fseek($fp, 0); }
		// Иначе пробуем создать новый
		else
		{
			$fp = @fopen($sess_file_name, "w+");
			if ($fp) { flock($fp, LOCK_EX); } else { trigger_error("Cannot open " . $sess_file_name . " for write: " . @$php_errormsg, E_USER_WARNING); }
		}
		// Если в итоге имеется открытый файл сесии - сохраняем в него данные и закрываем
		if ($fp)
		{
			fwrite($fp, time() + ($sess_expire == 0 ? 60 * 60 * 24 * 14 : $sess_expire) . "\n");
			fwrite($fp, @serialize($sess_data));
			fclose($fp);
		}
		// Уничтожаем закэшированные данные сессии
		unset($this->sessions_data[$sess_id]);
	}

	// Функция удаления сессии и её данных - на вход id сессии
	function delete($sess_id)
	{
		$sess_file_name = $this->session_file_path($sess_id);
		if (isset($this->file_handlers[$sess_id]) && $this->file_handlers[$sess_id])
		{ fclose($this->file_handlers[$sess_id]); @unlink($sess_file_name); }
		// Уничтожаем закэшированные данные сессии
		unset($this->sessions_data[$sess_id]);
	}

	function gc()
	{
		$dir = @opendir($this->save_path());
		if ($dir)
		{
			while ($file = @readdir($dir))
			{
				if (substr($file, 0, 3) != "us_") { continue; }
				$file_path = $this->save_path() . "/" . $file;
				$fp = @fopen($file_path, "r");
				if ($fp)
				{
					flock($fp, LOCK_SH);
					$sess_expire = @intval(trim(fgets($fp, 1024)));
					if (time() > $sess_expire) { fclose($fp); @unlink($file_path); }
					else { fclose($fp); }
				}
			}
		}
	}
}