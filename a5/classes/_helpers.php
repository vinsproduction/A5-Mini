<?php
// Функция создаёт из линейного массива переданного на вход ассоциативный массив, в качестве ключей беруться чётные
// ёлементы, в качестве значение нечётные
function list2hash($arr)
{
	$hash = array();
	if (!is_array($arr)) { $arr = array($arr); }
	$i = 0; $key = null;
	foreach ($arr as $item)
	{
		if ($i % 2 == 0) { $key = (string) $item; }
		else { $hash[$key] = $item; }
		$i++;
	}
	return $hash;
}

/*
Функция для подготовки массива к выводу вертикальным способом
Функция на вход принимает - массив, количество колонок для вывода массива
и третий не обязательный параметр - если передать его как true то ключи массива
не будут сохранены, иначе измениться только порядок элементов

Пример работы функции:
Допустим у вас есть сортированный массив: array(0 => "a", 1 => "b", 2 => c, 3 => "d", 4 => "e", 5 => "f", 6 => "g")
И вы хотите вывести его в три колонки - выводя по-порядку каждый элемент и добавляя перенос
строки после каждого третьего вы получите что-то вроде:
a b c
d e f
g

Применив же данную функцию, на выходе вы получите такой массив: array(0 => "a", 3 => "d", 5 => "f", 1 => "b", 4 => "e", 6 => "g", 2 => "c")
и соотвественно при выводе его тем же способом вы получите:
a d f
b e g
c
*/
function verticalize_list($list, $columns, $is_refresh_keys = false)
{
	$count = count($list);
	if ($count > 0)
	{
		$rows = ceil($count / $columns);
		$decrease_on = ($columns - ($columns * $rows) % $count) * $rows;
		$verticalized = array();
		for ($c = 0; $c < $rows; $c++)
		{
			$i = 0;
			foreach ($list as $key => $val)
			{
				if
				(
					($i < $decrease_on && ($i - $c) % $rows == 0)
					||
					($i >= $decrease_on && $c != ($rows - 1) && ($i - $decrease_on - $c) % ($rows - 1) == 0)
				)
				{
					if ($is_refresh_keys) { $verticalized[] = $val; }
					else { $verticalized[$key] = $val; }
				}
				$i++;
			}
		}
		return $verticalized;
	}
	return $list;
}

/*
Преобразует древовидную (бесконечной вложенности) структуру в сплошной список в порядке
обхода дерева с добавлением ключа "уровень" для обозначения глубины вложенности
Каждый элемент дерева должен содержать ключ "child_nodes" или иное указанное в соответствующем
параметре при вызове функции, данный ключ должен содержать список всех дочерних объектов,
которые в свою очередь могут также содержать данный ключ.
Пример:
	$tree = array
	(
		array
		(
			"name" => "Section 1", "child_nodes" => array
			(
				array("name" => "Subsection 1 1", "child_nodes" => array()),
				array("name" => "Subsection 1 2", "child_nodes" => array()),
				array("name" => "Subsection 1 3", "child_nodes" => array()),
			)
		),
		array
		(
			"name" => "Section 2", "child_nodes" => array
			(
				array("name" => "Subsection 2 1", "child_nodes" => array
				(
					array("name" => "Subsection 2 1 1", "child_nodes" => array()),
					array("name" => "Subsection 2 1 2", "child_nodes" => array()),
				)),
				array("name" => "Subsection 2 2", "child_nodes" => array()),
			)
		),
	)

	$list = tree_to_list($tree);

	На выходе получим:
	$list = array
	(
		array("name" => "Section 1", "level" => 0),
		array("name" => "Subsection 1 1", "level" => "1"),
		array("name" => "Subsection 1 2", "level" => 1),
		array("name" => "Subsection 1 3", "level" => 1),
		array("name" => "Section 2", "level" => "0"),
		array("name" => "Subsection 2 1", "level" => 1),
		array("name" => "Subsection 2 1 1", "level" => 2),
		array("name" => "Subsection 2 1 2", "level" => 2),
		array("name" => "Subsection 2 2", "level" => 1),
	);
*/
function tree_to_list($tree, $start_level = 0, $child_nodes_key = null, $level_key = null)
{
	if ($child_nodes_key === null) { $child_nodes_key = "child_nodes"; }
	if ($level_key === null) { $level_key = "level"; }
	$list = array();
	foreach ($tree as $i => $item)
	{
		$list[$i] = $item;
		if (is_array($item))
		{
			$list[$i][$level_key] = $start_level;
			unset($list[$i][$child_nodes_key]);
			if (array_key_exists($child_nodes_key, $item) && is_array($item[$child_nodes_key]) && count($item[$child_nodes_key]))
			{ $list = $list + tree_to_list($item[$child_nodes_key], $start_level + 1, $child_nodes_key, $level_key); }
		}
	}
	return $list;
}

