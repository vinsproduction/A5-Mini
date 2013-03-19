<?php
/* Мощный инструмент для работы с формами, проверки ошибок и вывода сообщений о них.
 * Основная идея в том чтобы обеспечить удобный функционал любой формы на странице с минимальным внесением
 * каких-либо модификаций в сам исходный html-код формы
 * В самом простом случае запуск функционала класса заключается в создании его инстанции
 *
 * $form = new FormProcessor();
 *
 * При создании инстанции класса устанавлиается обработчик буфера вывода страницы, таким образом
 * перед тем как отдать содержание страницы клиенту в браузер, класс парсит содержание,
 * выделяет формы и сами элементы формы и устанавливает их значения взависимости от его наличия в $_GET или $_POST
 * (это зависит какого типа сама форма - её аттрибут "method")
 *
 * Класс корректно обрабатывает любые <input> элементы, а также <textarea> и <select> (включая <select multiple)
 *
 * В результате всего процесса после отправки данных формы при нахождении в ней ошибок пользователю не придётся
 * заново заполнять все данные, которые он уже ввёл без ошибок.
 *
 * Автоматическую обработку содержания можно отключить (см конструктор класса)
 *
 * Основные методы для работы с ошибками
 *
 * check($var_name, $check_flags, $is_only_validate = false)
 * Метод проверяет на указанные ошибки переменную с именем указанным в первом параметре.
 * Список флагов для различных проверок вы найдёте в самом начале класса.
 * Третий параметр если true говорит о том что требуется просто проверка на ошибки,
 * и не нужно сохранять статус о том по данному полю была ошибка.
 *
 * Имя переменной - это по-сути ключ в массиве, который в данный момент используется в
 * качестве исходных данных, массив устанавливается автоматически на основе <form method="post|get">
 * но может быть переопределён вызовом метода set_check_array().
 *
 * Метод check() корректно отрабатывает такие указания для $var_name как например "payments[20][sum]".
 * Предположим что проверяемый массив - $_POST, тогда вызов check("payments[20][sum]", "entered,valid_sum")
 * будет означать что данные находящийеся в $_POST["payments"][20]["sum"] должны быть не пустыми и должны быть
 * правильным дробным числом. Если это не так - класс запомнит статус ошибки для имени данного поля.
 *
 * is_error($var_name)
 * метод вернёт true если по указанному полю есть ошибка.
 * Пример: is_error("payments[20][sum]")
 *
 * set_error($var_name, $message, $message_is_flag = false)
 * Метод принудительно устанавливает статус что в данном поле есть ошибка, вторым параметром
 * указывается само сообщение об ошибке, если вы хотите использовать стандартное сообщение об ошибке
 * для какого-то конкретного флага, то вместо сообщения передайте сам флаг и третий параметр в true
 * Например: set_error("name", "entered", "true)
 * Данный пример установит сообщение об ошибке "Не введено" (зависит от языка) для поля "name"
 *
 * set_error_lang()
 * Устанавливает язык сообщений оби ошибках, en - дефолт
 *
 * validate()
 * Полностью аналогичен методу check() - по сути - алиас, всегда передаёт параметр $is_only_validate = true
 *
 * check_value($value, $var_name, $check_flags, $is_only_validate = false)
 * Аналогичен check() но значение берёт из первого параметра
 *
 * get_error_text($name)
 * Возвращает сообщение об ошибке для поля - false - если ошибок нет
 *
 * get_error_html($name)
 * Возвращает HTML-фрагмент с сообщением об ошибке по указанному полю
 *
 * Подробности читайте в комментариях к классу
 */
class FormProcessor
{
	private $check_arr = null;
	private $saved_content = array();
	private $latest_var_name = null;
	private $form_errors = array();

	// По-умолчанию FormProcessor не сохраняет введённые данные в полях с типом "password"
	// Вы можете изменить данное поведение с помощью метода persistent_passwords(bool $is_persistent)
	private $persistent_passwords = false;

	private $error_prefix = '<div style="color: #ff0000;">';
	private $error_suffix = '</div>';
	private $error_lang = "ru";

