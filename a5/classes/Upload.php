<?php
class Upload
{
	/*
	Функция для работы с загрузкой файлов
	На вход принимает строковый индекс названия файла из массива $_FILES
	или содержание $_FILES["userfile"]

	Возвращается всегда массив.
	При возникновении ошибки массив будет содержать два индекса:
	errcode - код ошибки
	errmsg - словесное описание ошибки
	Все возможные типы ошибок перечислены в методе Upload::error()

	В случае успешного аплоада и при невозникновении ошибок возвращается массив со следующими ключами
	uploaded - true или false - флаг говорящий о том что файл был загружен или нет,
	           даже если возникнет пользовательская ошибка (например несовпадение типа), то вернётся true
	           если файл всё же был загружен успешно.
	type - например "image/gif"
	size - размер в байтах
	name - имя файла
	ext - расширение (с точкой)
	path - путь к временному файлу
	data - содержание файла
	class - класс файла, например image, flash или file
	subclass - например jpg, gif, swf
	width - ширина (для кратинок или флэш)
	height - высота (для картинок или флэш)

	Вторым параметром в функцию могут быть переданы доп.условия в виде массива, вот все возможные:
	array
	(
		"no_data" => true, // Не будет считываться содержание файла
		"class" => "image,flash" // Вернётся ошибка типа class если файл не будет указнного класса
		"subclass" => "jpg,gif" // Вернётся ошибка типа subclass если под-класс файла не будет одним из указанных
		"maxsize" => 5000 // Вернётся ошибка типа maxsize если размер файлы превысет 5000 байт
		"minsize" => 5000 // Вернётся ошибка типа minsize если размер файла менее 5000 байт
		"maxwidth" => 50 // Вернётся ошибка типа maxwidth если ширина более 50
		"maxheight" => 50 // Вернётся ошибка типа maxheight если высота более 50
		"minwidth" => 50 // Аналогично
		"minheight" => 50 // Аналогично
	);
	*/
	static function fetch($file, $params = array())
	{
		$data = array
		(
			"errcode" => null,
			"errmsg" => null,
			"errparams" => array(),
			"uploaded" => false,
		);

		foreach (array_keys($params) as $param_name)
		{
			if (!in_array($param_name, array("no_data", "class", "subclass", "maxsize", "minsize", "maxwidth", "maxheight", "minwidth", "minheight")))
			{ throw_error("Unknown parameter: $param_name", true); }
		}

		if (!is_array($file))
		{
			$uploaded = Upload::normalize($_FILES);
			if (false === $pos = strpos($file, "[")) { $file = @$uploaded[$file]; }
			else
			{
				$file = rtrim($file, "]");
				$file = substr($file, 0, $pos) . "]" . substr($file, $pos);
				$parts = explode("][", $file);
				$file =& $uploaded;
				foreach ($parts as $part)
				{
					if (array_key_exists($part, $file)) { $file =& $file[$part]; }
					else { $file = null; break; }
				}
			}
		}

		if (!is_array($file)) { self::error($data, "not_uploaded"); }
		else
		{
			if (!isset($file["error"])) { self::error($data, "not_uploaded"); }
			elseif ($file["error"] == UPLOAD_ERR_NO_FILE) { self::error($data, "not_uploaded"); }
			elseif ($file["error"])
			{
				// Файл был загружен, но с ошибкой
				$data["uploaded"] = true;
				switch ($file["error"])
				{
					case 1: self::error($data, "post_exceeded", array("size" => human_size(ini_get("upload_max_filesize")))); break;
					case 2:	self::error($data, "maxsize_exceeded", array("size" => human_size(@$_REQUEST["MAX_FILE_SIZE"]))); break;
					case 3: self::error($data, "partial"); break;
					case 6: self::error($data, "no_tmp_dir"); break;
					case 7: self::error($data, "cant_write"); break;
					case 8: self::error($data, "extension_stopped"); break;
					default: self::error($data, "unknown"); break;
				}
			}
			elseif (!is_uploaded_file($file["tmp_name"])) { self::error($data, "not_uploaded"); }
			else
			{
				$data["uploaded"] = true;
				// Ошибок не было - файл загружен
				$data["type"] = !is_empty(@$file["type"]) ? $file["type"] : "application/octet-stream";
				$data["size"] = @$file["size"];
				$data["name"] = @basename($file["name"]);
				$data["path"] = @$file["tmp_name"];
				$data["ext"] = get_file_extension($data["name"]);
				$data["data"] = null;
				if (@!$params["no_data"])
				{
					$data["data"] = @file_get_contents($data["path"]);
					if ($data["data"] === false) { $data["errcode"] = "cant_read"; }
				}
				$data["class"] = "file";
				$data["subclass"] = ltrim($data["ext"], ".");
			}

			if (!$data["errcode"])
			{
				$info = @getimagesize($data["path"]);
				if ($info !== false)
				{
					$data["width"] = $info[0];
					$data["height"] = $info[1];
					$data["class"] = Image::get_class($info[2]);
					$data["subclass"] = Image::get_subclass($info[2]);
				}

				if (isset($params["maxsize"]) && is_numeric($params["maxsize"]) && $data["size"] > $params["maxsize"])
				{ self::error($data, "maxsize", array("size" => human_size($params["maxsize"]))); }
				elseif (isset($params["minsize"]) && is_numeric($params["minsize"]) && $data["size"] < $params["minsize"])
				{ self::error($data, "minsize", array("size" => human_size($params["minsize"]))); }

				if (!$data["errcode"])
				{
					if (isset($params["class"]))
					{
						$classes = preg_split("/\s*,\s*/u", $params["class"]);
						if (!in_array($data["class"], $classes)) { self::error($data, "class", array("class" => implode(", ", $classes))); }
					}
				}

				if (!$data["errcode"])
				{
					if (isset($params["subclass"]))
					{
						$subclasses = preg_split("/\s*,\s*/u", $params["subclass"]);
						if (!in_array($data["subclass"], $subclasses)) { self::error($data, "subclass", array("subclass" => implode(", ", $subclasses))); }
					}
				}

				if (!$data["errcode"])
				{
					if (isset($params["maxwidth"]) && is_numeric($params["maxwidth"]))
					{
						if (isset($data["width"]) && $data["width"] > $params["maxwidth"])
						{ self::error($data, "maxwidth", array("width" => $params["maxwidth"])); }
					}
				}

				if (!$data["errcode"])
				{
					if (isset($params["maxheight"]) && is_numeric($params["maxheight"]))
					{
						if (isset($data["height"]) && $data["height"] > $params["maxheight"])
						{ self::error($data, "maxheight", array("height" => $params["maxheight"])); }
					}
				}

				if (!$data["errcode"])
				{
					if (isset($params["minwidth"]) && is_numeric($params["minwidth"]))
					{
						if (isset($data["width"]) && $data["width"] < $params["minwidth"])
						{ self::error($data, "minwidth", array("width" => $params["minwidth"])); }
					}
				}

				if (!$data["errcode"])
				{
					if (isset($params["minheight"]) && is_numeric($params["minheight"]))
					{
						if (isset($data["height"]) && $data["height"] < $params["minheight"])
						{ self::error($data, "minheight", array("height" => $params["minheight"])); }
					}
				}
			}
		}

		return $data;
	}