// Обход всего дерева с какой-либо целью
// Первый параметр: аналогично функции tree_to_list
// Второй и третий: callback-функция, на вход принимает текущий элемент первым параметром и его родительский - вторым
// можно использовать передачу по ссылке чтобы произвести какие-то манипуляции с данными.
// Отличие второго параметра от третьего в том что его вызов происходит до прохождения рекурсии.
// Иными словами вызов функции из второго параметра будет происходить в порядке обхода дерева, с узла самого верхнего уровня
// до узла самого последнего, а в третьем параметре будет происходить обратный эффект, его вызов начнётся с ноды самого
// последнего уровня до ноды самого верхнего уровня.
// Четвёртый параметр указывает на то когда была вызвана функция: before или after
// Данный параметр полезен если вы будете использовать одну и ту же функцию для обоих вариантов вызова.
function tree_walk($tree, $callback_before = null, $callback_after = null, $child_nodes_key = null)
{
	if ($child_nodes_key === null) { $child_nodes_key = "child_nodes"; }
	$recursive = function(&$child_nodes, &$parent_node = null) use (&$recursive, $callback_before, $callback_after, $child_nodes_key)
	{
		foreach ($child_nodes as $node_id => $node)
		{
			if ($callback_before !== null) { $callback_before($child_nodes[$node_id], $parent_node, "before"); }
			$recursive($child_nodes[$node_id][$child_nodes_key], $child_nodes[$node_id]);
			if ($callback_after !== null) { $callback_after($child_nodes[$node_id], $parent_node, "after"); }
		}
	};
	$recursive($tree);
	return $tree;
}

// Урезание дерева на основе каких-либо условий
// Первый параметр: аналогично функции tree_to_list
// Второй: callback-функция, на вход принимает текущий элемент первым параметром и его родительский - вторым
// Функция должна вернуть true если нужно оставить в дереве текущий элемент, это означает что в дереве останется
// текущий элемент и все его родительские элементы, если функция вернёт false то текущий элемент будет убран из дерева
// и также возможно будут убраны все его родительские (если для них функция также вернула false)
function tree_reduce($tree, $callback, $child_nodes_key = null)
{
	if ($child_nodes_key === null) { $child_nodes_key = "child_nodes"; }
	$recursive = function(&$child_nodes, &$parent_node = null) use (&$recursive, $callback, $child_nodes_key)
	{
		// Имеет ли родительская нода хотя бы один дочерний видимый объект
		$is_parent_has_visible_nodes = false;
		foreach ($child_nodes as $node_id => $node)
		{
			$is_visible_node = $callback($child_nodes[$node_id], $parent_node);
			// Рекурсивно проверяем на видимость все дочерние ноды
			$is_node_has_visible_childs = $recursive($child_nodes[$node_id]["child_nodes"], $child_nodes[$node_id]);
			// Если нода не является видимой и не имеет ни одной видимой дочерней ноды - убираем всю ветку,
			// иначе сообщаем о том что текущая родительская ветка имеет хотя бы одну видимую дочернюю
			if (!$is_visible_node && !$is_node_has_visible_childs) { unset($child_nodes[$node_id]); } else { $is_parent_has_visible_nodes = true; }
		}
		return $is_parent_has_visible_nodes;
	};
	$recursive($tree);
	return $tree;
}

// Функция рекурсивного создания директорий
function mkdirs($path, $mode = 0777)
{
	if (@is_dir($path)) { return true; }
	$result = @mkdir($path, $mode, true);
	if (!$result)
	{
		throw_error("Cannot create $path: " . $php_errormsg);
		$GLOBALS["php_errormsg"] = $php_errormsg;
	}
	return $result;
}

