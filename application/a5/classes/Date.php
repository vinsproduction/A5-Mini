<?php
// Класс помощник для работы с датами
class Date
{
	// Основной часто-используемый метод
	// Первым параметром метод принимает либо строку - в таком случае пытается преобразовать её в объект DateTime
	// либо уже готовый объект DateTime (http://www.php.net/datetime)
	// второй параметр - способ форматирования возвращаемой даты, если не передан - возвращается сам объект DateTime
	// параметры для форматирования можно посмотреть здесь: http://ru.php.net/manual/en/function.date.php
	// в случае если входная дата неверная - возвращает false.
	// В отличии от стандартного поведения объекта DateTime при передачи пустой строки метод не возвращает текущее время,
	// вместо этого он возвращает false - указывая на то что дату не указали или указали неверно
	static function format($date, $format = null)
	{
		if (!($date instanceof DateTime))
		{
			if ($date === null) { return false; }
			if (is_empty($date)) { return false; }
			// Для поддержки ввода даты в формате: YYYY.MM.DD
			$date = preg_replace("/^ \s* (\d{3,})\.(\d{1,2})\.(\d{1,2}) (\s*)/sux", "$1-$2-$3$4", $date);
			$date = @date_create($date);
			if ($date === false) { return false; }
		}
		if ($format === null) { return $date; }
		else { return date_format($date, $format); }
	}

	// Возвращает массив содержащий инфрмацию о количестве дней в каждом из месяцев для указанного года
	static function days_in_months($year)
	{
		$days_in_month = array(1 => 31, 2 => 28, 3 => 31, 4 => 30, 5 => 31, 6 => 30, 7 => 31, 8 => 31, 9 => 30, 10 => 31, 11 => 30, 12 => 31);
		if (($year % 4 != 0) || ($year % 100 == 0) && ($year % 400 != 0)) { $days_in_month[2] = 28; }
		else { $days_in_month[2] = 29; }
		return $days_in_month;
	}

	// Возвращает количество дней в указанном месяце для указанного года, месяц от 1 до 12
	// если месяц передан неверно - false
	static function days_in_month($month, $year)
	{
		$days_in_month = self::days_in_months($year);
		if (isset($days_in_month[intval($month)])) { return $days_in_month[intval($month)]; }
		return false;
	}
	
	// Конвертировние номера месяца в название
	static function convert_month($format){
	
		if( !is_numeric($month) ) return $month;
	
		$month = intval($month);

		$monthNames = array("января", "февраля", "марта", "апреля", "мая", "июня", "июля", "августа", "сентября", "октября", "ноября", "декабря");
	
		return isset($monthNames[$month-1]) ? $monthNames[$month-1] : $month;

	}
	
	
}


