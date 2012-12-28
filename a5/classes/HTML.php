<?php
class HTML
{
	static function sanitize($html, $sanitize_tags = null) { return self::parse($html, array("self", "sanitize_callback"), $sanitize_tags); }

	static function sanitize_callback($data_type, $data, $sanitize_tags = null)
	{
		static $latest_tag_name = null;
		$sanitize_tags_default = "!doctype html body head base style meta title link script";
		if ($sanitize_tags === null) { $sanitize_tags = $sanitize_tags_default; }
		elseif (substr($sanitize_tags, 0, 1) == "+") { $sanitize_tags = substr($sanitize_tags, 1) . " " . $sanitize_tags_default; }
		$sanitize_tags = preg_split("/\s+/u", $sanitize_tags, -1, PREG_SPLIT_NO_EMPTY);
		$sanitize_tags_quoted = $sanitize_tags;
		array_walk($sanitize_tags_quoted, function(&$item) { $item = preg_quote($item, "/"); });

		// Если предыдущий тэг был "script" и указали что нужно избавляться от него - то все последующие данные и тэги
		// если они не являются тэгом "/script" уничтожаются
		if ($latest_tag_name == "script" && preg_match("/^(\/?)(" . implode($sanitize_tags_quoted, "|") . ")/sixu", "script"))
		{
			if ($data_type != "tag" || $data["name"] != "/script")
			{ return null; }
		}

		if ($data_type == "tag")
		{
			$latest_tag_name = $data["name"];
			// Убираем небезопасные тэги
			if (preg_match("/^(\/?)(" . implode($sanitize_tags_quoted, "|") . ")/sixu", $data["name"])) { return null; }
			// Обезопасиваем небезопасные аттрибуты и значения
			$data = self::sanitize_tag($data);
		}

		return $data;
	}

	// На вход передаётся массив - информация о тэге ("name" и "attr" ключи должны пристуствовать)
	// Возвращает такой же массив но с убранными и исправленными параметрами (убирает вероятную XSS уязвимость)
	static function sanitize_tag($data)
	{
		// Обезопасиваем небезопасные аттрибуты и значения
		foreach ($data["attr"] as $name => $value)
		{
			// Атрибуты on* вырезаем
			if (preg_match("/^on/siu", $name)) { unset($data["attr"][$name]); continue; }
			// Для атрибутов href, src и value превращаем содержание javascript: -> xjavascript:
			if (preg_match("/^(href|data|dynsrc|src|value)$/siu", $name))
			{ $data["attr"][$name] = preg_replace("/^\b(javascript:)/siu", "x$1", $value); continue; }
			// В атрибутах style превращаем expression -> xexpression
			if (preg_match("/^(style)$/sixu", $name))
			{ $data["attr"][$name] = preg_replace("/\b(expression\(.*?\))/sixu", "x$1", $value); continue; }
		}
		return $data;
	}