// Функция рекурсивного удаления содержимого директории
function rmdirs($path)
{
	if (!@is_dir($path) || @is_link($path))
	{
		$r = @unlink($path);
		if (false === $r)
		{
			$GLOBALS["php_errormsg"] = $php_errormsg;
			return throw_error("Cannot unlink $path: " . $GLOBALS["php_errormsg"]);
		}
		return true;
	}

	if (@is_dir($path))
	{
		if (@rmdir($path)) { return true; }
		$dir = @opendir($path);
		if (false === $dir)
		{
			$GLOBALS["php_errormsg"] = $php_errormsg;
			return throw_error("Cannot open $path: " . $GLOBALS["php_errormsg"]);
		}
		while (false !== $item = readdir($dir))
		{
			if ($item == "." || $item == "..") continue;
			if (!rmdirs("$path/$item")) { return false; }
		}
		@closedir($dir);
		$r = @rmdir($path);
		if (false === $r)
		{
			$GLOBALS["php_errormsg"] = $php_errormsg;
			return throw_error("Cannot unlink $path: " . $GLOBALS["php_errormsg"]);
		}
		return true;
	}
	else
	{
		$GLOBALS["php_errormsg"] = $php_errormsg;
		return throw_error("Cannot unlink $path: " . $GLOBALS["php_errormsg"]);
	}
}

// Функция рекурсивного удаления пустых папок.
// На вход получает путь к папке, проходится по содержимому всех
// вложенных папок и удаляет все папки не содержащие данные, также удаляет указанную папку
// если она в итоге окажется пустой.
// Функция возвращает true если папка была удалена (т.е. не содеражала ничего, либо только пустые подпапки)
// Функция возвращает null если папка была не пустая, это значит что пустые подпапки в ней были удалены (частично очищена)
// Функция возвращает false и сообщение об ошибке если папку не смогли удалить (за неимением прав к примеру)
function cleandir($path)
{
	if (!@is_dir($path))
	{
		$GLOBALS["php_errormsg"] = "Not a directory";
		return throw_error("Cannot unlink $path: " . $GLOBALS["php_errormsg"]);
	}
	else
	{
		$dir = @opendir($path);
		if (false === $dir)
		{
			$GLOBALS["php_errormsg"] = $php_errormsg;
			return throw_error("Cannot open $path: " . $GLOBALS["php_errormsg"]);
		}
		$is_empty_dir = true;
		while (false !== $item = readdir($dir))
		{
			if ($item == "." || $item == "..") continue;
			if (!is_dir("$path/$item")) { $is_empty_dir = false; continue; }
			else
			{
				$r = cleandir("$path/$item");
				if ($r === null) { $is_empty_dir = false; }
				if ($r === false) { return false; }
			}
		}
		@closedir($dir);
		if ($is_empty_dir)
		{
			if (@rmdir($path)) { return true; }
			else
			{
				$GLOBALS["php_errormsg"] = $php_errormsg;
				return throw_error("Cannot unlink $path: " . $GLOBALS["php_errormsg"]);
			}
		} else { return null; }
	}
}

// Возвращает true если передан абсолютный путь к файлу (от корня)
function is_absolute_path($path) { return preg_match("/^([a-zA-Z]:|\/)/s", $path) ? true : false; }

// Возвращает расширение файла если оно есть, на вход имя файла или полный путь
function get_file_extension($name, $default = "") { return preg_match("/^.*(\.[a-zA-Z0-9_-]+)$/s", $name, $regs) ? strtolower($regs[1]) : $default; }

// Функция делает нормальный путь из пути который содержит "dir/../" или "./"
function normalize_path($path)
{
	$path = str_replace("\\", "/", trim($path)); $disk = null;
	if (preg_match("~^([a-zA-Z]:/)(.*)~sx", $path, $regs)) { $disk = $regs[1]; $path = $regs[2]; }
	elseif (preg_match("~^(/)(.*)~sx", $path, $regs)) { $disk = $regs[1]; $path = $regs[2]; }

	while (false !== strpos($path, "/./")) { $path = str_replace("/./", "/", $path); }
	$path = preg_replace("~/+~sux", "/", $path);
	if (substr($path, 0, 2) == "./") { $path = substr($path, 2); }
	$path = rtrim($path, "/");

	// Делим полный путь на части
	$segment = explode("/", $path); $i = 0;
	while ($i < count($segment))
	{
		// Если за текущим сегментом есть ещё один
		if ($i + 1 < count($segment))
		{
			// И текущий не равен ".." а следующий равен ".."
			if ($segment[$i] != ".." && $segment[$i + 1] == "..")
			{
				unset($segment[$i], $segment[$i + 1]);
				$segment = array_values($segment);
				$i--; if ($i < 0) { $i = 0; }
				continue;
			}
		}
		$i++;
	}

	$path = implode("/", $segment);
	// Убираем все "../" последовательности из начала
	$path = preg_replace("~^(?:\.\.(?:/|$))+~sux", "", $path);

	return $disk . $path;
}