	private $error_names = array
	(
		"ru" => array
		(
			'entered' => "Не заполнено",
			'selected' => "Не выбрано",
			'min_len' => "Минимальное количество символов: %s",
			'max_len' => "Максимальное количество символов: %s",
			'valid_email' => "Неверный e-mail",
			'valid_date' => "Неверная дата",
			'valid_time' => "Неверное время",
			'valid_integer' => "Неверное число",
			'valid_age' => "Неверный возраст",
			'valid_float' => "Неверное число",
			'valid_price' => "Неверная цена",
			'valid_sum' => "Неверная сумма",
			'valid_cost' => "Неверная стоимость",
		),
		"en" => array
		(
			'entered' => "Not entered",
			'selected' => "Not selected",
			'min_len' => "Minimum length is %s",
			'max_len' => "Maximum length is %s",
			'valid_email' => "Invalid e-mail",
			'valid_date' => "Invalid date",
			'valid_time' => "Invalid time",
			'valid_integer' => "Invalid number",
			'valid_age' => "Invalid age",
			'valid_float' => "Invalid number",
			'valid_price' => "Invalid price",
			'valid_sum' => "Invalid sum",
			'valid_cost' => "Invalid cost",
		),
	);

	private $default_error_names = array
	(
		"ru" => "Проверка не пройдена: %s",
		"en" => "Check failed: %s",
	);

	// Текущий источник данных для приведения элементов форм в исходное состояние
	private $source_data = null;

	private $current_form_indexes = array();
	private $form_name = null;

	private $validators = array();

	function __construct($is_auto_process = true)
	{
		$this->set_check_array($_POST);

		// Если запросили авто-процессинг страницы - устанавливаем обработчик
		if ($is_auto_process) { ob_start(array(&$this, 'process_forms')); }

		$this->validators = array
		(
			"min_len" => array("self", "min_len"),
			"max_len" => array("self", "max_len"),
			"valid_email" => array("self", "valid_email"),
			"valid_date" => array("self", "valid_date"),
			"valid_time" => array("self", "valid_time"),
			"valid_integer" => array("self", "valid_integer"),
			"valid_age" => array("self", "valid_age"),
			"valid_float" => array("self", "valid_float"),
			"valid_price" => array("self", "valid_float"),
			"valid_sum" => array("self", "valid_float"),
			"valid_cost" => array("self", "valid_float"),
		);
	}

	// Метод устанавливает имя формы, которую нужно обработать, остальные обработаны не будут
	// Если передать null, false, пустую строку и прочее - то будут обрабатываться все формы
	// Имя формы указывается в её тэге с помощью параметра name или id, приоритетнее - name
	function set_form_name($form_name)
	{
		if (!$form_name) { $this->form_name = null; }
		else { $this->form_name = $form_name; }
	}

	// Метод устанавливает массив для использования методом check
	function set_check_array(&$a) { $this->check_arr =& $a; }

	// Метод устанавливает массив, который используется как данные для заполнения формы
	// Метод используется в основном самой системой, но может и использоваться отдельно
	// Например если используется парсинг части хтмл
	function set_source_data(&$a) { $this->source_data =& $a; }

	// Метод устанавливает язык сообщений об ошибках
	function set_error_lang($lang) { if (array_key_exists($lang, $this->error_names)) { $this->error_lang = $lang; } }

	// Метод изменяет поведение работа с полями типа "password"
	function persistent_passwords($is_persistent) { $this->persistent_passwords = !!$is_persistent; }