	static private function error(&$bin, $code, $code_params = array())
	{
		$codes = array
		(
			"unknown" => "Unknown error",
			"not_uploaded" => "The file not uploaded",
			"post_exceeded" => "The uploaded file exceeds the maximum size of {size}",
			"maxsize_exceeded" => "The uploaded file exceeds the maximum size of {size}",
			"partial" => "The uploaded file was only partially uploaded",
			"no_tmp_dir" => "Missing a temporary folder for file saving",
			"cant_write" => "Failed to write file to disk",
			"cant_read" => "Failed to read file from disk",
			"extension_stopped" => "A PHP extension stopped the file upload",
			"maxsize" => "The file size exceeds {size}",
			"minsize" => "The file size less then {size}",
			"class" => "The file must be {class}",
			"subclass" => "The file must be {subclass}",
			"maxwidth" => "The width exceeds {width}",
			"maxheight" => "The height exceeds {height}",
			"minwidth" => "The width less then {width}",
			"minheight" => "The height less then {height}",
		);

		if (!isset($codes[$code])) { throw_error("Unknown error code: " . $code, true); }

		$bin["errcode"] = $code;
		$search = array(); $replace = array();
		foreach ($code_params as $key => $value) { $search[] = "{" . $key . "}"; $replace[] = $value; }
		$bin["errmsg"] = str_replace($search, $replace, $codes[$code]);
		if (count($search) && count($replace)) { $bin["errparams"] = array_combine($search, $replace); } else { $bin["errparams"] = array(); }
	}

	// Возвращает нормализованный массив на основе $_FILES для более удобной работы с ним
	// Это особенно удобно если вы производите загрузку нескольких файлов в виде массива
	static function normalize($files = null)
	{
		if ($files === null) { $files = $_FILES; }
		foreach ($files as $top_key => $upload_info)
		{
			$files[$top_key] = array();
			foreach ($upload_info as $parameter => $value)
			{ self::_regroup($files[$top_key], $parameter, $value); }
		}
		return $files;
	}

	private static function _regroup(&$array, $parameter, $value)
	{
		if (is_array($value))
		{
			foreach ($value as $k => $v)
			{
				if (is_array($v)) { self::_regroup($array[$k], $parameter, $v); }
	            else { $array[$k][$parameter] = $v; }
	        }
	    }
	    else { $array[$parameter] = $value; }
	}
}