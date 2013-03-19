<?php
class Image
{
	/*
	Класс для работы с картинками, основной метод: Image::transform()
	На вход получает два параметра - оба массивы:

	1 массив - данные о картинке, ключи этого массива идентичные ключам возвращаемым Upload::fetch(), из них важны только:
		path или data - если переданно и то и то - предпочтение отдается параметру data (если он не пуст)
		ИЛИ
		если первый параметр - строка - значит это должен быть путь к файлу картинки
		если среди ключей есть не пустой ключ errcode - то никакие действия с картинкой производится не будут

	2 массив - описание действий, которые нужно произвести с картинкой

		"resize" => 100 - алиас для resize_width = 100, resize_height = 100
		"resize_width" => 150 сделать картинку НЕ БОЛЕЕ 150 пикселов по ширине
		"resize_height" => 100 сделать картинку НЕ БОЛЕЕ 100 пикселов по высоте
		Используя совместо эти параметры они работают как "И", т.е. габариты картинки
		будут не более указанных значений.
		Если размеры изначальной картинки менее указанных габаритов, то картинка останется неизменной.
		Изменения размеров происходит пропорционально!

		"fit" => 100 - алиас для fit_width = 100, fit_height = 100
		"fit_width" => 150 сделать картинку шириной 150 пикселов
		"fit_height" => 100 сделать картинку высотой 100 пикселов
		Параметры полностью аналогичны параметрам "resize_width" и "resize_height"
		за исключением того что если картинка менее указанных габаритов, то она
		будет растянута до указанных размеров.

		"crop" => 100 - алиас для crop_width = 100, crop_height = 100
		"crop_width" => 100 - урезать картинку до ширины 100 пикселов, при этом размер
		вырезанной части будет 100 по ширине, а по высоте пропорционально размеру картинки.
		Вырезаться будет центральная часть картинки, но вы можете передать параметр
		"crop_left" => - который укажет сколько нужно отступить слева чтобы сделать вырез
		"crop_height", "crop_top" - параметры аналогичны двум предыдущим
		Используя совместно "crop_width" и "crop_height" вы указываете точный размер
		вырезаемой области - пропорциональный размер картинки пр этом в расчёт не берётся.

		"fit_min" => 100 - алиас для fit_min_width = 100, fit_min_height = 100
		"fit_min_width" => 100 - сделать картинку по ширине точно 100 пикселов, размер изменяется пропорционально.
		"fit_min_height" => 100 - аналогично предыдущему, но для высоты.
		Используя данные параметры совместно вы добиваетесь того что и ширина и высота картинки будет гарантировано
		не менее указанных значений. При этом наименьший габарит будет подогнан под указанное для него значение.
		Пример:
		 - У вас есть картинка размером 300х200 и вы указали параметры 100х100, таким образом вы получите
		   картинку размером 150х100.
		 - Исходная картинка размером 200х300 и вы указали параметры 100х100, таким образом вы получите
		   картинку размером 100х150
		Данные параметры очень удобно использовать совместно с crop-параметрами. К примеру если вам нужно
		получить картинку размером 100 на 100 при этом она должна занимать всё доступное пространство
		независимо от её пропорциональных размеров и при этом допускается обрезание лишних краёв, то можно
		использовать следующий набор параметров:
		"fit_min_width" => 100, "fit_min_height" => 100, "crop_width" => 100, "crop_height" => 100

		Собственно набор параметров выше полностью аналогичен более краткому виду
		"thumb" => 100 - алиас для thumb_width = 100, thumb_height = 100
		"thumb_width" => 100, "thumb_height" => 100
		данные параметры ЗАМЕЩАЮТ четыре (или соответствующие два) параметра указанные выше

		"rotate" => 0, 90, 180, 45 и любое другое число - поворот картинки на указанное число градусов
		"invert" => true - инвертировать цвета картинки
		"grayscale" => true - сделать картинку чёрно-белой
		"quality" => качество jpeg картинки (конечно только если это jpeg картинка) - по-умолчанию - 90

		"wm" - картинка для watermark - можно указать путь к файлу либо массив аналогичный первому параметру
		"wm_pos" - два параметра разделённых пробелом - первый - ориентация по горизонтали, второй - по вертикали
		параметры могут быть либо числом (пиксели) либо словами для первого параметра: left center right, для
		второго: top center bottom. По-умолчанию ориентация: right bottom
		Пример: Image::transform($img, array("wm" => "file.png", "wm_pos" => "center center"));

	Возвращает такой же массив переданный в первом параметре с дополнительными ключами:
	"modified" - true или false - изменилась ли картинка после обработки, в случае с false означает что никакие манипуляции с исходными данными картинки проделаны не были
	"errcode" - код ошибки если таковая возникла
	"errmsg" - текстовое описание возникшей ошибки

	Если подавление ошибок включено не было - функция завершит работу скрипта с выдачей ошибки и сообщением типа Fatal error
	*/
	static function transform($bin, $action = array())
	{
		if (is_scalar($bin)) { $bin = array("path" => $bin); }

		$bin["modified"] = false;

		// Если передали ошибочные данные - возвращаем их обратно
		if (@$bin["errcode"]) { return $bin; }

		$bin["errcode"] = null;
		$bin["errmsg"] = null;
		$bin["errparams"] = array();

		$image_type = null;
		$image_output_type = null;

		if (function_exists("imagecreatetruecolor") && function_exists("imagecreatefromstring"))
		{
			// Если передали путь к файлу - получаем данные и информацию о нём
			if (isset($bin["path"]) && (!isset($bin["data"]) || !strlen($bin["data"])))
			{
				unset($php_errormsg);
				$info = @getimagesize($bin["path"]);
				if ($info === false) { self::error($bin, "get_info_failed", array("error" => @$php_errormsg ? $php_errormsg : "Cannot detect image information")); }
				else
				{
					$fp = @fopen($bin["path"], "rb");
					if ($fp)
					{
						flock($fp, LOCK_SH);
						if (!isset($bin["name"])) { $bin["name"] = @basename($bin["path"]); }
						if (!isset($bin["ext"])) { $bin["ext"] = get_file_extension($bin["name"]); }
						$bin["data"] = null; while (!feof($fp)) { $bin["data"] .= fread($fp, 16384); }
						$bin["class"] = self::get_class($info[2]);
						$bin["subclass"] = self::get_subclass($info[2]);
						$bin["size"] = strlen($bin["data"]);
						$bin["width"] = $info[0];
						$bin["height"] = $info[1];
						$bin["type"] = $info["mime"];
						fclose($fp);
					}
					else { self::error($bin, "open_failed", array("path" => $bin["path"], "error" => @$php_errormsg)); }
				}
			}

			if (isset($bin["data"]) && strlen($bin["data"]) && !$bin["errcode"])
			{
				if (isset($bin["type"]))
				{
					if (preg_match("~^image/.*jpe?g~u", $bin["type"]) && function_exists("imagejpeg")) { $image_type = "image/jpg"; }
					elseif (preg_match("~^image/.*png~u", $bin["type"]) && function_exists("imagepng")) { $image_type = "image/png"; }
					elseif (preg_match("~^image/.*gif~u", $bin["type"]) && function_exists("imagegif")) { $image_type = "image/gif"; }
					if ($image_type === null) { self::error($bin, "type_unsupported", array("type" => $bin["type"])); }
				}

				// Если не указан тип исходной картинки и нет указания какого типа будет выходная
				// Указываем на то что выходная картинка должна быть типа image/jpeg
				if (!$bin["errcode"] && $image_type === null && !isset($action["type"])) { $action["type"] = "image/jpeg"; }

				if (isset($action["type"]))
				{
					if (preg_match("~^image/.*jpe?g~u", $action["type"]) && function_exists("imagejpeg")) { $image_output_type = "image/jpg"; }
					elseif (preg_match("~^image/.*png~u", $action["type"]) && function_exists("imagepng")) { $image_output_type = "image/png"; }
					elseif (preg_match("~^image/.*gif~u", $action["type"]) && function_exists("imagegif")) { $image_output_type = "image/gif"; }
					if ($image_output_type === null) { self::error($bin, "type_unsupported", array("type" => $action["type"])); }
				}
				else { $image_output_type = $image_type; }

				if (!$bin["errcode"])
				{
					foreach ($action as $param => $value)
					{
						switch ($param)
						{
							case "resize":
								$action = array_merge(array("resize_width" => $value, "resize_height" => $value), $action);
								unset($action[$param]);
								break;
							case "fit":
								$action = array_merge(array("fit_width" => $value, "fit_height" => $value), $action);
								unset($action[$param]);
								break;
							case "fit_min":
								$action = array_merge(array("fit_min_width" => $value, "fit_min_height" => $value), $action);
								unset($action[$param]);
								break;
							case "crop":
								$action = array_merge(array("crop_width" => $value, "crop_height" => $value), $action);
								unset($action[$param]);
								break;
							case "thumb":
								$action = array_merge(array("thumb_width" => $value, "thumb_height" => $value), $action);
								unset($action[$param]);
								break;
						}
					}

					$params = array
					(
						"resize_width",
						"resize_height",
						"fit_width",
						"fit_height",
						"fit_min_width",
						"fit_min_height",
						"crop_width",
						"crop_height",
						"thumb_width",
						"thumb_height",
					);

					foreach ($params as $param)
					{
						if (@is_numeric($action[$param]) && intval($action[$param]) > 0)
						{ $action[$param] = intval($action[$param]); } else { unset($action[$param]); }
					}

					if (@is_numeric($action["crop_left"]))
					{ $action["crop_left"] = intval($action["crop_left"]); } else { unset($action["crop_left"]); }

					if (@is_numeric($action["crop_top"]))
					{ $action["crop_top"] = intval($action["crop_top"]); } else { unset($action["crop_top"]); }

					if (@$action["invert"]) { $action["invert"] = true; } else { unset($action["invert"]); }
					if (@$action["grayscale"]) { $action["grayscale"] = true; } else { unset($action["grayscale"]); }

					if (@is_numeric($action["quality"]) && intval($action["quality"]) > 0 && $image_output_type == "image/jpg")
					{ $action["quality"] = intval($action["quality"]); } else { unset($action["quality"]); }

					if (@is_numeric($action["rotate"])) { $action["rotate"] = intval($action["rotate"]); } else { unset($action["rotate"]); }

					if (isset($action["wm_pos"]))
					{
						@list($hor, $vrt) = preg_split("/\s+/u", $action["wm_pos"], 2);
						if
						(
							(in_array($hor, array("left", "center", "right")) || is_numeric($hor))
							&& (in_array($vrt, array("top", "center", "bottom")) || is_numeric($vrt))
						)
						{ $action["wm_pos"] = $hor . " " . $vrt; }
						else { unset($action["wm_pos"]); }
					}

					// Раскрываем параметры thumb_width и thumb_height - здесь используется такой хитрожопый приём
					// для того чтобы сохранить порядок указания действий при этом убрав лишние и подставив новые
					// Пример - если хочется сначала сделать thumb из картинки, а потом уже сделаь grayscale
					// то обычно функция и вызывается в данном порядке параметров - если бы здесь мы просто
					// добавляли в массив новые параметры, то grayscale оказался бы в итоге первым действием
					// соотвественно сделать картинку grayscale большую намного меньше чем сделать grayscale
					// её thumb
					if (isset($action["thumb_width"]) || isset($action["thumb_height"]))
					{
						$new_action = array();
						foreach ($action as $action_name => $action_value)
						{
							if (isset($action["thumb_width"]))
							{
								if (in_array($action_name, array("fit_min_width", "crop_width"))) { continue; }
								if ($action_name == "thumb_width")
								{
									$new_action["fit_min_width"] = $action_value;
									$new_action["crop_width"] = $action_value;
									continue;
								}
							}

							if (isset($action["thumb_height"]))
							{
								if (in_array($action_name, array("fit_min_height", "crop_height"))) { continue; }
								if ($action_name == "thumb_height")
								{
									$new_action["fit_min_height"] = $action_value;
									$new_action["crop_height"] = $action_value;
									continue;
								}
							}
							$new_action[$action_name] = $action_value;
						}
						$action = $new_action;
					}

					if ($src_im = @imagecreatefromstring($bin["data"]))
					{
						$generate_image = false;

						$src_width = $new_width = imagesx($src_im);
						$src_height = $new_height = imagesy($src_im);

						$bin["size"] = strlen($bin["data"]);
						$bin["width"] = $src_width;
						$bin["height"] = $src_height;

						if (count($action))
						{
							if ($src_width > 0 && $src_height > 0)
							{
								// Подготовка списка действий с картинкой
								$do_transform = array();
								foreach ($action as $action_name => $action_value)
								{
									switch ($action_name)
									{
										case "resize_width":
										case "resize_height":
											$image_action = array();
											if (isset($action["resize_width"])) { $image_action["width"] = $action["resize_width"]; }
											if (isset($action["resize_height"])) { $image_action["height"] = $action["resize_height"]; }
											self::resize($new_width, $new_height, $image_action);
											if ($new_width != $src_width || $new_height != $src_height) { $do_transform["resize"] = array($new_width, $new_height); }
											break;

										case "fit_width":
										case "fit_height":
											$image_action = array();
											if (isset($action["fit_width"])) { $image_action["width"] = $action["fit_width"]; }
											if (isset($action["fit_height"])) { $image_action["height"] = $action["fit_height"]; }
											self::fit($new_width, $new_height, $image_action);
											if ($new_width != $src_width || $new_height != $src_height) { $do_transform["resize"] = array($new_width, $new_height); }
											break;

										case "fit_min_width":
										case "fit_min_height":
											$image_action = array();
											if (isset($action["fit_min_width"])) { $image_action["width"] = $action["fit_min_width"]; }
											if (isset($action["fit_min_height"])) { $image_action["height"] = $action["fit_min_height"]; }
											self::fit_min($new_width, $new_height, $image_action);
											if ($new_width != $src_width || $new_height != $src_height) { $do_transform["resize"] = array($new_width, $new_height); }
											break;

										case "crop_width":
										case "crop_height":
											// Новые размеры картинки уже могли стать другими за счёт операций "resize"
											// поэтому crop делается на основе их, а не на основе размеров исходной картинки
											if (isset($action["crop_width"]) && !isset($action["crop_height"]))
											{ $action["crop_height"] = round($new_height * ($action["crop_width"] / $new_width)); }

											if (isset($action["crop_height"]) && !isset($action["crop_width"]))
											{ $action["crop_width"] = round($new_width * ($action["crop_height"] / $new_height)); }

											if (!isset($action["crop_top"]))
											{ $action["crop_top"] = round(($new_height - $action["crop_height"]) / 2); }
											if (!isset($action["crop_left"]))
											{ $action["crop_left"] = round(($new_width - $action["crop_width"]) / 2); }

											$do_transform["crop"] = array($action["crop_left"], $action["crop_top"], $action["crop_width"], $action["crop_height"]);
											break;

										case "wm":
											$wm = @self::transform($action_value);
											if ($wm["errcode"]) { self::error($bin, "wm_error", array("error" => $wm["errmsg"])); }
											else
											{
												if ($wm["width"] > $new_width || $wm["height"] > $new_height)
												{
													$wm = @self::transform($wm, array("resize_width" => round($new_width * 0.2), "resize_height" => round($new_height * 0.2)));
													if ($wm["errcode"]) { self::error($bin, "wm_error", array("error" => $wm["errmsg"])); }
												}

												if (!$wm["errcode"])
												{
													$wm_pos = array("right", "bottom");
													if (isset($action["wm_pos"])) { $wm_pos = explode(" ", $action["wm_pos"], 2); }
													$do_transform["wm"] = array($wm, $wm_pos);
												}
											}
											break;

										case "grayscale": $do_transform["grayscale"] = true; break;
										case "invert": $do_transform["invert"] = true; break;
										case "rotate": $do_transform["rotate"] = array($action_value); break;

										// Если просят изменить тип - говорим что картинку надо сгенерить
										case "type":
											if ($image_type != $image_output_type) { $generate_image = true; }
											break;

										// Если просят изменить качество и тип картинки jpeg - говорим что нужно генерить
										case "quality":
											if ($image_output_type == "image/jpg") { $generate_image = true; }
											break;
									}

									if ($bin["errcode"]) { break; }
								}

								if (!$bin["errcode"])
								{
									// Если с картинкой нужно производить какие-либо действия
									if (count($do_transform))
									{
										foreach ($do_transform as $transform => $params)
										{
											// Перед началом любой трансформации - получаем текущий размер картинки
											$src_width = imagesx($src_im);
											$src_height = imagesy($src_im);

											switch ($transform)
											{
												case "resize":
													$dst_im = @imagecreatetruecolor($params[0], $params[1]);
													if ($dst_im)
													{
														imagealphablending($dst_im, false);
														imagesavealpha($dst_im, true);
														if (@imagecopyresampled($dst_im, $src_im, 0, 0, 0, 0, $params[0], $params[1], $src_width, $src_height))
														{
															$src_im = $dst_im;
															unset($dst_im);
															$generate_image = true;
														}
													}
													break;

												case "crop":
													$dst_im = @imagecreatetruecolor($params[2], $params[3]);
													if ($dst_im)
													{
														imagealphablending($dst_im, false);
														imagesavealpha($dst_im, true);
														if (@imagecopy($dst_im, $src_im, 0, 0, $params[0], $params[1], $params[2], $params[3]))
														{
															$src_im = $dst_im;
															unset($dst_im);
															$generate_image = true;
														}
													}
													break;

												case "grayscale":
													imagefilter($src_im, IMG_FILTER_GRAYSCALE);
													$generate_image = true;
													break;

												case "invert":
													for ($x = 0; $x < $src_width; $x++)
													{
														for ($y = 0; $y < $src_height; $y++)
														{
															$rgb = imagecolorat($src_im, $x, $y);
															$r = ($rgb >> 16) & 0xff;
															$g = ($rgb >> 8) & 0xff;
															$b = $rgb & 0xff;
															$r = 255 - $r; $g = 255 - $g; $b = 255 - $b;
															$color = imagecolorallocate($src_im, $r, $g, $b);
															imagesetpixel($src_im, $x, $y, $color);
														}
													}
													$generate_image = true;
													break;

												case "rotate":
													$src_im = imagerotate($src_im, $params[0], 0);
													$generate_image = true;
													break;

												case "wm":
													$wm_im = @imagecreatefromstring($params[0]["data"]);
													if ($wm_im)
													{
														$wm_pos = $params[1];
														$wm_width = imagesx($wm_im);
														$wm_height = imagesy($wm_im);

														if (is_numeric($wm_pos[0]))
														{
															$wm_pos[0] = intval($wm_pos[0]);
															$wm_left = $wm_pos[0];
															if ($wm_left < 0) { $wm_left = $src_width - $wm_width + $wm_left; }
														}
														else
														{
															if ($wm_pos[0] == "left") { $wm_left = 0; }
															if ($wm_pos[0] == "center") { $wm_left = round($src_width / 2 - $wm_width / 2); }
															if ($wm_pos[0] == "right") { $wm_left = $src_width - $wm_width; }
														}

														if (is_numeric($wm_pos[1]))
														{
															$wm_pos[1] = intval($wm_pos[1]);
															$wm_top = $wm_pos[1];
															if ($wm_top < 0) { $wm_top = $src_height - $wm_height + $wm_top; }
														}
														else
														{
															if ($wm_pos[1] == "top") { $wm_top = 0; }
															if ($wm_pos[1] == "center") { $wm_top = round($src_height / 2 - $wm_height / 2); }
															if ($wm_pos[1] == "bottom") { $wm_top = $src_height - $wm_height; }
														}

														imagealphablending($src_im, true);
														@imagecopyresampled($src_im, $wm_im, $wm_left, $wm_top, 0, 0, $wm_width, $wm_height, $wm_width, $wm_height);
														$generate_image = true;
													}
													break;
											}
										}
									}
								}
							}
							else { self::error($bin, "invalid_dimentions", array("width" => $src_width, "height" => $src_height)); }

							// Если не было ошибок и требуется сгенерировать новую картинку
							if (!$bin["errcode"] && $generate_image)
							{
								ob_start();
								switch ($image_output_type)
								{
									case "image/jpg": $bin["subclass"] = "jpg"; @imagejpeg($src_im, null, isset($action["quality"]) ? $action["quality"] : 90); break;
									case "image/png": $bin["subclass"] = "png"; @imagepng($src_im); break;
									case "image/gif": $bin["subclass"] = "gif"; @imagegif($src_im); break;
								}
								$bin["modified"] = true;
								$bin["class"] = "image";
								$bin["type"] = $image_output_type;
								$bin["width"] = imagesx($src_im);
								$bin["height"] = imagesy($src_im);
								$bin["data"] = ob_get_contents();
								$bin["size"] = strlen($bin["data"]);
								ob_end_clean();
							}
						}
					}
					else { self::error($bin, "create_failed"); }
				}
			}
		}
		else { self::error($bin, "gd_not_available"); }

		if (error_reporting() && @$bin["errcode"]) { throw_error($bin["errmsg"], true); }

		return $bin;
	}