	// Основной метод - запускается в конце работы скрипта - обрабатывает html-содержание страницы
	// устанавливает значения полей формы на странице в соответствии с содержанием массива POST или GET
	function process_forms($content)
	{
		$forms = preg_split('{(< (/?form) (?> \s+ (?> [^>"\']+ | " [^"]* " | \' [^\']* \' )* )? >)}six', $this->_protect_content($content), -1, PREG_SPLIT_DELIM_CAPTURE);
		$content = null;

		// Теперь обрабатываем каждую форму
		for ($m = 0, $forms_len = count($forms); $m < $forms_len;)
		{
			$content .= $this->_restore_content($forms[$m]);

			if ($m + 3 < $forms_len)
			{
				$form_tag_name = strtolower($forms[$m + 2]);
				$form_attr = self::split_attributes(substr($forms[$m + 1], strlen($form_tag_name) + 1, -1));
				if (isset($form_attr["method"])) { $form_attr["method"] = strtolower($form_attr["method"]); }

				if ($form_tag_name == "form")
				{
					$form_content = null;
					$form_array = null;

					while ($m < $forms_len)
					{
						$m += 3;
						if ($m < $forms_len) { $form_content .= $this->_restore_content($forms[$m]); } else { break; }
						if ($m + 2 >= $forms_len || strtolower(substr($forms[$m + 2], 0, 5)) == "/form") { break; }
					}

					if (!array_key_exists("action", $form_attr)) { $form_attr["action"] = $_SERVER["REQUEST_URI"]; }
					if (!array_key_exists("method", $form_attr) || $form_attr["method"] == "get") { $form_attr["method"] = "get"; }

					$is_process_form = false;
					if ($this->form_name === null) { $is_process_form = true; }
					elseif (array_key_exists("name", $form_attr)) { $is_process_form = in_array($form_attr["name"], (array) $this->form_name); }
					elseif (array_key_exists("id", $form_attr)) { $is_process_form = in_array($form_attr["id"], (array) $this->form_name); }

					$content .= "<" . $form_tag_name . self::join_attributes($form_attr) . ">";

					if ($is_process_form)
					{
						if ($form_attr["method"] == "get") { $this->set_source_data($_GET); } else { $this->set_source_data($_POST); }
						$content .= $this->process($form_content);
					}
					else { $content .= $form_content; }

					$content .= "</" . $form_tag_name . ">";
				}
				else { $content .= $forms[$m + 1]; }

				$m += 2;
			}
			$m++;
		}

		return $content;
	}

