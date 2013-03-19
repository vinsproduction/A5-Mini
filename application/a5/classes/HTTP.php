<?php
class HTTP
{
	// Просто выдаёт заголовки для браузера о запрете кэширования страницы
	static function no_cache()
	{
		header("Pragma: no-cache");
		header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");
		header("Expires: 0");
	}

	static function response_code($code)
	{
		$code = intval($code);
		if (!$code) { $code = 200; }
		header("Status: " . $code, true, $code);
	}

	static function inline($filename = null, $filetype = null) { self::attachment($filename = null, $filetype = null, true); }

	// Функция отсылает http заголовок
	// для старта скачивания указанного имени файла и его типа (опционально)
	// Не забудьте отправить данные файла и если возможно - размер :)
	static function attachment($filename = null, $filetype = null, $is_inline = false)
	{
		if ($filetype === null) { $filetype = "application/octet-stream"; }
		header("Pragma: private");
		header("Content-Type: " . $filetype);
		if ($filename !== null && @strpos($_SERVER["HTTP_USER_AGENT"], "MSIE") !== false) { $filename = rawurlencode($filename); }
		header("Content-Disposition: " . ($is_inline ? "inline" : "attachment") . ($filename !== null ? "; filename=\"" . $filename . "\"" : null));
	}
}