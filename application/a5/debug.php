<?php
A5::$debug = new Debug();

if (DEBUG_FILE) { A5::$debug->log_file(DEBUG_FILE); }

// Основная функция вывода дебаг-информации в скриптах - везде используйте её
function debug($msg, $group = null, $color = null, $tip = null) { A5::$debug->log_message($msg, $group, $color, $tip); }

if (!CONSOLE_MODE && !AJAX_REQUEST)
{
	// Функция модифицирует переданный ей html добавляя к нему дебаг-информацию в консоли
	function debug_append_data($s)
	{
		$summary_execute_time = 0;
		$summary_fetch_time = 0;
		$finish_time = A5::finish_time();

		$all_sql = A5::get_sql_executed();
		foreach ($all_sql as $result)
		{
			$call_place = "in file " . $result->file . " on line " . $result->line;
			A5::$debug->message(ltrim_lines($result->sql) . ";", 'SQL', null, $call_place);
			A5::$debug->message
			(
				"-- " .
				sprintf("%.3f sec", $result->exec_time + $result->fetch_time) .
				" = " .
				sprintf("%.3f", $result->exec_time) .
				"+" .
				sprintf("%.3f", $result->fetch_time) .
				'; ' .
				'returned ' . ($result->count) . ' row(s)'
				, 'SQL', 'green', $call_place
			);

			if ($result->cached) { A5::$debug->message("--- " . ($result->performed ? "PERFORMED & " : "") . "CACHED ---", "SQL", "green", $call_place); }

			A5::$debug->message('', 'SQL', $call_place);

			$summary_execute_time += $result->exec_time;
			$summary_fetch_time += $result->fetch_time;
		}

		A5::$debug->message(get_included_files(), "PHP-Included");
		A5::$debug->message(A5::get_config_params(), "Configuration");

		if (defined("A5_SCRIPT_START_TIME"))
		{
			A5::$debug->log_message(sprintf("SQL: %.3f sec = %.3f + %.3f", $summary_execute_time + $summary_fetch_time, $summary_execute_time, $summary_fetch_time), 'Work time');
			A5::$debug->log_message(sprintf("CODE: %.3f sec", $finish_time - A5_SCRIPT_START_TIME - $summary_execute_time - $summary_fetch_time), 'Work time');
			A5::$debug->log_message(sprintf("SUMMARY: %.3f sec", $finish_time - A5_SCRIPT_START_TIME), 'Work time');
		}
		return A5::$debug->append_html_data($s);
	}

	function ob_debug_append_data($buffer) { return in_array(A5::output_type(), array("html")) ? debug_append_data($buffer) : $buffer; }
	ob_start('ob_debug_append_data');
}
else
{
	// Функция вывода финальной информации в лог-файл
	function debug_final_information()
	{
		if (defined("A5_SCRIPT_START_TIME"))
		{
			$finish_time = A5::finish_time();

			A5::$debug->log(sprintf("SQL: %.3f sec = %.3f + %.3f", A5::get_sql_exec_time() + A5::get_sql_fetch_time(), A5::get_sql_exec_time(), A5::get_sql_fetch_time()));
			A5::$debug->log(sprintf("CODE: %.3f sec", $finish_time - A5_SCRIPT_START_TIME - A5::get_sql_exec_time() - A5::get_sql_fetch_time()));
			A5::$debug->log(sprintf("SUMMARY: %.3f sec", $finish_time - A5_SCRIPT_START_TIME));
		}
	}
	register_shutdown_function('debug_final_information');
}