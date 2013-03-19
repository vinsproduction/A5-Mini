<?php
class Client
{
    // IP клиента, обычный конечный адрес видимый веб-сервером
	static function ip() { return $_SERVER["REMOTE_ADDR"]; }

	// Настоящий ip клиента, учитывает возможные заголовки
	// HTTP_X_FORWARDED_FOR и HTTP_X_REAL_IP
	// если они есть, иначе возвращает self::ip()
	static function real_ip()
	{
        if (!@is_empty($_SERVER["HTTP_X_REAL_IP"])) { $ip = $_SERVER["HTTP_X_REAL_IP"]; }
		elseif (!@is_empty($_SERVER["HTTP_X_FORWARDED_FOR"]))
		{
		    // если значение вида: client1, proxy1, proxy2, то берём только client 1
		    $ip = explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"]);
			$ip = trim($ip[0]);
		}
		else { return self::ip(); }

		// Проверяем ip по маске xxx.xxx.xxx.xxx < 256, так как HTTP заголовки могут иметь искажённую информацию
		if (preg_match("/^([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})\.([0-9]{1,3})$/sux", $ip, $matches))
		{ if (!($matches[1] < 256 && $matches[2] < 256 && $matches[3] < 256 && $matches[4] < 256)) return self::ip(); } else { return self::ip(); }

		return $ip;
	}