	static private function error(&$bin, $code, $code_params = array())
	{
		$codes = array
		(
			"gd_not_available" => "ImageCreateTrueColor and ImageCreateFromString not available, check installation of GD library.",
			"create_failed" => "ImageCreateFromString cannot create image resource from provided data. Unsupported format or data corrupted.",
			"invalid_dimentions" => "Invalid dimenstions of source image: {width} x {height}",
			"type_unsupported" => "This type of image is unsupported: {type}",
			"open_failed" => "Cannot open {path} for read: {error}",
			"get_info_failed" => "Get info failed: {error}. Unsupported format or data corrupted.",
			// Это рекурсивный код - будет содержать сообщение одно из тех что выше
			"wm_error" => "Watermark error: {error}",
		);

		if (!isset($codes[$code])) { throw_error("Unknown error code: " . $code, true); }

		$bin["errcode"] = $code;
		$search = array(); $replace = array();
		foreach ($code_params as $key => $value) { $search[] = "{" . $key . "}"; $replace[] = $value; }
		$bin["errmsg"] = str_replace($search, $replace, $codes[$code]);
		if (count($search) && count($replace)) { $bin["errparams"] = array_combine($search, $replace); } else { $bin["errparams"] = array(); }
	}

	static function resize(&$width, &$height, $action = array())
	{
		if (isset($action["width"]) && intval($action["width"]) > 0)
		{ $action["width"] = intval($action["width"]); } else { unset($action["width"]); }
		if (isset($action["height"]) && intval($action["height"]) > 0)
		{ $action["height"] = intval($action["height"]); } else { unset($action["height"]); }

		if (isset($action["width"]))
		{
			if ($width > $action["width"])
			{
				$height = $width > 0 ? round($height * ($action["width"] / $width)) : 0;
				$width = $action["width"];
			}
		}

		if (isset($action["height"]))
		{
			if ($height > $action["height"])
			{
				$width = $height > 0 ? round($width * ($action["height"] / $height)) : 0;
				$height = $action["height"];
			}
		}
	}

