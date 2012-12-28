<?php
/*
Класс для генерации кодов защищающих от роботов при отправке форм.
В подпапке robocode лежит файл robocode.php - пример для генерации
картинки с кодом и пример подключения и использования данного класса.

Пример использования класса:
<?php
$robocode = new RoboCode("data/codes");

if (isset($_GET["get_image"])) { $robocode->output_code_image($_GET["get_image"], 200, 100); exit; }

if (isset($_POST["code_id"]))
{
	// Если код введён и введён правильно
	if ($robocode->valid_code($_POST["code"], $_POST["code_id"]))
	{
		// Выполняются действия с данными формы
		...
	}
}
?>
<form>
<input type="hidden" name="code_id" value="<?= $robocode->get_code_id() ?>">
<input type="hidden" name="code" value="">
<img src="<?= $_SERVER["PHP_SELF"] ?>?<?= $robocode->get_code_id() ?>">
<input type="submit">
</form>
*/

class RoboCode
{
	private $codes_dir = null;
	private $current_code_id = null;
	private $current_code_number = null;

	// Конструктор - при создании класса производятся следующие действия:
	// 1) Создаётся папка где будут хранится коды - иначе останавливает выполнение скрипта и выдаёт ошибку
	// 2) Если папка уже есть и в ней есть застарелые коды (например код был сгенерирован и выведен на странице но не был проверен)
	function __construct($codes_path)
	{
		if (!is_dir($codes_path))
		{
			if (@!mkdirs($codes_path))
			{ throw_error("Cannot create $codes_path: " . $GLOBALS["php_errormsg"], true); }
		}

		$this->codes_dir = preg_replace("{/+$}", "", $codes_path);

		// Удаляем старые файлы (которые последний раз были созданы более часа назад)
		// С вероятностью - 1%
		if (get_probability(1))
		{
			$dir = @opendir($this->codes_dir);
			while ($file = @readdir($dir))
			{
				if ($file == "." || $file == "..") { continue; }
				if (@filemtime($this->codes_dir . "/" . $file) < time() - 60 * 60)
				{ @unlink($this->codes_dir . "/" . $file); }
			}
		}
	}

	// Метод устанавливает текущее значение проверочного кода (создаёт для него файл и записывает туда значение)
	function set_code($code)
	{
		$code_id = null;
		// Бесконечный цикл пока не создадим уникальный файл с кодом
		while ($code_id === null)
		{
			$code_id = generate_key(64);
			$code_file = $this->codes_dir . "/" . $code_id;

			// Если такой есть, то обнуляем ай-ди и повторяем цикл
			if (@file_exists($code_file)) { $code_id = null; }
			else
			{
				$fp = @fopen($code_file, "w") or throw_error("Cannot open " . $code_file . ": " . $php_errormsg, true);
				@fwrite($fp, $code) or throw_error("Cannot write " . $code_file . ": " . $php_errormsg, true);
				@fclose($fp) or throw_error("Cannot close " . $code_file . ": " . $php_errormsg, true);
			}
		}
		$this->current_code_id = $code_id;
		return $code;
	}

	// Метод генерирует код для картинки - по-умолчанию - 4 цифры и устанавливает его как текущий
	function gen_code($len = 4)
	{
		$chars = "0123456789";
		$code_number = ""; while ($len--) { $code_number .= $chars[random(0, strlen($chars) - 1)]; }
		return $this->set_code($code_number);
	}

	// Метод возвращает текущий id кода - если его ещё нет - генерирует новый id, новый код и создаёт для него файл
	function get_code_id()
	{
		if ($this->current_code_id === null) { $this->gen_code(); }
		return $this->current_code_id;
	}

	// Функция возвращает сам код по его идентификатору
	// Функцию используют в скрипте генерации картинки с кодом, а также для проверки правильности ввода кода
	// Функция на вход берёт идентификатор кода
	// Если такого кода не существует - возвращает null, иначе - сам код
	function get_code($id)
	{
		$code_file = $this->get_code_file($id);
		if (@file_exists($code_file))
		{
			$text = @file_get_contents($code_file);
			if (!is_empty($text)) { return trim($text); }
		}
		return null;
	}

	// Функция для проверки правильности ввода кода, на вход введёный код первым параметром
	// и его идентификатор - вторым. Если код не введён, такого нет или он не правильный
	// возвращает false. Иначе - true
	function valid_code($code, $id)
	{
		$curr_code = $this->get_code($id);
		// После получения кода - удаляем файл с кодом, т.к. после проверки кода
		// в любом случае правильно введён он или нет - должен генерироваться новый код,
		// иначе хакер сможет всё время передавать один и тот же идентификатор кода и тем самым его подобрать
		$code_file = $this->get_code_file($id);
		if (@file_exists($code_file)) { @unlink($code_file); }
		// Также обнуляем id кода
		$this->current_code_id = null;

		// Если такой код не существует - всегда false
		if ($curr_code === null) { return false; }
		return (trim($code) == trim($curr_code));
	}