	function process($form_content)
	{
		if ($this->source_data === null) { $this->set_source_data($_POST); }

		$this->latest_var_name = null;
		$this->current_form_indexes = array();
		$auto_idx = array();

		$form_content = $this->_protect_content($form_content);
		$content = null;
		$parts = preg_split('{(< (/?err|input|/?select|/?textarea) (?> \s+ (?> [^>"\']+ | " [^"]* " | \' [^\']* \' )* )? >)}six', $form_content, -1, PREG_SPLIT_DELIM_CAPTURE);
		for ($i = 0, $len = count($parts); $i < $len;)
		{
			$content .= $parts[$i];

			if ($i + 3 < $len)
			{
				$tag_name = strtolower($parts[$i + 2]);
				$tag_attr = self::split_attributes(substr($parts[$i + 1], strlen($tag_name) + 1, -1));

				switch ($tag_name)
				{
					case 'input':
						// Если не указан аттрибут - это текст
						if (!array_key_exists("type", $tag_attr)) { $tag_attr["type"] = "text"; }

						switch ($input_type = strtolower($tag_attr["type"]))
						{
							case 'text':
							case 'hidden':
							case 'password':
								if ($input_type != "password" || $this->persistent_passwords)
								{
									if (array_key_exists("name", $tag_attr))
									{
										$name = $tag_attr["name"];
										$need_array = self::is_need_array($name);
										$value = $this->parse_var($name, $this->source_data);

										if (!array_key_exists("value", $tag_attr))
										{
											if ($need_array)
											{
												if (!array_key_exists($name, $auto_idx)) { $auto_idx[$name] = 0; }

												if ($value !== false && array_key_exists($auto_idx[$name], $value))
												{ $tag_attr["value"] = strval($value[$auto_idx[$name]]); }

												$auto_idx[$name]++;
											}
											elseif ($value !== false) { $tag_attr["value"] = strval($value); }
										}
									}
								}
								break;
							case 'checkbox':
							case 'radio':
								if (array_key_exists("name", $tag_attr))
								{
									if (!array_key_exists("value", $tag_attr)) { $tag_attr["value"] = "on"; }

									$name = $tag_attr["name"];
									$need_array = self::is_need_array($name);
									$value = $this->parse_var($name, $this->source_data);

									if (!array_key_exists("checked", $tag_attr))
									{
										if ($need_array)
										{
											if ($value !== false && in_array(strval($tag_attr["value"]), $value))
											{ $tag_attr["checked"] = null; } else { unset($tag_attr["checked"]); }
										}
										else
										{
											if
											(
												$value !== false
												&&
												(
													!strcmp($value, $tag_attr["value"])
													||
													($input_type == "checkbox" && $value)
												)
											)
											{ $tag_attr["checked"] = null; }
											else
											{ unset($tag_attr["checked"]); }
										}
									}
								}
								break;
						}

						$content .= "<" . $tag_name . self::join_attributes($tag_attr) . " />";
						break;

					// Для select нужно найти закрывающий </select>
					// и пропарсить его опции
					case 'select':
						$select_content = null;

						while ($i < $len)
						{
							$i += 3; $select_content .= $parts[$i];
							if (strtolower(substr($parts[$i + 2], 0, 7)) == "/select") { break; }
						}

						if (array_key_exists("name", $tag_attr))
						{
							$name = $tag_attr["name"];
							$need_array = self::is_need_array($name);
							$value = $this->parse_var($name, $this->source_data);

							// Парсим опции
							$opt_parts = preg_split('{(< (option) (?> \s+ (?> [^>"\']+ | " [^"]* " | \' [^\']* \' )* )? >)}six', $select_content, -1, PREG_SPLIT_DELIM_CAPTURE);
							$has_selected = 0;
							$select_content = null;

							for ($n = 0, $opt_len = count($opt_parts); $n < $opt_len;)
							{
								$select_content .= $opt_parts[$n];

								if ($n + 3 < $opt_len)
								{
									$opt_tag_name = strtolower($opt_parts[$n + 2]);
									$opt_tag_attr = self::split_attributes(substr($opt_parts[$n + 1], strlen($opt_tag_name) + 1, -1));

									if (!array_key_exists("selected", $opt_tag_attr))
									{
										if ($need_array)
										{
											if ($value !== false && in_array(strval($opt_tag_attr["value"]), $value) && (!$has_selected || ($has_selected && array_key_exists("multiple", $tag_attr))))
											{ $opt_tag_attr["selected"] = null; }
											else
											{ unset($opt_tag_attr["selected"]); }
										}
										else
										{
											if ($value !== false && !strcmp($value, $opt_tag_attr["value"]) && (!$has_selected || ($has_selected && array_key_exists("multiple", $tag_attr))))
											{ $opt_tag_attr["selected"] = null; }
											else
											{ unset($opt_tag_attr["selected"]); }
										}
									}

									if (array_key_exists("selected", $opt_tag_attr)) { $has_selected++; }

									$select_content .= "<" . $opt_tag_name . self::join_attributes($opt_tag_attr) . ">";
								}

								$n += 3;
							}
						}

						$content .= "<" . $tag_name . self::join_attributes($tag_attr) . ">";
						$content .= $select_content;
						$content .= "</" . $tag_name . ">";

						break;

					// Для textarea нужно найти закрывающий <textarea>
					// Всё что между ними - контент textarea
					case 'textarea':
						$textarea_content = null;

						while ($i < $len)
						{
							$i += 3; $textarea_content .= $parts[$i];
							if (strtolower(substr($parts[$i + 2], 0, 9)) == "/textarea") { break; }
						}

						$textarea_content = $this->_restore_data($textarea_content);
						$textarea_content = decode_h($textarea_content);
						$content .= "<" . $tag_name . self::join_attributes($tag_attr) . ">";

						if (array_key_exists("name", $tag_attr))
						{
							$name = $tag_attr["name"];
							$need_array = self::is_need_array($name);
							$value = $this->parse_var($name, $this->source_data);

							if ($need_array)
							{
								if (!array_key_exists($name, $auto_idx)) { $auto_idx[$name] = 0; }

								if ($value !== false && array_key_exists($auto_idx[$name], $value))
								{ $textarea_content = $value[$auto_idx[$name]]; }

								$auto_idx[$name]++;
							}
							elseif ($value !== false) { $textarea_content = $value; }
						}

						$content .= h($textarea_content);
						$content .= "</" . $tag_name . ">";

						break;

					case 'err':
						$name = array_key_exists("for", $tag_attr) ? $tag_attr["for"] : $this->latest_var_name;
						if ($name !== null)
						{
							if (array_key_exists("plain", $tag_attr) && strtolower($tag_attr["plain"]) == "yes")
							{ $content .= h($this->get_error_text($name)); }
							else
							{ $content .= $this->get_error_html($name); }
						}
						break;
				}

				if (array_key_exists("name", $tag_attr)) { $this->latest_var_name = $tag_attr["name"]; }
				$i += 2;
			}
			$i++;
		}

		return $this->_restore_content($content);
	}