	// Парсер HTML/XML кода - первый параметр - входной $html - второй - callback-функция
	// callback-функция будет вызываться для каждого тэга и каждого куска обычного текста
	// первым параметром функция должна принимать тип данных для которого она вызвана
	// параметр может иметь два значения: "data" - в этом случае второй параметр является
	// обычным текстом, который можно каким-либо образом обработать и вернуть обратно
	// если же первый параметр "tag" - то второй будет массивом с двумя ключами "name" и
	// "attr" (массив атрибутов тэга) - нужно вернуть такой же массив (возможно изменённый)
	// чтобы изменить параметры тэга, также можно вернуть null чтобы пропустить данный тэг
	// либо можно вернуть скалярное значение отличное от "null" тогда тэг будет заменён
	// на возвращённое значение (не забывайте в этом случае возвращать htmlspecialchars значение)
	// если передан тип "data" то текст передаётся "как есть", т.е. скорее всего вам потребуется
	// использовать html_entity_decode() - возвращённое значение также подставлятеся "как есть"
	static function parse($html, $callback, $callback_params = null)
	{
		if (!@is_callable($callback)) { return throw_error("Invalid callback function supplied"); }
		$chunks = preg_split('{(< (![Dd][Oo][Cc][Tt][Yy][Pp][Ee]|/?[a-zA-Z][a-zA-Z0-9_!:-]*) (?> \s+ (?> [^>"\']+ | " [^"]* " | \' [^\']* \' )* )? >)}sxu', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
		$html = null;
		for ($i = 0, $chunks_len = count($chunks); $i < $chunks_len;)
		{
			$html .= call_user_func($callback, "data", $chunks[$i], $callback_params);
			if ($i + 3 < $chunks_len)
			{
				$tag_name = strtolower($chunks[$i + 2]);
				$tag_attr = array();
				$tag_attrs = substr($chunks[$i + 1], strlen($tag_name) + 1, -1);

				// !doctype тэг не является настоящим тэгом - поэтому мы не трогаем его аттрибуты
				if ($tag_name != "!doctype")
				{
					// Получаем массив атрибутов для тэга
					if (preg_match_all('/([^\s=]+) \s* ( = \s* (?> ("[^"]*" | \'[^\']*\' | \S*) ) )?/sxu', $tag_attrs, $regs))
					{
						$names = $regs[1]; $checks = $regs[2]; $values = $regs[3];
						for ($n = 0, $c = count($names); $n < $c; $n++)
						{
							$name = strtolower($names[$n]);
							if (@!strlen($checks[$n])) { $value = null; }
							else
							{
								$value = $values[$n];
								if (substr($value, 0, 1) == '"' || substr($value, 0, 1) == "'") { $value = substr($value, 1, -1); }
							}

							if (strpos($value, '&') !== false) { $value = decode_h($value); }
							$tag_attr[$name] = trim($value);
						}
					}
				}

				$new_tag_info = call_user_func($callback, "tag", array("name" => $tag_name, "attr" => $tag_attr), $callback_params);

				if ($new_tag_info === null) { $i += 3; continue; }
				elseif (is_scalar($new_tag_info)) { $html .= $new_tag_info; $i += 3; continue; }
				else
				{
					if (isset($new_tag_info["name"])) { $tag_name = strtolower($new_tag_info["name"]); }
					if (isset($new_tag_info["attr"]) && is_array($new_tag_info["attr"])) { $tag_attr = $new_tag_info["attr"]; }
				}

				if ($tag_name != "!doctype")
				{
					$tag_attrs = null;
					foreach($tag_attr as $name => $value)
					{
						$tag_attrs .= " " . h($name);
						if ($value !== null) { $tag_attrs .= '="' . h($value) . '"'; }
					}
				}

				$html .= "<" . $tag_name . $tag_attrs . ">";
				$i += 2;
			}
			$i++;
		}
		return $html;
	}

	static function to_text($html)
	{
		$html = self::parse($html, array("self", "to_text_callback"));
		$html = decode_h($html);
		$html = preg_replace("/<!-- .*? -->/sxu", "", $html);
		$html = preg_replace("/" . decode_h("&nbsp;") . "/su", " ", $html);
		$html = preg_replace("/[ \t]{2,}/su", " ", $html);
		$html = preg_replace("/\n(\s|{{%% TAB %%}})*\n/su", "\n\n", $html);
		$html = str_replace("{{%% TAB %%}}", "\t", $html);
		$html = preg_replace("/^[\t ]+/mu", "", $html);
		$html = preg_replace("/[\t ]+$/mu", "", $html);
		return trim($html);
	}

	static function to_text_callback($data_type, $data)
	{
		static $latest_tag_name = null;

		// Игнорируемые тэги и содержание в внутри них
		if (in_array($latest_tag_name, array("script", "style", "select", "textarea")))
		{ if ($data_type != "tag" || $data["name"] != "/" . $latest_tag_name) { return null; } }

		if ($data_type == "tag")
		{
			$current_tag_name = $data["name"];
			switch ($current_tag_name)
			{
				case "br":
				case "div":
				case "tr":
				case "h1": case "/h1":
				case "h2": case "/h2":
				case "h3": case "/h3":
				case "h4": case "/h4":
				case "h5": case "/h5":
				case "h6": case "/h6":
				case "p": case "/p":
				case "ol": case "/ol":
				case "ul": case "/ul":
				case "dl": case "/dl":
				case "dt": case "/dt":
				case "caption": case "/caption":
				case "p": case "/p":
					$data = "\n"; break;

				case "/title":
				case "hr":
					$data = "\n-----------------------------------------------\n"; break;

				case "li":
					$data = "\n* "; break;

				case "dd":
					$data = "\n{{%% TAB %%}} "; break;

				case "td":
				case "th":
					if (in_array($latest_tag_name, array("/th", "/td"))) { $data = "{{%% TAB %%}}"; } else { $data = null; }
					break;

				default: $data = null; break;
			}

			$latest_tag_name = $current_tag_name;
		}
		else { $data = str_replace("\n", " ", preg_replace("/(\r\n|\n)/su", "\n", $data)); }

		return $data;
	}
}