	static function fit(&$width, &$height, $action = array())
	{
		if (isset($action["width"]) && intval($action["width"]) > 0)
		{ $action["width"] = intval($action["width"]); } else { unset($action["width"]); }
		if (isset($action["height"]) && intval($action["height"]) > 0)
		{ $action["height"] = intval($action["height"]); } else { unset($action["height"]); }

		if (isset($action["width"]))
		{
			$new_height = $width > 0 ? round($height * ($action["width"] / $width)) : 0;
			if ((isset($action["height"]) && $new_height <= $action["height"]) || !isset($action["height"]))
			{
				$height = $new_height;
				$width = $action["width"];
			}
		}

		if (isset($action["height"]))
		{
			$new_width = $height > 0 ? round($width * ($action["height"] / $height)) : 0;
			if ((isset($action["width"]) && $new_width <= $action["width"]) || !isset($action["width"]))
			{
				$width = $new_width;
				$height = $action["height"];
			}
		}
	}

	static function fit_min(&$width, &$height, $action = array())
	{
		if (isset($action["width"]) && intval($action["width"]) > 0)
		{ $action["width"] = intval($action["width"]); } else { unset($action["width"]); }
		if (isset($action["height"]) && intval($action["height"]) > 0)
		{ $action["height"] = intval($action["height"]); } else { unset($action["height"]); }

		if (isset($action["width"]))
		{
			$new_height = $width > 0 ? round($height * ($action["width"] / $width)) : 0;
			if ((isset($action["height"]) && $new_height >= $action["height"]) || !isset($action["height"]))
			{
				$height = $new_height;
				$width = $action["width"];
			}
		}

		if (isset($action["height"]))
		{
			$new_width = $height > 0 ? round($width * ($action["height"] / $height)) : 0;
			if ((isset($action["width"]) && $new_width >= $action["width"]) || !isset($action["width"]))
			{
				$width = $new_width;
				$height = $action["height"];
			}
		}
	}