function bytes_from_size($size)
{
	if (!is_numeric($size))
	{
		if (preg_match("/k/is", $size))
		{ $size = intval($size)*1024; }
		elseif (preg_match("/m/is", $size))
		{ $size = intval($size)*1024*1024; }
		elseif (preg_match("/g/is", $size))
		{ $size = intval($size)*1024*1024*1024; }
		elseif (preg_match("/t/is", $size))
		{ $size = intval($size)*1024*1024*1024*1024; }
		elseif (preg_match("/p/is", $size))
		{ $size = intval($size)*1024*1024*1024*1024*1024; }
	}
	return $size;
}

function human_size($size)
{
	if (file_exists($size)) { $size = filesize($size); }
	else { $size = bytes_from_size($size); }
	$postfix = array("bytes", "Kb", "Mb", "Gb", "Tb", "Pb");
	$i = 0;
	while ($size >= 1024) { $size /= 1024; $i++; }
	return number_format($size, $i ? 2 : 0, ".", "") . " " . $postfix[$i];
}

// Функция возвращающая ETag для файла - такой же как возвращает Apache
function file_etag($filename) { return dechex(fileinode($filename)) . '-' . dechex(filesize($filename)) .'-' . dechex(filemtime($filename)); }

// Более короткий :) алиас htmlspecialchars
function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, "utf-8"); }

// Функция - обратная h(), но более расширенная
function decode_h($s) { return html_entity_decode((string) $s, ENT_QUOTES, "utf-8"); }

// Функция вывода текста (BLOB) из базы
function hm($s) { return nl2br(h($s)); }

// Функция для квотирования строковой переменной
// В строку JavaScript, экранирует все опасные символы
function j($s)
{
	$s = str_replace("\\", "\\\\", $s);
	$s = str_replace("'", "\\'", $s);
	$s = str_replace("\n", "\\n", $s);
	$s = str_replace("\r", "\\r", $s);
	return $s;
}

// Короткий вызов вместо htmlspecialchars(j(...))
function hj($s) { return h(j($s)); }

function is_empty($s) { return strlen(trim($s)) ? false : true; }

// Без параметров - возвращает float значение от 0 до 1 включительно
// С одним параметром возвращает число от 0 до указанного параметра включительно
// С двумя параметрами возвращает число от указанного до указанного включительно
function random($from = null, $to = null)
{
	if ($from !== null && $to !== null) { return mt_rand($from, $to); }
	elseif ($from !== null && $to === null) { return mt_rand(0, $from); }
	else { return mt_rand(0, mt_getrandmax())/mt_getrandmax(); }
}

// Возвращает true с вероятностью процентов указанных в параметре
// Например get_probability(30) с вероятностью 30 процентов вернёт true
function get_probability($percent) { return mt_rand(0, mt_getrandmax()) < $percent * mt_getrandmax() / 100; }

// Генерирует случайную комбинацию из 128 символов (или меньше если указано)
function generate_key($max = 128)
{
	$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
	$len = strlen($chars) - 1; $password = "";
	while ($max--) { $password .= $chars[random(0, $len)]; }
	return $password;
}

// Функция выдаёт ошибку и указывает на то место где она была вызвана за пределами ядра
function throw_error($message, $is_fatal = false)
{
	$traces = debug_backtrace();
	$core_dir = defined("CORE_DIR") ? CORE_DIR : normalize_path(__DIR__);

	// Найдём самый первый вызов не из папки ядра
	$trace = false;
	foreach ($traces as $i => $item)
	{
		if (!isset($item["file"])) { continue; }
		if (@$item["function"] == __FUNCTION__) { continue; }
		if (strpos(normalize_path(@$item["file"]), $core_dir . "/") !== 0) { $trace = $item; break; }
	}

	if ($trace)
	{
		if (error_reporting())
		{ echo "<b>" . ($is_fatal ? "Fatal error" : "Warning") . ":</b> " . h($message) . (isset($trace["file"]) ? " in <b>" . h($trace["file"]) . "</b>" : null) . (isset($trace["line"]) ? " on line <b>" . h($trace["line"]) . "</b>" : null); }
		if ($is_fatal) { exit; }
	}

	return false;
}

