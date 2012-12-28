<?php
/* Класс для локализации вашего приложения, работает по прицнипу похожему на gettext
 * Использовать класс в вашем приложении можно сразу без какой-либо настройки.
 * Основные методы класса:
 * I18n::t($msg_id)
 * Возвращает переведённый вариант строки в соответствии с нужным языком и словарём
 *
 * I18n::t_plural($msg_id, $msg_many_id, $n)
 * Аналогичен предыдущему, но возвращает либо $msg_id либо $msg_many_id
 * в зависимости от третьего параметра $n, $msg_id используется для $n == 1
 * $msg_many_id используется для любых других значений $n
 * Поведение данного метода можно изменить, указав особое правило для данного языка
 * с помощью метода I18n::plural_rule() - см в описании класса.
 *
 * При отсутствии перевода оба метода возвращают назад тоже самое выражение которое вы
 * передали на вход.
 *
 * Для удобства пользования данными методами рекомендуется создать хелперы в вашем приложении.
 *
 * Например:
 * function ht() { return h(call_user_func_array(array("I18n", "t"), func_get_args())); }
 * Данная функция будет принимать на вход теже параметры что и метод t(), но после получения
 * перевода преобразовывать строку в безопасный для использования в HTML вид с помощью функции h()
 *
 * Следует учесть что если вы используете Framework A5 - он уже создаёт все необходимые хелперы
 * и их описание можно посмотреть в реализации класса A5
 *
 * В выражениях можно использовать интерполяцию (подстановку переменных параметров)
 * Более подробно об этом описано в комментариях к методу t(). Просто пример интерполяции:
 * I18n::t("% messages", 5) - вернёт "5 messages"
 *
 * Другие методы класса
 *
 * I18n::language($lang)
 * Выбор текущего языка для перевода, а также получение информации о текущем языке (вызов без параметров)
 *
 * I18n::module($name)
 * Выбор текущего модуля для перевода, а также получение текущего модуля (вызов без параметров)
 * Модули используются для создания пространства имён словарей для разных частей приложений.
 * При добавлении словарей вы указываете пространство имён для которого он предназначен (не обязательно).
 * Далее при назначении модуля данным методом, класс будет искать переводы приоритетно сначала среди
 * словарей для данного модуля, а при отсутствии перевода в них, переходить ко всем остальным.
 *
 * I18n::append($object, $module_name = null)
 * I18n::prepend($object, $module_name = null)
 * I18n::set($index, $object, $module_name = null)
 *
 * Методы добавляют словари для поиска переводов, словарь представляет собой инстанцию объекта,
 * который должен наследовать I18n_Dictonary и реализовывать описанные в нём методы.
 * Пример такого словаря реализован в классе I18n_Dictonary_Simple
 *
 * Отличие методов смотри в их описании ниже.
 */
class I18n
{
	// Содержит список ссылок на объекты словарей
	// Перевод делается в порядке следования этих словарей
	static private $first_index = 0;
	static private $last_index = 0;
	static private $dictonaries = array();
	static private $default_module = null;
	static private $module_cache = array();
	static private $translations_cache = array();
	static private $language = "en";
	static private $plural_rules = array();
	static private $unknown_translations = array();

	// Устанавливает имя модуля, для которого будут браться переводы
	// или возвращает имя текущего если ничего не передать в аргументах
	static function module($name = null)
	{
		if (func_num_args() > 0) { self::$default_module = $name; }
		else { return self::$default_module; }
	}

	// Устанавливает особые правила языка для множестенных чисел
	// $callback на вход принимает три параметра:
	// 1) $msg_id - сообщение в случае количества 1
	// 2) $msg_many_id - сообщение в случае количества не равное 1
	// 3) $n - само количество
	// На выход функция должна вернуть один из первых двух параметров, либо их модифицированный вариант
	// (к примеру с добавлением в его конец какого-то суффикса
	// Пример такой callback-функции для русского языка есть в конце этого файла
	static function plural_rule($language, $callback)
	{
		if (!$callback) { unset(self::$plural_rules[$language]); }
		else { self::$plural_rules[$language] = $callback; }
	}

	// Устанавливает имя языка для перевода
	// или возвращает имя текущего если ничего не передать в аргументах
	static function language($name = null)
	{
		if (func_num_args() > 0) { self::$language = $name; }
		else { return self::$language; }
	}

