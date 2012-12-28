<?php
class Debug
{
	private $messages = array();
	private $log_file = null;

	function append_html_data($content)
	{
		$data = null;
		foreach ($this->messages as $group => $items)
		{
			foreach ($items as $item)
			{
				$item["message"] = h($item["message"]);
				$item["message"] = str_replace(" ", "&nbsp;", $item["message"]);
				$item["message"] = str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", $item["message"]);
				$item["message"] = nl2br($item["message"]);
				if ($item["color"]) { $item["message"] = "<span style=\"color: " . $item["color"] . ";\">" . $item["message"] . "</span>"; }
				$data .= "top.debug.echo('" . j($item["message"]) . "', '" . j($item["tip"]) . "',  '" . j($group) . "');";
			}
		}

		$js = '';
		$js .= 'function Debug()' . "\n";
		$js .= '{' . "\n";
		$js .= '	this.div = top.document.body.insertBefore(top.document.createElement(\'DIV\'), top.document.body.childNodes[0]);' . "\n";
		$js .= '	this.div.style.display = \'none\';' . "\n";
		$js .= '	this.div.style.background = \'black\';' . "\n";
		$js .= '	this.div.style.zIndex = 100000000;' . "\n";
		$js .= '	this.div.style.position = \'relative\';' . "\n";
		$js .= '	this.div.style.width = \'100%\';' . "\n";
		$js .= '	this.div.style.height = \'400px\';' . "\n";
		$js .= '	this.div.style.overflow = \'auto\';' . "\n";
		$js .= '	this.div.style.border = \'2px #ffffff solid\';' . "\n";
		$js .= '	this.div.style.color = \'#00ff00\';' . "\n";
		$js .= '	this.div.style.font = \'normal 12px "Courier New"\';' . "\n";
		$js .= '	this.div.style.padding = \'5px\';' . "\n";
		$js .= '	var main_div = this.div;' . "\n";
		$js .= '	main_div.id = \'console_main_div\'' . "\n";

		$js .= '	this.toggle_console = function(e)' . "\n";
		$js .= '	{' . "\n";
		$js .= '		var evt = e ? e : window.event;' . "\n";
		$js .= '		if ((evt.ctrlKey || e.metaKey) && (evt.keyCode == 192 || evt.keyCode == 96))' . "\n";
		$js .= '		{ main_div.style.display = (main_div.style.display == \'none\') ? \'block\' : \'none\'; }' . "\n";
		$js .= '	}' . "\n";

		$js .= '	this.main = top.HTMLElement ? top : top.document.body;' . "\n";

		$js .= '	if (this.main.attachEvent) { this.main.attachEvent(\'onkeydown\', this.toggle_console); }' . "\n";
		$js .= '	else { this.main.addEventListener(\'keydown\', this.toggle_console, true); }' . "\n";

		$js .= '	this.echo = function(msg, title, group)' . "\n";
		$js .= '	{' . "\n";
		$js .= '		if (!msg) return;' . "\n";
		$js .= '		var div = top.document.createElement(\'DIV\');' . "\n";
		$js .= '		div.style.borderLeft = \'3px double #ffffff\';' . "\n";
		$js .= '		div.style.paddingLeft = \'5px\';' . "\n";
		$js .= '		div.innerHTML = msg;' . "\n";
		$js .= '		if (group)' . "\n";
		$js .= '		{' . "\n";
		$js .= '			var group_div = top.document.getElementById(\'console_group_\' + group);' . "\n";
		$js .= '			if (!group_div)' . "\n";
		$js .= '			{' . "\n";
		$js .= '				group_div = top.document.createElement(\'DIV\');' . "\n";
		$js .= '				group_div.id = \'console_group_\' + group;' . "\n";
		$js .= '				group_div.innerHTML = \'<span style="font-weight: bold; font-size: 17px;">\' + group + \'</span>\';' . "\n";
		$js .= '				main_div.appendChild(group_div);' . "\n";
		$js .= '			}' . "\n";
		$js .= '			group_div.appendChild(div);' . "\n";
		$js .= '		} else { main_div.appendChild(div); }' . "\n";
		$js .= '		if (title) { div.title = title; }' . "\n";
		$js .= '	}' . "\n";
		$js .= '}' . "\n";
		$js .= 'top.debug = new Debug();';


	    $html = "<script type=\"text/javascript\"><!--\n" . $js . "\n" . $data . "//--></script>\n";
		
		return $content . $html;
	}

	function log_file($filepath = null)
	{
		if ($filepath === null) { return $this->log_file; }
		else { return ($this->log_file = $filepath); }
	}

	// Одновременная запись в лог и добавление сообщения для вывода в хтмл
	function log_message($var, $group = null, $color = null, $tip = null)
	{
		$this->message($var, $group, $color, $tip);
		$this->log($var, $group, $color, $tip);
	}

	// Функция записи сообщения в лог-файл
	function log($var, $group = null, $color = null, $tip = null)
	{
		if ($this->log_file !== null)
		{
			if (is_scalar($var)) { $message = "$var\n"; }
			else { $message = $this->get_dump($var); }

			$fp = @fopen($this->log_file, "a+");
			if ($fp) { flock($fp, LOCK_EX); fwrite($fp, sprintf("[%s %d] ", date('Y-m-d H:i:s T'), getmypid()) . $message); fclose($fp); }
			else { echo "Cannot open " . $this->log_file . ": " . @$php_errormsg; }
		}
	}

	// Функция добавления сообщения для последующего вывода в хтмл
	function message($var, $group = null, $color = null, $tip = null)
	{
		if (is_scalar($var)) { $message = "$var\n"; }
		else { $message = $this->get_dump($var); }

		$this->messages[$group][] = array
		(
			"message" => $message,
			"color" => $color,
			"tip" => $tip,
		);
	}

	static function get_dump($obj, $level = 0)
	{
		if ($level < 7)
		{
			if (is_array($obj)) { $text = "array"; }
			elseif (is_object($obj)) { $text = "object"; }
			elseif (is_bool($obj)) { $text = $obj ? "true" : "false"; }
			elseif ($obj === null) { $text = "null"; }
			else { $text = $obj; }
			if (is_array($obj) || is_object($obj))
			{
				$text .= "\n" . str_repeat("\t", $level) . "(\n";
				foreach ($obj as $key => $val)
				{
					if ($key == "GLOBALS") { continue; }
					$text .= str_repeat("\t", $level + 1) . "[" . $key . "] => " . self::get_dump($val, $level + 1);
				}
				$text .= str_repeat("\t", $level) . ")\n";
			}
			else { $text .= "\n"; }
		}
		else { $text = "*RECURSION*"; }
		return $text;
	}
}