	// Получает полный путь к файлу с кодом
	function get_code_file($id)
	{
		$id = preg_replace("/[^a-zA-Z0-9_\.-]/", "", $id);
		$code_file = $this->codes_dir . "/" . $id;
		return $code_file;
	}

	// Функция выдаёт в браузер картинку с нарисованным на ней кодом
	// вам не обязательно нужно использовать именно эту функцию - вы можете использовать свою
	// всё что вам требуется сделать в скрипте генерации картинки
	// $robocode = new RoboCode("...");
	// $code_number = $robocode->get_code($_GET["id"]);
	// и далее сгенерировать картинку с нарисованным на ней $code_number
	function output_code_image($code_id, $width, $height)
	{
		$text = $this->get_code($code_id);
		$fonts = glob(__DIR__ . "/RoboCode/*.ttf");

		$chars = array();
		for ($i=0; $i < strlen($text); $i++)
		{
			$thischar = substr($text, $i, 1);
			$chars[] = array
			(
				"char" => $thischar,
				"font_size" => random(ceil($height * 0.9), $height),
				"font" => $fonts[random(0, sizeof($fonts)-1)],
				"angle" => random(-10, 10),
			);
		}

		// Картинка бэкграунда
		$im = imagecreatetruecolor($width, $height);
		$black = imagecolorallocate($im, 255, 255, 255);
		imagefilledrectangle($im, 0, 0, $width, $height, $black);

		// Заполнение картинки бэкграунда - шумом
		$x1s = $x2s = $y1s = $y2s = array();
		for ($i = 0; $i < 5; $i++)
		{
			$x1s = array_merge($x1s, range(0, $width - 1));
			$x2s = array_merge($x2s, range(0, $width - 1));
			$y1s = array_merge($y1s, range(0, $height - 1));
			$y2s = array_merge($y2s, range(0, $height - 1));
		}

		shuffle($x1s); shuffle($x2s); shuffle($y1s); shuffle($y2s);

		while (count($x1s) + count($x2s) + count($y1s) + count($y2s))
		{
			$c = random(70, 185);
			if (count($x1s) && count($x2s))
			{ imageline($im, array_shift($x1s), 0, array_shift($x2s), $height - 1, imagecolorallocate($im, $c, $c, $c)); }

			$c = random(70, 185);
			if (count($y1s) && count($y2s))
			{ imageline($im, 0, array_shift($y1s), $width - 1, array_shift($y2s), imagecolorallocate($im, $c, $c, $c)); }
		}
		// Заверешение части заполнения шумом

		// Создание картинок для каждого отдельного символа
		for ($i = 0; $i < count($chars); $i++)
		{
			$char =& $chars[$i];
			$bbox = imagettfbbox($char['font_size'], $char["angle"], $char['font'], $char['char']);
			// правый нижний x - левый нижний x = ширина
			$char['width'] = abs($bbox[2] - $bbox[0]);
			$char['height'] = abs($bbox[1] - $bbox[7]);

			$char["img"] = imagecreatetruecolor($char['width'], $char['height']);
			$black = imagecolorallocate($char["img"], 0, 0, 0);
			imagefilledrectangle($char["img"], 0, 0, $width, $height, $black);
			imagecolortransparent($char["img"], $black);

			$c = random(0, 1) ? random(40, 80) : random(175, 215);
			$color = imagecolorallocate($char["img"], $c, $c, $c);
			imagettftext($char["img"], $char['font_size'], $char["angle"], -$bbox[0], -$bbox[1] + $char['height'], $color, $char['font'], $char['char']);
		}

		// Суммарная ширина символов
		$summary_width = 0; for ($i = 0; $i < sizeof($chars); $i++) { $summary_width += $chars[$i]["width"]; }

		// Накладываем каждый символ на картинку с бэкграундом
		$x = 0;
 		for ($i = 0; $i < sizeof($chars); $i++)
 		{
			$place_width = ceil($width * ($chars[$i]["width"] / $summary_width));
			imagecopymerge($im, $chars[$i]["img"], $x + ceil(($place_width - $chars[$i]["width"]) / 2), 0 + ceil(($height - $chars[$i]["height"]) / 2), 0, 0, $chars[$i]["width"], $chars[$i]["height"], 60);
 			$x += $place_width;
 		}

		header("Content-Type: image/jpeg");
		imagejpeg($im, null, 30);
		imagedestroy($im);
	}
}