	static function split_attributes($body)
	{
		$attr = array();
		$preg = '/([-a-zA-Z0-9_:]+) \s* ( = \s* (?> ("[^"]*" | \'[^\']*\' | \S*) ) )?/sx';
		if (preg_match_all($preg, $body, $regs))
		{
			$names = $regs[1];
			$checks = $regs[2];
			$values = $regs[3];
			for ($i = 0, $c = count($names); $i < $c; $i++)
			{
				$name = strtolower($names[$i]);
				if (@!strlen($checks[$i])) { $value = null; }
				else
				{
					$value = $values[$i];
					if (substr($value, 0, 1) == '"' || substr($value, 0, 1) == "'") { $value = substr($value, 1, -1); }
				}

				if (strpos($value, '&') !== false) { $value = decode_h($value); }
				$attr[$name] = $value;
			}
		}
		return $attr;
	}

	static function join_attributes($attr)
	{
		$str = null;
		foreach($attr as $k => $v)
		{
			$str .= " " . h($k);
			if ($v !== null) { $str .= '="' . h($v) . '"'; } else { $str .= '="' . h($k) . '"'; }
		}
		return $str;
	}

	// Функция может проверять либо конкретную переменную, т.е.
	// $varname должно содержать что-то типа "string1" или "string1[5][ab]"
	// Либо одномерный массив, т.е. "string1[]", проверка массивов типа
	// "string1[][]" не поддерживается. Т.к. не известно что проверять
	// Проверка массивов проводиться только на заполненность хотя бы
	// одним не пустым элементом, т.е. только с флагом "selected" или "entered"
	// Ошибка будет общая для всей группы полей, чтобы проверить и выдать ошибку на конкретное поле
	// Передайте в качестве $varname конкретный элемент массива, например "string[2]"
	function check($varname, $flags = "", $only_validate = false)
	{ return self::check_value($this->parse_var($varname, $this->check_arr), $varname, $flags, $only_validate);	}

