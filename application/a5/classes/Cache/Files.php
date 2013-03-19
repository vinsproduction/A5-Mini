<?php
class Cache_Files
{
	private $save_path = null;
	
	function __construct($save_path = null)
	{
		if ($save_path !== null) { $this->save_path = normalize_path($save_path); }
		else { throw_error(__CLASS__ . ": Provide path to cache storage in the first parameter of constructor", true); }

		// При создании класса чистим папку кэша с вероятностью 1%
		if (get_probability(1))
		{
			if (is_dir($this->save_path))
			{
				$dir = @opendir($this->save_path);
				if ($dir)
				{
					while ($file_name = @readdir($dir))
					{
						if ($file_name == "." || $file_name == "..") { continue; }
						$fp = @fopen($this->save_path . "/" . $file_name, "r");
						if ($fp)
						{
							flock($fp, LOCK_SH);
							// Время кэширования данного файла				
							$time = @intval(trim(fgets($fp, 1024)));
							if ($time > 0 && time() > $time) { fclose($fp); @unlink($this->save_path . "/" . $file_name); continue; }
						}
						fclose($fp);
					}
				}
			}
		}
		
		return true;
	}
	
	function store($cache_id, $data, $time = 0, $tags = array())
	{
		$cache_file_path = $this->save_path . "/" . $cache_id;

		if (!is_dir(dirname($cache_file_path)))
		{ @mkdirs(dirname($cache_file_path)) or throw_error("Cannot create " . dirname($cache_file_path) . ": " . $GLOBALS["php_errormsg"], true); }
		
		$fp = @fopen($cache_file_path, "a+");
		if ($fp)
		{
			flock($fp, LOCK_EX); ftruncate($fp, 0); fseek($fp, 0);

			$data = array("data" => $data, "tags" => array());

			$tag_time = (string) microtime(true);
			foreach ($tags as $tag_id)
			{
				$data["tags"][$tag_id] = $tag_time;
				$cache_file_path = $this->save_path . "/" . $tag_id;
				
				if (!is_dir(dirname($cache_file_path)))
				{ @mkdirs(dirname($cache_file_path)) or throw_error("Cannot create " . dirname($cache_file_path) . ": " . $GLOBALS["php_errormsg"], true); }

				// Если такого тэга ещё нет - создаём					
				if (!file_exists($cache_file_path))
				{
					$tfp = @fopen($cache_file_path, "a+");
					if ($tfp)
					{
						flock($tfp, LOCK_EX); ftruncate($tfp, 0); fseek($tfp, 0);
						fwrite($tfp, $tag_time);
						fclose($tfp);
					}
				}
			}

			fwrite($fp, $time == 0 ? 0 : time() + $time . "\n");
			fwrite($fp, serialize($data));
			
			fclose($fp);
		} else { throw_error("Cannot create " . $cache_file_path . ": " . @$php_errormsg, true); }

		return true;
	}
	
	function fetch($cache_id)
	{
		$cache_file_path = $this->save_path . "/" . $cache_id;
		// Возвращаем данные из кэша только если файл существует и дата модификации файла удовлетворяет времени кэширования
		if (file_exists($cache_file_path))
		{
			$fp = @fopen($cache_file_path, "r");
			if ($fp)
			{
				flock($fp, LOCK_SH);
				// Время кэширования данного файла				
				$time = @intval(trim(fgets($fp, 1024)));
				if ($time == 0 || time() <= $time)
				{
					if (filesize($cache_file_path))
					{
						$buffer = null; while (!feof($fp)) { $buffer .= @fread($fp, 1024); }
						$data = @unserialize($buffer);
						if ($data !== false)
						{
							// Проверяем все ли тэги существуют и нет ли среди них новее чем те, которые сохраняли мы
							// если есть - это означает что данный кэш уже не валидный
							foreach ($data["tags"] as $tag_id => $tag_check_time)
							{
								$tag_file_path = $this->save_path . "/" . $tag_id;
								if (!file_exists($tag_file_path)) { $data["data"] = null; break; }
								else
								{
									$tfp = @fopen($tag_file_path, "r");
									if ($tfp)
									{
										flock($tfp, LOCK_SH);
										$tag_time = trim(fgets($tfp));
										if ($tag_time > $tag_check_time) { $data["data"] = null; break; }
									}
									else { $data["data"] = null; break; }
								}
								
							}
							fclose($fp);
							// Если кэш оказался не валидным - удаляем его
							if ($data["data"] === null) { @unlink($cache_file_path); }
							return $data["data"];
						}
					}
				}
				fclose($fp);
			}
		}
		return null;
	}
	
	function delete($cache_id)
	{
		$cache_file_path = $this->save_path . "/" . $cache_id;
		if (file_exists($cache_file_path))
		{
			$fp = @fopen($cache_file_path, "r");
			if ($fp)
			{
				flock($fp, LOCK_SH); fclose($fp);
				if (!@unlink($cache_file_path)) { return false; }
			}
		}
		return true;
	}

	function delete_tags($tags)
	{
		foreach ($tags as $tag_id) { $this->delete($tag_id); }
		return true;
	}
}