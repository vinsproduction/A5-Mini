<?php
// Помощник для форсирования скачивания файлов с поддержкой Ranges и прочих фич
// Мультидиапозон Ranges пока не поддерживается, только один, этого вполне достаточно в штатных ситуациях.
class Download
{
	static function inline($filepath, $filename = null, $filetype = null) { self::attachment($filepath, $filename = null, $filetype = null, true); }

	static function attachment($filepath, $filename = null, $filetype = null, $is_inline = false)
	{
		$filesize = @filesize($filepath);
		$fileetag = @file_etag($filepath);
		$filemtime = @filemtime($filepath);
		$filesize = $filesize ? $filesize : 0;
		$chunksize = 1024 * 1024;

		while (@ob_end_clean());

		// Перед отдачей контента нужно закрыть все соединения с базами данных
		// если таковые имеются - иначе пока файл будет скачиваться эти соединения будут
		// висеть как занятые, в этом ничего хорошего нет совсем
		if (class_exists("DBConnection", false) && method_exists("DBConnection", "close_all")) { DBConnection::close_all(); }

		HTTP::attachment($filename, $filetype, $is_inline);
		header("Accept-Ranges: bytes");
		header("ETag: \"" . $fileetag . "\"");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT", $filemtime);

		$is_modified = null;
		if (($is_modified === null || $is_modified == false) && isset($_SERVER["HTTP_IF_NONE_MATCH"])) { $is_modified = ($_SERVER["HTTP_IF_NONE_MATCH"] != '"' . $fileetag . '"'); }
		if (($is_modified === null || $is_modified == false) && isset($_SERVER["HTTP_IF_NONE_MATCH"]) && (false !== $since_time = strtotime($_SERVER["HTTP_IF_MODIFIED_SINCE"]))) { $is_modified = ($filemtime > $since_time); }
		if ($is_modified === false) { HTTP::response_code("304"); exit; }

		if (isset($_SERVER["HTTP_RANGE"]))
		{
			if (!isset($_SERVER["HTTP_IF_RANGE"]) || $_SERVER["HTTP_IF_RANGE"] == "\"" . $fileetag . "\"")
			{
				if (preg_match("/^\s* bytes \s* = \s* (.+) \s*$/sixu", $_SERVER["HTTP_RANGE"], $regs))
				{
					$ranges = preg_split("/\s*,\s*/u", $regs[1]);
					$chunks = array();

					foreach ($ranges as $range)
					{
						if (preg_match("/\s* (\d*) \s* - \s* (\d*) \s*/sixu", $range, $regs))
						{
							$start = $regs[1];
							$finish = $regs[2];

							if (is_empty($start) && is_empty($finish)) { continue; }

							if (is_empty($start) && !is_empty($finish))
							{
								$start = $filesize - $finish;
								$finish = $filesize - 1;
							}

							if (!is_empty($start) && is_empty($finish)) { $finish = $filesize - 1; }

							if ($start > $filesize) { $start = $filesize - 1; }
							if ($finish > $filesize) { $finish = $filesize - 1; }

							if ($finish < $start) { continue; }
							$chunks[] = array("start" => $start, "finish" => $finish);
						}
					}

					if (count($chunks) > 0)
					{
						$range = $chunks[0];
						$start = $range["start"];
						$finish = $range["finish"];
						HTTP::response_code(206);
						header("Content-Range: bytes " . $start . "-" . $finish . "/" . $filesize);
						header("Content-Length: " . ($finish - $start + 1));
						$fp = @fopen($filepath, "r");
						if ($fp) { self::output_chunk($fp, $start, $finish); fclose($fp); }
						exit;
					}
				}
			}
		}

		// Если не смогли отработать заголовок Ranges по какой-либо причине - отдаём всё
		header("Content-Length: " . $filesize);

		$bytes_sent = 0;
		$fp = @fopen($filepath, "r");
		if ($fp) { self::output_chunk($fp, 0, $filesize - 1); fclose($fp); }

		exit;
	}

	// Выдаёт часть файла с начального по конечный байт включительно
	private static function output_chunk($fp, $start, $finish)
	{
		$chunksize = 1024 * 1024;
		$bytes_sent = 0;
		$bytes_length = $finish - $start + 1;
		@fseek($fp, $start);
		while(!feof($fp) && (!connection_aborted()) && ($bytes_sent < $bytes_length))
		{
			$bytes_to_read = $chunksize;
			if ($bytes_sent + $bytes_to_read > $bytes_length) { $bytes_to_read = $bytes_length - $bytes_sent; }
			$buffer = @fread($fp, $bytes_to_read);
			echo $buffer; flush();
			$bytes_sent += strlen($buffer);
		}
	}
}