	// На вход передаётся то что возвращается getimagesize с индексом 2
	static function get_class($IMAGETYPE_XXX)
	{
		switch ($IMAGETYPE_XXX)
		{
			case IMAGETYPE_SWF:
			case IMAGETYPE_SWC:
				return "flash"; break;
			default:
				return "image";
				break;
		}
	}

	static function get_subclass($IMAGETYPE_XXX)
	{
		switch ($IMAGETYPE_XXX)
		{
			case IMAGETYPE_GIF: return "gif"; break;
			case IMAGETYPE_JPEG: return "jpg"; break;
			case IMAGETYPE_PNG: return "png"; break;
			case IMAGETYPE_SWF: return "swf"; break;
			case IMAGETYPE_PSD: return "psd"; break;
			case IMAGETYPE_BMP: return "bmp"; break;
			case IMAGETYPE_TIFF_II:
			case IMAGETYPE_TIFF_MM: return "tiff"; break;
			case IMAGETYPE_JPC: return "jpc"; break;
			case IMAGETYPE_JP2: return "jp2"; break;
			case IMAGETYPE_JPX: return "jpf"; break;
			case IMAGETYPE_JB2: return "jb2"; break;
			case IMAGETYPE_SWC: return "swc"; break;
			case IMAGETYPE_IFF: return "aiff"; break;
			case IMAGETYPE_WBMP: return "wbmp"; break;
			case IMAGETYPE_XBM: return "xbm"; break;
			case IMAGETYPE_ICO: return "ico"; break;
		}
	}
}