<?php
// Класс для формирования .xls файлов
// класс является надстройкой над классом PHPExcel и напрямую использует его,
// поэтому для работы с данным классом вы должны скачать и установить класс PHPExcel например в
// application/classes папку.
// Скачать данный класс можно по адресу: http://www.codeplex.com/PHPExcel
class ExcelDocument
{
	private $factory = null;
	private $default_cell_style = null;

	// Инициализация базового класса
	function __construct()
	{
		$this->factory = new PHPExcel();
		$this->factory->removeSheetByIndex(0);
	}

	// Получение доступа к базовому классу
	function factory() { return $this->factory; }

	// Создание объекта ExcelDocument_Worksheet
	function sheet($name = null, $data = array()) { return new ExcelDocument_Worksheet($this, $name, $data); }

	// Создание объекта ExcelDocument_Cell
	function cell($value, $style = null) { return new ExcelDocument_Cell($this, $value, $style); }

	// Стиль по-умолчанию для всего документа в целом
	function set_default_style($style)
	{
		if (!$style instanceof ExcelDocument_Style) { $style = new ExcelDocument_Style($style); }
		$this->factory->getDefaultStyle()->applyFromArray($style->settings());
	}

	// Стиль для вновь создаваемых ячеек по-умолчанию
	function set_default_cell_style($style) { $this->default_cell_style = $style; }

	// Стиль для вновь создаваемых ячеек по-умолчанию
	function get_default_cell_style() { return $this->default_cell_style; }

	// Устанавливает активный лист
	function active_sheet($index)
	{
		if (is_numeric($index)) { $this->factory->setActiveSheetIndex($index); }
		else { $this->factory->setActiveSheetIndexByName($index); }
	}

	// Функция принимает на вход массив массивов
	// Каждый массив считается строчкой таблицы, и каждое значение
	// массива считается значением соотвествующей ячейки.
	// Значение ячейки может являться экземпляром объекта XLS_Cell
	function sendfile($filename = null)
	{
		// Если не создали ни одной книги - создаём одну и отсылаем файл
		if ($this->factory->getSheetCount() <= 0) { $this->sheet(); }
		HTTP::attachment(($filename === null ? "data_" . date("d.m.Y_H_i_s") . ".xls" : $filename), "application/vnd.ms-excel");

		// Перед отдачей контента нужно закрыть все соединения с базами данных
		// если таковые имеются - иначе пока файл будет скачиваться эти соединения будут
		// висеть как занятые, в этом ничего хорошего нет совсем
		if (class_exists("DBConnection", false) && method_exists("DBConnection", "close_all")) { DBConnection::close_all(); }

		$writer = new PHPExcel_Writer_Excel5($this->factory);
		$writer->save("php://output");
		exit;
	}

	// Полиморфный вызов с переменными параметрами, в самом начале передаются массивы данных,
	// самым последним параметром может идти имя файла - а может и не идти, каждый параметр массив
	static function create_and_send()
	{
		$args = func_get_args();
		$filename = null;
		$xls = new ExcelDocument();
		foreach ($args as $sheet_data)
		{
			if (is_array($sheet_data)) { $xls->sheet(null, $sheet_data); }
			else { $filename = $sheet_data; break; }
		}
		$xls->sendfile($filename);
	}

	// Возвращает объект чтения Excel-файла
	static function read($filename)
	{
		$xls = new ExcelDocument_Reader();
		$xls->load($filename);
		return $xls;
	}
}