	// Добавляет новый словарь в список, каждый последующий вызов этого метода, сдвигает приоритет предыдущего добавленного
	// словаря на один пункт и ставит более приоритетным последний добавленный словарь
	// $instance - движок словаря, должен наследовать I18n_Dictonary
	// $module_name - указание что словарь добавляется для конкретного модуля
	// Метод возвращает числовой индекс данного словаря, который затем не меняется и используя индекс можно заменить
	// данный словарь каким-то другим, для этого нужно использовать метод I18n::set()
	static function append($instance, $module_name = null) { return self::set(--self::$first_index, $instance, $module_name); }

	// Метод полностью аналогичный методу append() за исключением того что словарь добавляется с самым низким приоритетом поиска
	static function prepend($instance, $module_name = null) { return self::set(++self::$last_index, $instance, $module_name); }

	// Вставляет словарь в указанное место в списке, $instance - движок словаря, должен наследовать I18n_Dictonary
	// Если $instance == false | null - словарь с указанным индексом убирается из списка поиска
	static function set($index, $instance, $module_name = null)
	{
		if ($index == 0 || ($index < 0 && $index != self::$first_index) || ($index > 0 && $index != self::$last_index)) { throw_error("Dictonary with index '" . $index . "' not found", true); }
		self::clean_caches();
		if (!$instance) { unset(self::$dictonaries[$index]); }
		else
		{
			if ($index > 0) { self::$dictonaries[$index] = array("object" => $instance, "module" => $module_name); }
			if ($index < 0) { self::$dictonaries = array($index => array("object" => $instance, "module" => $module_name)) + self::$dictonaries; }
		}
		return $index;
	}

	// Сброс списка словарей - делает его пустым
	static function reset()
	{
		self::$dictonaries = array();
		self::clean_caches();
	}

	// Основной метод - возвращает переведённую строку
	// Первый аргумент - идентификатор (или фраза)
	// в тексте можно использовать подстановки вида: %, %1, %2
	// если подстановка без числа ("%") то будет подставлен аргумент
	// с тем же порядковым номером каким порядком следует данный символ
	// если указано число - то будет взять аргумент порядковый номер которого
	// указан, поясним на примере
	// t("Hello, %! Now time is %", "Andy", "9:34") - вернёт "Hello, Andy! Now time is 9:34"
	// t("Hello, %2! Now time is %1", "9:34", "Andy") - вернёт аналогичную строку
	// Если в тексте требуется использовать сам символ "%" - укажите его дважды, пример
	// t("Completed % %%", 90) - вернёт "Completed 90 %"
	static function t($msg_id)
	{
		$args = func_get_args(); $args = array_slice($args, 1);
		$message = self::find($msg_id);
		if (!$message) { $message = $msg_id; }
		return self::interpolation($message, $args);
	}

	// Метод для множественных чисел
	// Первым параметром передаётся сообщение для количества - 1
	// Вторым параметром - для любого другого количества
	// Третий параметр - само количество
	// Возвращается перевод на текущий язык, возможно используя спец.правила (см plural_rule() метод)
	// Если перевод не обнаружен используется одно из переданных сообщений взависимости от количества
	static function t_plural($msg_id, $msg_many_id, $n)
	{
		// Если для языка указано правило для множественных чисел
		if (array_key_exists(self::language(), self::$plural_rules))
		{
			// Получаем новый id для сообщения
			$plural_msg_id = call_user_func(self::$plural_rules[self::language()], $msg_id, $msg_many_id, abs($n));
			// Ищем по словарю модифицированное сообщение
			$message = self::find($plural_msg_id);
			// Если перевод не найден
			if (!$message)
			{
				// Получаем стандартное сообщение
				$native_msg_id = self::plural_native($msg_id, $msg_many_id, $n);
				// Если оно совпадает с модифицированным - выбираем его
				// это нужно для того чтобы не осуществлять повторный поиск в
				// словаре если сообщение уже не было найдено выше
				if ($native_msg_id == $plural_msg_id) { $message = $native_msg_id; }
			}
			// Если найдено - интерполируем и возвращаем перевод
			if ($message) { return self::interpolation($message, $n); }
		}
		return self::t(self::plural_native($msg_id, $msg_many_id, $n), $n);
	}

	// Стандартный выбор сообщения для множественных чисел
	static private function plural_native($msg_id, $msg_many_id, $n) { return (abs($n) == 1) ? $msg_id : $msg_many_id; }