// Функция удаления лишних лидирующих пробелов и табуляций из каждой отдельной строки переданного текста
function ltrim_lines($text)
{
	if (!is_scalar($text)) { $text = @strval($text); }
	$text = preg_replace("/^\s*\r?\n/su", "", rtrim($text));
	$lines = preg_split("/(\r?\n)/u", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
	if (!$lines) return $text;
	$min_prefix = null;
	for ($i = 0, $c = count($lines); $i < $c; $i += 2)
	{
		if (!preg_match("/^\s*$/u", $lines[$i]))
		{
			preg_match("/^[ \t]*/su", $lines[$i], $m);
			if ($min_prefix === null || mb_strlen($m[0]) < mb_strlen($min_prefix)) { $min_prefix = $m[0]; }
		}
		else { $lines[$i] = ""; }
	}
	for ($i = 0, $count = count($lines); $i < $count; $i += 2)
	{ $lines[$i] = preg_replace("/^" . preg_quote($min_prefix, "/") . "/", "", rtrim($lines[$i])); }
	return implode("", $lines);
}

// Функция убирает из начала строки подстроку $replacement если она есть в $string
function substr_ltrim($string, $substring)
{ return substr($string, 0, strlen($substring)) === $substring ? substr($string, strlen($substring)): $string; }

// Функция убирает с конца строки подстроку $substring если она есть в в конце $string
function substr_rtrim($string, $substring)
{ return substr($string, -strlen($substring)) === $substring ? substr($string, 0, strlen($string) - strlen($substring)): $string; }

// Функция конвертирует любой текст в строку урезанную до указанного количества символов
// Убирая переносы строк, html-тэги, лишние пробелы внутри и по краям, добавляет "..."
// в конец если длина строки больше чем указанная длина
function text2string($value, $len = 60, $ellipsis = "...")
{
	$value = strip_tags($value);
	$value = decode_h($value);
	$value = str_replace(decode_h("&nbsp;"), " ", $value);
	$value = str_replace("\n", " ", $value);
	$value = str_replace("\r", " ", $value);
	$value = str_replace("\t", " ", $value);
	$value = preg_replace("/\s{2,}/u", " ", trim($value));
	if ($len && mb_strlen($value) > $len) { $value = mb_substr($value, 0, $len) . $ellipsis; }
	return $value;
}

// Возвращает текущий адрес страницы (без QUERY_STRING) - аналог $_SERVER["PHP_SELF"]
function self_url() { return preg_replace("/\?.*$/", null, this_url()); }

// Возвращает текущий (полный) адрес страницы, но без доменного имени
function this_url()
{
	$current_url = $_SERVER["REQUEST_URI"];
	$current_url = preg_replace("~^[a-z]+://[^/]+~", null, $current_url);
	return $current_url;
}

// Можно было бы использовать parse_url() но у него проблемы с utf-8 - он его иногда убивает
function split_url($string)
{
	$string = trim($string);
	$parts = array();

	if (preg_match("~^ ([a-z]+) (://) ~suxi", $string, $m)) { $parts["scheme"] = $m[1]; $string = substr($string, strlen($m[0])); }

	$string = preg_replace("~^//~sux", "", $string);

	if (isset($parts["scheme"]))
	{
		if (preg_match("~^ ([^:@]*) (:([^:@]*))? @ ~sux", $string, $m))
		{
			if (!@is_empty($m[1])) { $parts["user"] = $m[1]; }
			if (!@is_empty($m[3])) { $parts["pass"] = $m[3]; }
			$string = substr($string, strlen($m[0]));
		}

		if (preg_match("~^ ([^/:]+) (:(\d+))? ~sux", $string, $m))
		{
			if (!@is_empty($m[1])) { $parts["host"] = $m[1]; }
			if (!@is_empty($m[3])) { $parts["port"] = $m[3]; }
			$string = substr($string, strlen($m[0]));
		}
	}

	if (preg_match("~\#(.*)$~sux", $string, $m))
	{
		if (!@is_empty($m[1])) { $parts["fragment"] = $m[1]; }
		$string = substr($string, 0, -strlen($m[0]));
	}

	if (preg_match("~\?(.*)$~sux", $string, $m))
	{
		if (!@is_empty($m[1])) { $parts["query"] = $m[1]; }
		$string = substr($string, 0, -strlen($m[0]));
	}

	if (!@is_empty($string)) { $parts["path"] = $string; }

	if (!count($parts)) { return false; } else { return $parts; }
}

// Функция возвращает части УРЛ с закодированными частями в соответствии с RFC1738
function encoded_url_parts($string)
{
	$parts = split_url($string);
	if ($parts === false) { return false; }

	static $unsafe = array();

	if (!count($unsafe))
	{
		$alpha = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
		$digit = "01234567890";
		$safe = "$-_.+";
		$extra = "!*'(),";
		$unreserved = $alpha . $digit . $safe . $extra;
		$uchar = $unreserved . "%";

		$_safe = array();
		$_safe["scheme"] = $alpha;
		$_safe["host"] = $alpha . $digit . "-.";
		$_safe["port"] = $digit;
		$_safe["user"] = $uchar . ";?&=";
		$_safe["pass"] = $uchar . ";?&=";
		$_safe["path"] = $uchar . ";:@&=/";
		$_safe["query"] = $uchar . ";:@&=/";

		// Заполняем таблицы перекодировки для каждой части УРЛ
		foreach ($_safe as $k => $v)
		{
			if (!array_key_exists($k, $unsafe)) { $unsafe[$k] = array(); }
			for ($i = 0; $i <= 255; $i++)
			{
				// Если символ не является безопасным
				$char = chr($i);
				if (false === strpos($v, $char))
				{ $unsafe[$k][$char] = "%" . strtoupper(dechex($i)); }
			}
		}
	}

	foreach ($parts as $k => $v)
	{
		if ($k == "fragment") { continue; }
		$parts[$k] = strtr($v, $unsafe[$k]);
	}

	return $parts;
}

// Функция составляет полный Url из его частей, на вход массив возврвщаемый функцией encoded_url_parts
function make_url_from_parts($parts)
{
	$url = null;
	if (array_key_exists("scheme", $parts)) { $url .= strtolower($parts["scheme"]) . "://"; }
	if (array_key_exists("user", $parts)) { $url .= $parts["user"]; }
	if (array_key_exists("pass", $parts)) { $url .= ":" . $parts["pass"]; }
	if (array_key_exists("user", $parts) || array_key_exists("pass", $parts)) { $url .= "@"; }
	if (array_key_exists("host", $parts)) { $url .= strtolower($parts["host"]); }
	if (array_key_exists("port", $parts)) { $url .= ":" . $parts["port"]; }
	if (array_key_exists("path", $parts)) { $url .= $parts["path"]; }
	if (array_key_exists("query", $parts)) { $url .= "?" . $parts["query"]; }
	if (array_key_exists("fragment", $parts)) { $url .= "#" . $parts["fragment"]; }
	return $url;
}

// $base_url - это корневой каталог страницы, относительно которой мы абсолютизируем
// указанную ссылку в $url. $base_url всегда должен оканчиваться "/".
// Например: если $base_url = "http://www.test.ru/products/"
// А $url = "../catalog.php"
// То функция вернёт "http://www.test.ru/catalog.php"
function make_absolute_url($base_url, $url)
{
	$url = trim($url);

	$url = str_replace("/./", "/", $url);
	if (strpos($url, "./") === 0) { $url = substr($url, 2); }

	// Если начинается на "//" то получаем протокол от $base_url и прибавляем наш $url
	if (strpos($url, "//") === 0) { $url = preg_replace("{^([a-zA-Z]+:)//[^/]+.*}s", "$1", $base_url) . $url; }
	// Если начинается на "/" то получаем корень от $base_url и прибавляем наш $url
	elseif (strpos($url, "/") === 0) { $url = preg_replace("{^([a-zA-Z]+://[^/]+).*}s", "$1", $base_url) . $url; }
	// Иначе если не начинается именем протокола
	elseif (!preg_match("{^[a-zA-Z]+:}s", $url)) { $url = $base_url . $url; }

	$parts = encoded_url_parts($url);

	// После этой строки мы уже должны иметь нормальный
	// кодированный УРЛ с абсолютным путём и с протоколом
	if (array_key_exists("path", $parts))
	{
		$parts["path"] = normalize_path(substr($parts["path"], 1));
		if (is_empty($parts["path"])) { unset($parts["path"]); } else { $parts["path"] = "/" . $parts["path"]; }
	}

	return make_url_from_parts($parts);
}

// Функция вернёт true если переданный урл абсолютный, т.е.
// если он составлен в полной форме: http://www.example.com/.....
function is_absolute_url($url)
{
	$url = trim($url);
	return preg_match("~^[a-zA-Z0-9_]+://[a-zA-Z0-9_-]+~", $url);
}

// Метод создаёт строку querystring на основе переданного ему массива
function build_query_string($data, $key = null)
{
	$res = array();
	foreach ((array)$data as $k => $v)
	{
		$tmp_key = rawurlencode($k);
		if ($key !== null) { $tmp_key = $key . '[' . $tmp_key . ']'; }
		if (is_array($v) || is_object($v)) { $res[] = build_query_string($v, $tmp_key); }
		else { $res[] = $tmp_key . "=" . rawurlencode($v); }
	}
	$separator = ini_get('arg_separator.output');
	return (count($res) && $key === null ? "?" : "") . implode($separator, $res);
}

// Метод аналогичный build_query_string с той лишь разницей что строка создаётся без лидирующего "?"
function build_post_data($data, $key = null)
{
	$post_data = build_query_string($data, $key);
	return !is_empty($post_data) ? substr($post_data, 1) : "";
}

// Метод создаёт последовательность "hidden" полей формы на основе переданного ему массива
function build_hidden_fields($data, $key = null)
{
	$res = array();
	foreach ((array)$data as $k => $v)
	{
		$tmp_key = h($k);
		if ($key !== null) { $tmp_key = $key . '[' . $tmp_key . ']'; }
		if (is_array($v) || is_object($v)) { $res[] = build_hidden_fields($v, $tmp_key); }
		else { $res[] = "<input type=\"hidden\" name=\"" . $tmp_key . "\" value=\"" . h($v) . "\" />"; }
	}
	return implode("", $res);
}


// Более удобный способ вывода отладочной информации
function printr($data) { echo "<xmp>"; print_r($data); echo "</xmp>"; }


// Функция для склонения чисел. Пример использования: declOfNum( 5, array( 'язык', 'языка', 'языков' ) )
function declOfNum($number, $titles, $view_number=true)
{
	$cases = array (2, 0, 1, 1, 1, 2);
	return ($view_number?$number." ":"").$titles[ ($number%100>4 && $number%100<20)? 2 : $cases[min($number%10, 5)] ];
}


// Функция для проверки переданных переменных
function if_ok($var){
	return (isset($var) && !empty($var)) ? $var : 0;
}


// Добротный var_export :)
// Example: varexp(array('a'=>'asdasd'),array('2'=>'33333'),'noexit');

function varexp($vars = '')
{

	$numargs = func_num_args();
	$args = func_get_args();

	if($numargs>1){

		foreach( $args as $i=>$arg){

			if( $arg != 'noexit'){

				print '<pre><b>Arg '.($i+1).':</b> ';
				var_export($arg); 
				print '</pre>';					
			}	
		}
		
	}else{
		
		print '<pre><b>Arg 1:</b> ';
		var_export($vars); 
		print '</pre>';
	
	}
	
	print '<hr>';
	
	if( !in_array('noexit',$args, true) || $vars === null) { exit; }

}

function varexp_($vars = '')
{

	$numargs = func_num_args();
	$args = func_get_args();

	if($numargs>1){

		foreach( $args as $i=>$arg){

			if( $arg != 'noexit'){

				print '<pre><b>Arg '.($i+1).':</b> ';
				var_export($arg); 
				print '</pre>';					
			}	
		}
		
	}else{
		
		print '<pre><b>Arg 1:</b> ';
		var_export($vars); 
		print '</pre>';
	
	}
	
	print '<hr>';

}

// Возвращает часть урла 

function get_uri_part($part = 0)
{
	$_uri = parse_url($_SERVER['REQUEST_URI']);
	$_path = $_uri['path'];
	$_parts = preg_split('/\//', $_path, -1, PREG_SPLIT_NO_EMPTY);

	return isset($_parts[$part]) ? $_parts[$part] : null;
}







