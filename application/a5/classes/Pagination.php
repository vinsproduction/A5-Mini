<?php
class Pagination
{
	/* Возвращает массив с данными о навигации
	 * Первым параметром передаётся количество элементов которые нужно пролистывать
	 * Вторым параметром передаётся массив с ключами для уточнения параметров листинга, поддерживаются следующие ключи:
	 * limit - максимальное количество элементов выводимое на одной странице
	 * pages_limit - максимальное количество номеров страниц выводимое на одной странице
	 * varname - имя переменной в QUERY_STRING используемой для указания текущей страницы (По-умолчанию: page)
	 * uri - ссылка используемая для навигации (По-умолчанию: текущая страница)
	 *
	 * На выход отдаёт массив следующего вида:
	 * array
	 * (
	 * 	"pages" => array() of array("number" => 4, "link" => "script.php?...", "current" => true : false),
	 * 	"next_section" => array() of array("number" => 4, "link" => "script.php?...", "current" => true : false),
	 * 	"prev_section" => array() of array("number" => 4, "link" => "script.php?...", "current" => true : false),
	 * 	"next_page" => array("number" => 4, "link" => "script.php?...", "current" => true : false),
	 * 	"prev_page" => array("number" => 4, "link" => "script.php?...", "current" => true : false),
	 * 	"first_page" => array("number" => 1, "link" => "script.php?...", "current" => true : false),
	 * 	"last_page" => array("number" => 4, "link" => "script.php?...", "current" => true : false),
	 * 	"limit" => (int),
	 * 	"offset" => (int),
	 * )
	 *
	 * Ключи массива означают следующее:
	 * pages - массив с информацией о страницах (если пустой - навигация не требуется)
	 * next_section - информация о следующем блоке страниц - если таковой требуется, не пустой в случае если количество номеров страниц превышает pages_limit
	 * prev_section - информация о предыдущем блоке страниц - если таковой требуется, не пустой в случае если количество номеров страниц превышает pages_limit
	 * next_page - информация о следующей странице
	 * prev_page - информация о предыдущей странице
	 * first_page - информация о самой первой странице
	 * last_page - информация о самой последней странице
	 * limit - количество элементов выводимых на одной странице - можно использовать для SQL-выражений LIMIT
	 * offset - номер первого элемента на текущей странице относительно всего количества в целом, нумерация начинается с 0 - можно использовать для SQL-выражений OFFSET
	 */
	static function construct($count, $p = array())
	{
		$p["count"] = $count;
		if (!isset($p["limit"])) { $p["limit"] = 10; }
		if (!isset($p["pages_limit"])) { $p["pages_limit"] = 30; }
		if (!isset($p["varname"])) { $p["varname"] = "page"; }
		if (!isset($p["uri"])) { $p["uri"] = self_url(); }

		$p["count"] = @intval($p["count"]);
		$p["limit"] = @intval($p["limit"]);
		$p["pages_limit"] = @intval($p["pages_limit"]);

		parse_str($_SERVER["QUERY_STRING"], $query_string);

		if (isset($_GET[$p["varname"]])) { $current_page = intval($_GET[$p["varname"]]); } else { $current_page = 1; }
		$current_page = ($current_page <= 0) ? 1 : $current_page;
		if (($current_page - 1) * $p["limit"] >= $p["count"]) { $current_page = 1; }
		$p["current_page"] = $current_page;

		$p["offset"] = ($p["current_page"] - 1) * $p["limit"];
		$p["pages_count"] = @ceil($p["count"]/$p["limit"]);

		$p["pages"] = array();
		$p["next_section"] = array();
		$p["prev_section"] = array();
		$p["next_page"] = array();
		$p["prev_page"] = array();
		$p["first_page"] = array();
		$p["last_page"] = array();

		$need_navigation = $p["count"] > $p["limit"] ? true : false;

		if ($need_navigation)
		{
			$current_number = 0;
			$start_number = $p["current_page"] > $p["pages_limit"] ? $p["current_page"] - ($p["current_page"] % $p["pages_limit"] > 0 ? $p["current_page"] % $p["pages_limit"] - 1 : $p["pages_limit"] - 1) : 1;

			if ($p["current_page"] > $p["pages_limit"])
			{
				$page_link = $p["uri"] . build_query_string(array_merge($query_string, array($p["varname"] => $start_number - 1)));
				$p["prev_section"] = array
				(
					"number" => $start_number - 1,
					"link" => $page_link,
					"current" => false,
				);
			}

			for ($page_number = $start_number; $page_number <= $p["pages_count"]; $page_number++)
			{
				$page_link = $p["uri"] . build_query_string(array_merge($query_string, array($p["varname"] => $page_number)));

				if ($current_number >= $p["pages_limit"])
				{
					$p["next_section"] = array
					(
						"number" => $page_number,
						"link" => $page_link,
						"current" => false,
					);
					break;
				}

				$p["pages"][] = array
				(
					"number" => $page_number,
					"link" => $page_link,
					"current" => $page_number == $p["current_page"] ? true : false,
				);
				$current_number++;
			}

			if ($p["current_page"] - 1 > 0)
			{
				$page_link = $p["uri"] . build_query_string(array_merge($query_string, array($p["varname"] => $p["current_page"] - 1)));
				$p["prev_page"] = array
				(
					"number" => $p["current_page"] - 1,
					"link" => $page_link,
					"current" => false,
				);
			}

			if ($p["current_page"] * $p["limit"] + 1 <= $p["count"])
			{
				$page_link = $p["uri"] . build_query_string(array_merge($query_string, array($p["varname"] => $p["current_page"] + 1)));
				$p["next_page"] = array
				(
					"number" => $p["current_page"] + 1,
					"link" => $page_link,
					"current" => false,
				);
			}

			$page_link = $p["uri"] . build_query_string(array_merge($query_string, array($p["varname"] => 1)));
			$p["first_page"] = array
			(
				"number" => 1,
				"link" => $page_link,
				"current" => 1 == $p["current_page"] ? true : false,
			);

			$page_link = $p["uri"] . build_query_string(array_merge($query_string, array($p["varname"] => $p["pages_count"])));
			$p["last_page"] = array
			(
				"number" => $p["pages_count"],
				"link" => $page_link,
				"current" => $p["pages_count"] == $p["current_page"] ? true : false,
			);
		}

		return $p;
	}
}