	static function interpolation($text, $args)
	{
		if (!is_array($args)) { $args = array($args); }
		if (strpos($text, "%") !== false)
		{
			return preg_replace_callback("/%% | %(\d+) | %/sux", function($matches) use ($args)
			{
				static $current_index = 1;
				if ($matches[0] == "%%") { return $matches[0]; }
				elseif ($matches[0] == "%") { $index = ($current_index++); }
				else { $index = $matches[1]; }
				$index--;
				if (array_key_exists($index, $args)) { return $args[$index]; } else { return $matches[0]; }
			}, $text);
		} else { return $text; }
	}

	// Ищет перевод строки по всем словарям в соответствии с указанным модулем (или по всем) - если не найдено - возвращает false
	static function find($msg_id, $module_name = null)
	{
		$lang = self::language();

		if
		(
			array_key_exists($lang, self::$translations_cache)
			&&
			array_key_exists($module_name, self::$translations_cache[$lang])
			&&
			array_key_exists($msg_id, self::$translations_cache[$lang][$module_name])
		)
		{ return self::$translations_cache[$lang][$module_name][$msg_id]; }

		// Переведённое сообщение
		$message = false;
		$skip_module = null;

		// Если словарей больше одного - используем кэш
		if (count(self::$dictonaries) > 1)
		{
			// Если передали имя конкретного модуля или указан не дефолтный
			// то поиск будем осуществлять среди словарей этого модуля
			if (func_num_args() > 1 || self::module() !== null)
			{
				$skip_module = $module_name = (func_num_args() > 1 ? $module_name : self::module());
				if (!array_key_exists($module_name, self::$module_cache))
				{
					self::$module_cache[$module_name] = array();
					foreach (self::$dictonaries as $index => $dict)
					{
						if ($dict["module"] == $module_name)
						{ self::$module_cache[$module_name][] = $index; }
					}
				}

				// Теперь ищем среди словарей для этого модуля
				foreach (self::$module_cache[$module_name] as $index)
				{
					$message = self::$dictonaries[$index]["object"]->get($msg_id, $lang, $module_name);
					if ($message) { break; }
				}
			}
		}

		// Если словарь всего один или не указан модуль для поиска
		// или среди словарей указанного модуля перевод не найден
		if (!$message)
		{
			foreach (self::$dictonaries as $index => $dict)
			{
				// Если производился поиск среди словарей какого-то модуля
				// пропускаем данные словари, т.к. в них ничего не нашлось
				if ($skip_module && $dict["module"] == $skip_module) { continue; }
				$message = $dict["object"]->get($msg_id, $lang);
				if ($message) { break; }
			}
		}

		// Если перевод не найден - сохраняем информацию о нём в глобальный массив
		if (!$message) { self::$unknown_translations[$lang][$msg_id] = true; }

		// Кэшируем результат поиска
		self::$translations_cache[$lang][$module_name][$msg_id] = $message;

		return $message;
	}

	// Возвращает собранный список не найденных переводов строк
	static function unknown_translations()
	{
		$unknown = self::$unknown_translations;
		foreach ($unknown as $lang => $translations) { $unknown[$lang] = array_keys($translations); }
		return $unknown;
	}

	static private function clean_caches()
	{
		self::$module_cache = array();
		self::$translations_cache = array();
	}

	// Используйте данные методы для модификации идентификаторов сообщений
	// Вероятно с расширением функциональности эти методы будут делать более сложную работу
	static function add_prefix($msg, $prefix) { return "(" . $prefix . ") " . $msg; }
	static function add_suffix($msg, $suffix) { return $msg . " (" . $suffix . ")"; }
}

/* Правило множественных чисел для русского языка
 * В некоторых случаях правило добавляет суффикс "few" к $msg_many_id
 * Пример использования:
 *
 * I18n::t("% message", "% messages", 3);
 *
 * Для русского языка вы должны перевести в словаре три выражения
 * "% message" => "% сообщение"
 * "% messages (few)" => "% сообщения"
 * "% messages" => "% сообщений"
 *
 * Выражение "% messages (few)" сформированно на основе $msg_many_id с помощью метода I18n::add_suffix()
 * Если вас это не устраивает, вы можете переопределить правило множественныз чисел для русского языка
 * используя рабочий пример ниже.
 */
I18n::plural_rule("ru", function($msg_id, $msg_many_id, $n)
{
	$variant = ($n % 10 == 1 && $n % 100 != 11 ? 0 : ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2));
	if ($variant == 0) { return $msg_id; }
	if ($variant == 1) { return I18n::add_suffix($msg_many_id, "few"); }
	if ($variant == 2) { return $msg_many_id; }
});