	// Функция может проверять либо конкретную переменную, т.е.
	// $varname должно содержать что-то типа "string1" или "string1[5][ab]"
	// Либо одномерный массив, т.е. "string1[]", проверка массивов типа
	// "string1[][]" не поддерживается. Т.к. не известно что проверять
	// Проверка массивов проводиться только на заполненность хотя бы
	// одним не пустым элементом, т.е. только с флагом "selected" или "entered"
	// Ошибка будет общая для всей группы полей, чтобы проверить и выдать ошибку на конкретное поле
	// Передайте в качестве $varname конкретный элемент массива, например "string[2]"
	function check_value($value, $varname, $flags = "", $only_validate = false)
	{
		$is_have_errors = false;

		// Если запросили полную проверку и установку ошибок и по полю уже есть ошибка,
		// то сразу возвращаем статус что проверка не пройдена ничего не проверяя
		if (!$only_validate && $this->is_error($varname)) { return false; }

		$flags = preg_split("/\s*,\s*/", $flags);
		foreach ($flags as $src_flag)
		{
			list($flag, $flag_params) = $this->deploy_flag($src_flag);
			if (!$only_validate) { $error_name = $this->get_error_message($src_flag); }
			switch ($flag)
			{
				case 'entered':
				case 'selected':
					if ($value === false || (!is_array($value) && is_empty($value)) || (is_array($value) && !count($value)))
					{
						$is_have_errors = true;
						if (!$only_validate) { if (!isset($this->form_errors[$varname])) $this->form_errors[$varname] = $error_name; }
					}
					elseif (is_array($value))
					{
						if (self::is_need_array($varname)) { $name = substr($varname, 0, strlen($varname) - 2); } else { $name = $varname; }
						foreach ($value as $k => $v)
						{
							if (is_empty($v))
							{
								$is_have_errors = true;
								if (!$only_validate)
								{
									if (!isset($this->form_errors[$varname])) { $this->form_errors[$varname] = $error_name; }
									if (!isset($this->form_errors[$name . "[" . $k . "]"])) { $this->form_errors[$name . "[" . $k . "]"] = $error_name; }
								}
							}
						}
					}
					break;

				default:
					if (array_key_exists($flag, $this->validators))
					{
						if ($value !== false)
						{
							$is_callable_validator = is_callable($this->validators[$flag]);
							if (!$is_callable_validator) { $error_name = "Unknown validator: " . $flag; }

							if (!is_array($value))
							{
								if (!is_empty($value) && (!$is_callable_validator || !call_user_func_array($this->validators[$flag], array_merge(array($value), $flag_params))))
								{
									$is_have_errors = true;
									if (!$only_validate) { if (!isset($this->form_errors[$varname])) $this->form_errors[$varname] = $error_name; }
								}
							}
							else
							{
								if (self::is_need_array($varname)) { $name = substr($varname, 0, strlen($varname) - 2); } else { $name = $varname; }
								foreach ($value as $k => $v)
								{
									if (!is_empty($v) && (!$is_callable_validator || !call_user_func_array($this->validators[$flag], array_merge(array($v), $flag_params))))
									{
										$is_have_errors = true;
										if (!$only_validate)
										{
											if (!isset($this->form_errors[$varname])) { $this->form_errors[$varname] = $error_name; }
											if (!isset($this->form_errors[$name . "[" . $k . "]"])) { $this->form_errors[$name . "[" . $k . "]"] = $error_name; }
										}
									}
								}
							}
						}
					}
					else
					{
						$is_have_errors = true;
						if (!$only_validate) { if (!isset($this->form_errors[$varname])) $this->form_errors[$varname] = $error_name; }
					}
					break;
			}
		}

		if ($only_validate) { return !$is_have_errors; }
		else { return $this->is_error($varname) ? false : true; }
	}

	// только возвращает есть ли ошибка в данном поле или нет
	function validate($varname, $flags = "") { return $this->check($varname, $flags, true); }

	// Возвращает true если в форме были ошибки (ошибки устанвлвиаются методами check или set_error)
	function is_form_error() { return count($this->form_errors) ? true : false; }

	// Устанавливает сообщения об ошибках для различных типов проверок, например set_error_text("entered", "(пусто)");
	// Сообщение устанавливается для текущего языка (если не переданно иное)
	function set_error_text($flag, $text, $lang = null) { $this->error_names[$lang === null ? $this->error_lang : $lang][$flag] = $text; }

	// Устанавливает пользовательский валидатор значений - $flag - имя валидатор, например "valid_temperature"
	// $callback - функция проверки в формате callback-параметров PHP
	// функция должна принимать первым параметром проверяемое значение, вторым (возможный набор аргументов).
	// Если значение верное - возвращать true иначе возвращать false что будет означать что проверка не пройдена.
	function set_validator($flag, $callback)
	{
		list($flag, $flag_params) = $this->deploy_flag($flag);
		$this->validators[$flag] = $callback;
		// Сразу устанавливаем дефолтные тексты ошибок для случаев если забудем установить свои
		foreach ($this->default_error_names as $lang => $error_text)
		{
			if (!isset($this->error_names[$lang][$flag]))
			{ $this->set_error_text($flag, sprintf($error_text, $flag), $lang); }
		}
	}

	// Устанавливает сообщение об ошибке для указанного элемента, если передан третий параметр в true -
	// считается что ошибка устонавливается по имени флага - т.е. сообщение будет стандартным
	function set_error($varname, $message = null, $by_flag = false) { $this->form_errors[$varname] = $by_flag ? $this->get_error_message($message) : ($message ? $message : $varname); }

	// Убира сообщение об ошибке для указанного поля
	function unset_error($varname) { unset($this->form_errors[$varname]); }