	// Возвращает имя браузера клиента, его версию или то и то и другое.
	// Если что-то не удалось определить - вернёт null
	// Примеры вызова:
	// Client::browser("name") -> IE
	// Client::browser("version") -> 6.0
	// Client::browser() -> array("name" => "IE", "version" => "6.0", "major_version" => "6", "minor_version" => 0)
	// (http://www.useragentstring.com useragent-ы браузеров для проверки/дополнения условий)
	static function browser($type = null)
	{
		$agent = @$_SERVER["HTTP_USER_AGENT"];
		$brw = array(null, null, null, null);

		if (strpos($agent, "Avant Browser") !== false) { $brw[0] = 'Avant Browser'; }
        elseif (strpos($agent, "Acoo Browser") !== false) { $brw[0] = 'Acoo Browser'; }
        elseif (preg_match("/Iron\/([0-9a-z\.]*)/i", $agent, $matches)) { $brw[0] = 'SRWare Iron'; $brw[1] = $matches[1]; }
		elseif (preg_match("/ABrowse\s([0-9a-z\.]*)/i", $agent, $matches)) { $brw[0] = 'ABrowse'; $brw[1] = $matches[1]; }
		elseif (preg_match("/AmigaVoyager\/([0-9a-z\.]*)/i", $agent, $matches)) { $brw[0] = 'AmigaVoyager'; $brw[1] = $matches[1]; }
		elseif (preg_match("/America Online Browser\s([0-9a-z\.]*)/i", $agent, $matches)) { $brw[0] = 'America Online Browser'; $brw[1] = $matches[1]; }
		elseif (preg_match("/AOL\s([0-9a-z\.]*)/i", $agent, $matches)) { $brw[0] = 'AOL'; $brw[1] = $matches[1]; }
		elseif (preg_match("/Arora\/([0-9a-z\.]*)/i", $agent, $matches)) { $brw[0] = 'Arora'; $brw[1] = $matches[1]; }
		elseif (preg_match("/BonEcho\/([0-9a-z\.]*)/i", $agent, $matches)) { $brw[0] = 'BonEcho'; $brw[1] = $matches[1]; }
		elseif (strpos($agent, "BlackBerry") !== false)
		{
			if (preg_match("/BlackBerry[0-9]{4}\/([0-9a-z\.]*)/i", $agent, $matches)) { $brw[0] = 'BlackBerry'; $brw[1] = $matches[1]; }
			elseif (preg_match("/Version\/([0-9a-z\.]*)/i", $agent, $matches)) { $brw[0] = 'BlackBerry'; $brw[1] = $matches[1]; }
        }
		elseif (preg_match("/Crazy Browser\s([0-9a-z\.]*)/i", $agent, $matches)) { $brw[0] = 'Crazy Browser'; $brw[1] = $matches[1]; }
        elseif (preg_match("/Chrome\/([0-9a-z\.]*)/i", $agent, $matches)) { $brw[0] = 'Google Chrome'; $brw[1] = $matches[1]; }
        elseif (preg_match("/(Maxthon|NetCaptor)[\/\s]([0-9a-z\.]*)/i", $agent, $matches)) { $brw[0] = $matches[1]; $brw[1] = @$matches[2]; }
        elseif (strpos($agent, "MyIE2") !== false) { $brw[0] = 'MyIE2'; }
		elseif (preg_match("/(Netscape|Navigator)[\/\s]([0-9a-z\.]*)/i", $agent, $matches)) { $brw[0] = 'Netscape'; $brw[1] = @$matches[2]; }
  	    elseif (preg_match("/(NetFront|K-Meleon|Galeon|Epiphany|Konqueror|Safari|Opera Mini)[\/\s]([0-9a-z\.]*)/", $agent, $matches)) { $brw[0] = $matches[1]; $brw[1] = @$matches[2]; }
	    elseif (preg_match("/Opera[\/\s]([0-9a-z\.]*)/i", $agent, $matches)) { $brw[0] = 'Opera'; $brw[1] = $matches[1]; }
		elseif (preg_match("/Orca\/([0-9a-z\.]*)/", $agent,$matches)) { $brw[0] = "Orca Browser"; $brw[1] = $matches[1]; }
		elseif (preg_match("/(SeaMonkey|GranParadiso|Minefield|Shiretoko)\/([0-9a-z\.]*)/", $agent, $matches)) { $brw[0] = "Mozilla " . $matches[1]; $brw[1] = @$matches[2]; }
	    elseif (preg_match("/Firefox\/([0-9a-z\.]*)/i", $agent, $matches)) { $brw[0] = 'Firefox'; $brw[1] = $matches[1]; }
	    elseif (preg_match("/Safari[\/\s]([0-9a-z\.]*)/i", $agent, $matches)) { $brw[0] = 'Safari'; $brw[1] = $matches[1]; }
        elseif (preg_match("/Lynx\/([0-9a-z\.]*)/i", $agent, $matches)) { $brw[0] = 'Lynx'; $brw[1] = $matches[1]; }
		elseif (preg_match("/Mozilla\/([0-9a-z\.]*)/i", $agent, $matches))
		{
			if (!@is_empty($matches[1])) { $vers = $matches[1]; } else { $vers = null; }
			if (preg_match("/Gecko\/([0-9a-z\.]*)/i", $agent, $matches)) { $brw[0] = 'Mozilla'; $brw[1] = $vers; }
			elseif (preg_match("/MSIE\s([0-9a-z\.]*)/i", $agent, $matches)) { $brw[0] = 'IE'; $brw[1] = $matches[1]; }
		}

		if (is_empty($brw[0])) { $brw[0] = null; }
		if (is_empty($brw[1])) { $brw[1] = null; }

		if ($brw[1] !== null)
		{
			if (strpos($brw[1], ".") !== false) { list($brw[2], $brw[3]) = explode(".", $brw[1]); }
			else { list($brw[2], $brw[3]) = array($brw[1], 0); }
		}

		$brw = array
		(
			"name" => $brw[0],
			"version" => $brw[1],
			"major_version" => $brw[2],
			"minor_version" => $brw[3]
		);

		if ($type !== null)
		{
			if (array_key_exists($type, $brw)) { return $brw[$type]; }
			else { throw_error("Unknown key name '" . $type . "'! Use on of: " . implode(", ", array_keys($brw)), true); }
		}
		else { return $brw; }
	}

