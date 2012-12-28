<?php
class CSV
{
	// Функция пытается определить разделитель полей в CSV файле
	// Если возникла критическая ошибка то устаналвивает ошибку в глобальной переменной $php_errormsg
	// Если определить не удалось из-за того что файл пустой например возвращает ";"
	// при любой ошибке возвращает false
	// На вход - указатель файла
	static function detect_delimeter($fh)
	{
		$old_pos = @ftell($fh);
		if ($old_pos === false) { $GLOBALS["php_errormsg"] = $php_errormsg; return false; }

		$CSVDelimeter = null;

		if (@fseek($fh, 0) === false) { $GLOBALS["php_errormsg"] = $php_errormsg; return false; }
		$header1 = @fgetcsv($fh, 16384, ",");
		if ($header1 === false) { $GLOBALS["php_errormsg"] = $php_errormsg; return false; }

		if (@fseek($fh, 0) === false) { $GLOBALS["php_errormsg"] = $php_errormsg; return false; }
		$header2 = @fgetcsv($fh, 16384, ";");
		if ($header2 === false) { $GLOBALS["php_errormsg"] = $php_errormsg; return false; }

		if (count($header1) > count($header2)) { $CSVDelimeter = ","; } else { $CSVDelimeter = ";"; }
		if (@fseek($fh, $old_pos) === false) { $GLOBALS["php_errormsg"] = $php_errormsg; return false; }

		return $CSVDelimeter;
	}

	// Функция аналогичная fputcsv - только она возвращает экранированную строку
	static function escape($s)
	{
		if (preg_match("/[\";\\\n\r\t ]/u", $s))
		{ return '"' . str_replace('"', '""', $s) . '"'; }
		else { return $s; }
	}

	// Получает на вход массив массивов, берёт ключи первого массива как заголовки столбцов
	// Если все ключи первого массива - числовые - тогда просто отдаёт все данные как есть
	// И отдаёт данные в формате .csv
	static function sendfile($data, $filename = null, $delim = ";")
	{
		HTTP::attachment(($filename === null ? "data_" . date("d.m.Y_H_i_s") . ".csv" : $filename), "text/csv");
		foreach ($data as $row)
		{
			foreach ($row as $cell) { echo self::escape($cell) . $delim; }
			echo "\r\n";
		}
		exit;
	}
}