	// Возвращает true если указанное поле содержит ошибку или false если не содержит
	function is_error($varname) { return $this->get_error_text($varname) ? true : false; }

	// Возвращает строку сообщение об ошибке для указанного элемента - false если ошибок не было
	function get_error_text($varname) { return isset($this->form_errors[$varname]) ? $this->form_errors[$varname] : false; }

	// Возвращает HTML-фрагмент сообщения об ошибке или пустую строку (со всеми префиксами и суффиксами).
	// Если элемент не указан - возвращает сообщение об ошибке текущего обрабатываемого элемента (latest_var_name)
	function get_error_html($varname = null, $prefix = null, $suffix = null)
	{
		if ($varname === null) { $varname = $this->latest_var_name; }
		if ($this->get_error_text($varname) !== false) { return ($prefix === null ? $this->error_prefix : $prefix) . h($this->get_error_text($varname)) . ($suffix === null ? $this->error_suffix : $suffix); }
		else { return ""; }
	}

	// Возвращает ассоциативный массив где ключ - имя поля, значение - сообщение об ошибке - если ошибок нет - пустой массив
	function get_all_errors() { return $this->form_errors; }

	// Устанавливает дефолтный-префикс для сообщений об ошибках используемый в get_error_html()
	function set_error_prefix($prefix) { $this->error_prefix = $prefix; }

	// Устанавливает дефолтный-суффикс для сообщений об ошибках используемый в get_error_html()
	function set_error_suffix($suffix) { $this->error_suffix = $suffix; }

	static private function is_need_array($varname) { return substr($varname, -2) == '[]'; }

	// Возвращает значение указанной переменной.
	// Переменная может заканчиваться "[]" что будет означать что возвращаемое значение
	// должно являтся массивом - если массива с таким именем нет или есть но не массив
	// то вернёт false.
	// В любом случае вернётся false при отсутствии переменной
	private function parse_var($varname, $arr)
	{
		$need_array = self::is_need_array($varname);
		if ($need_array) { $varname = substr($varname, 0, strlen($varname) - 2); }

		// Если переменная не ввиде массива
		if (0 >= $pos = strpos($varname, '['))
		{
			if (array_key_exists($varname, $arr)) { return ($need_array ? (is_array($arr[$varname]) ? $arr[$varname] : false) : $arr[$varname]); }
			else { return false; }
		}
		else
		{
			// Если есть такая
			if (array_key_exists(substr($varname, 0, $pos), $arr))
			{
				// Если имя переменной не заканчивается на ']'
				// То это не массив, считаем обычной переменной (странной переменной)
				if (substr($varname, -1) != ']')
				{
					if (array_key_exists($varname, $arr)) { return ($need_array ? (is_array($arr[$varname]) ? $arr[$varname] : false) : $arr[$varname]); }
					else { return false; }
				}
				// Если в конце стоит ']' значит это точно массив
				// Нормальный массив у нас может быть разбит только сочетанием символов ']['
				else
				{
					$parts = array();

					// Например часть от test[a][b] у нас будет 'a][b'
					$parts = explode('][', substr($varname, $pos + 1, -1));

					// Первая часть от test[a][b] у нас будет 'test'
					// Добавим её в начало
					array_unshift($parts, substr($varname, 0, $pos));

					// В результате получаем массив типа $parts = array('test', 'a', 'b');
					$keypath = "";
					$currarr =& $arr;
					foreach ($parts as $part)
					{
						// Если имя ключа не обозначено
						// То определяем для него числовой индекс
					    if (!strlen($part))
					    {
					        if (!isset($this->current_form_indexes[$keypath])) { $this->current_form_indexes[$keypath] = 0; }
					        $part = $this->current_form_indexes[$keypath]++;
					    }

					    // Если следующий элемент не установлен, или $currarr уже не является массивом
					    // Значит такой элемент не был передан из формы (не существует), возвращаем false
					    if (!isset($currarr[$part]) || !is_array($currarr)) { return false; }

					    // Иначе ставим указатель на следующий по вложенности массив
					    $currarr =& $currarr[$part];
					    $keypath .= "[" . $part . "]";
					}
					return ($need_array ? (is_array($currarr) ? $currarr : false) : $currarr);
				}
			}
			else { return false; }
		}
	}