	// Возвращает true - клиент с мобильного или false - клиент со стационара
	static function mobile()
	{
		$user_agent = strtolower($_SERVER["HTTP_USER_AGENT"]);
		$accept = strtolower($_SERVER["HTTP_ACCEPT"]);

		// Мобильный браузер обнаружен по HTTP-заголовку accept
		if (strpos($accept, "text/vnd.wap.wml") !== false || strpos($accept, "application/vnd.wap.xhtml+xml") !== false) { return true; }

		// Мобильный браузер обнаружен по установкам сервера
		if
		(
			isset($_SERVER["HTTP_X_WAP_PROFILE"])
			|| isset($_SERVER["HTTP_PROFILE"])
			|| isset($_SERVER["HTTP_X_OPERAMINI_FEATURES"])
			|| isset($_SERVER["HTTP_UA_PIXELS"])
		) { return true; }

	    // Мобильный браузер обнаружен по User Agent
		if (preg_match("/(" .
			"mini 9.5|vx1000|lge |m800|e860|u940|ux840|compal|" .
			"wireless| mobi|ahong|lg380|lgku|lgu900|lg210|lg47|lg920|lg840|" .
			"lg370|sam-r|mg50|s55|g83|t66|vx400|mk99|d615|d763|el370|sl900|" .
			"mp500|samu3|samu4|vx10|xda_|samu5|samu6|samu7|samu9|a615|b832|" .
			"m881|s920|n210|s700|c-810|_h797|mob-x|sk16d|848b|mowser|s580|" .
			"r800|471x|v120|rim8|c500foma:|160x|x160|480x|x640|t503|w839|" .
			"i250|sprint|w398samr810|m5252|c7100|mt126|x225|s5330|s820|" .
			"htil-g1|fly v71|s302|-x113|novarra|k610i|-three|8325rc|8352rc|" .
			"sanyo|vx54|c888|nx250|n120|mtk |c5588|s710|t880|c5005|i;458x|" .
			"p404i|s210|c5100|teleca|s940|c500|s590|foma|samsu|vx8|vx9|a1000|" .
			"_mms|myx|a700|gu1100|bc831|e300|ems100|me701|me702m-three|sd588|" .
			"s800|8325rc|ac831|mw200|brew |d88|htc\/|htc_touch|355x|m50|km100|" .
			"d736|p-9521|telco|sl74|ktouch|m4u\/|me702|8325rc|kddi|phone|lg |" .
			"sonyericsson|samsung|240x|x320vx10|nokia|sony cmd|motorola|" .
			"up.browser|up.link|mmp|symbian|smartphone|midp|wap|vodafone|o2|" .
			"pocket|kindle|mobile|psp|treo" .
		")/", $user_agent)) { return true; }

		// Мобильный браузер обнаружен User Agent
		if (preg_match("/^" .
			"acs\-|alav|alca|amoi|audi|aste|avan|benq|bird|blac|blaz|brew|cell|" .
			"cldc|cmd\-|dang|doco|eric|hipt|inno|ipaq|java|jigs|kddi|keji|leno|" .
			"lg\-c|lg\-d|lg\-g|lge\-|maui|maxo|midp|mits|mmef|mobi|mot\-|moto|" .
			"mwbp|nec\-|newt|noki|opwv|palm|pana|pant|pdxg|phil|play|pluc|port|" .
			"prox|qtek|qwap|sage|sams|sany|sch\-|sec\-|send|seri|sgh\-|shar|sie\-|" .
			"siem|smal|smar|sony|sph\-|symb|t\-mo|teli|tim\-|tosh|treo|tsm\-|upg1|" .
			"upsi|vk\-v|voda|wap\-|wapa|wapi|wapp|wapr|webc|winw|xda\-" .
		"/", $user_agent)) { return true; }

		return false; // Мобильный браузер не обнаружен
	}

	// Возвращает true - клиент имеет поддержку HTML5 или false - соответственно не имеет поддержки HTML5
	static function support_html5()
	{
	    $brw = self::browser();
	    $name = $brw["name"];
	    $major = $brw["major_version"];
	    $minor = $brw["minor_version"];
		if ($name == "IE" && $major >= 9) { return true; }
		elseif ($name == "Firefox" && ($major >= 4 || ($major >= 3 && $minor >= 5))) { return true; }
	    elseif ($name == "Safari" && $major >= 4) { return true; }
		elseif ($name == "Google Chrome" && $major >= 3) { return true; }
		elseif ($name == "Opera" && ($major >= 11 || ($major >= 10 && $minor >= 5))) { return true; }
		return false;
	}
}