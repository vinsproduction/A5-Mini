<?php
class Cookie
{
	// Записывает массив (или произвольный объект) в куки.
	static function set_data($name, $value = null, $expire = 0, $path = "", $domain = "", $secure = false)
	{
		$value = @base64_encode(gzcompress(serialize($value), 9));
		if (strlen($value) > 4096) { throw_error("Maximum cookie value size is exceeded 4Kb", true); }
		return setcookie($name, $value, $expire, $path, $domain, $secure);
	}

	// Возвращает массив (или произвольный объект) из кук.
	static function get_data($name)
	{
	    if (is_empty($_COOKIE[$name])) { return false; }
		$data = @base64_decode($_COOKIE[$name]);
		if ($data === false) { return false; }
		$data = @gzuncompress($data);
		if ($data === false) { return false; }
		$data = @unserialize($data);
		if ($data === false) { return false; }
		return $data;
	}
}