	// Функция защиты контента ХТМЛ-текста, сохраняет неизменным
	// содержимое заключённое в тэгах script и textarea
	private function _protect_content($content)
	{
		$saves = preg_split('{(< (/?script|/?textarea) (?> \s+ (?> [^>"\']+ | " [^"]* " | \' [^\']* \' )* )? >)}six', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
		$content = null;
		for ($m = 0, $len = count($saves); $m < $len;)
		{
			$content .= $saves[$m];
			if ($m + 3 < $len)
			{
				$tag_name = strtolower($saves[$m + 2]);
				$tag_attr = self::split_attributes(substr($saves[$m + 1], strlen($tag_name) + 1, -1));

				if ($tag_name == "script" || $tag_name == "textarea")
				{
					$tag_content = null;

					while ($m < $len)
					{
						$m += 3;
						if ($m < $len) { $tag_content .= $saves[$m]; } else { break; }
						if ($m + 2 >= $len || strtolower(substr($saves[$m + 2], 0, strlen($tag_name) + 1)) == "/" . $tag_name) { break; }
					}

					$content .= "<" . $tag_name . self::join_attributes($tag_attr) . ">";
					$content .= $this->_save_data($tag_content);
					$content .= "</" . $tag_name . ">";
				}
				else { $content .= $saves[$m + 1]; }

				$m += 2;
			}
			$m++;
		}
		return $content;
	}

	// Функция восстановления защищённого контента
	private function _restore_content($content) { return preg_replace_callback("/%%[a-fA-F0-9]{32}%%/s", array(&$this, "_restore_content_callback"), $content); }
	private function _restore_content_callback($matches) { return $this->_restore_data($matches[0]); }

	private function _save_data($data)
	{
		$id = "%%" . md5(microtime() . uniqid()) . "%%";
		$this->saved_content[$id] = $data;
		return $id;
	}

	private function _restore_data($hash)
	{
		if (array_key_exists($hash, $this->saved_content))
		{
			$content = $this->saved_content[$hash];
			unset($this->saved_content[$hash]);
			return $content;
		}
		else { return $hash; }
	}

	// Разбивает переданный флаг на имя и переданные параметры (если есть)
	// Пример для "max_len[8]" вернёт array("max_len", array("8"))
	private function deploy_flag($src_flag)
	{
		$flag = strtolower(trim($src_flag)); $flag_params = array();
		if (preg_match("/^(.+)\[([^]]+)\]$/", $flag, $regs)) { $flag = $regs[1]; $flag_params = preg_split("/\s*:\s*/", $regs[2]); }
		return array($flag, $flag_params);
	}

	// Возвращает чистый текст сообщения об ошибке для текущего или указанного языка
	// если не удалось найти текст для ошибки - возвращает стандартный
	private function get_error_message($src_flag, $lang = null)
	{
		list($flag, $flag_params) = $this->deploy_flag($src_flag);
		if ($lang === null) { $lang = $this->error_lang; }
		$error_name_index = isset($this->error_names[$lang][$src_flag]) ? $src_flag : $flag;
		if (isset($this->error_names[$lang][$error_name_index]))
		{
			$error_name = @call_user_func_array("sprintf", array_merge(array($this->error_names[$lang][$error_name_index]), $flag_params));
			if ($error_name === false) { $error_name = $this->error_names[$lang][$error_name_index] . (@$php_errormsg ? ": $php_errormsg" : null); }
		}
		else { $error_name = "Unknown error: " . $error_name_index; }
		return $error_name;
	}

	static private function min_len($val, $len = 0) { return mb_strlen(trim($val)) < $len ? false : true; }
	static private function max_len($val, $len = 255) { return mb_strlen(trim($val)) > $len ? false : true; }
	static private function valid_date($indate) { return Valid::date($indate); }
	static private function valid_time($indate) { return Valid::date($indate); }
	static private function valid_float($val) { return Valid::float($val); }
	static private function valid_age($val) { return Valid::age($val); }
	static private function valid_integer($val) { return Valid::integer($val); }
	static private function valid_email($val) { return Valid::email($val); }
}