<?php
// Для замера времени исполнения запроса
if (!defined("A5_SCRIPT_START_TIME")) { define("A5_SCRIPT_START_TIME", microtime(true)); }

if (!defined("APP_DIR")) { die("APP_DIR constant is not defined! Define it in your environment.php file."); }
if (!defined("PUBLIC_DIR")) { die("PUBLIC_DIR constant is not defined! Define it in your environment.php file."); }

// Основные настройки
error_reporting(E_ALL | E_STRICT);
ini_set("track_errors", "on");
ini_set("display_errors", "on");
ini_set("html_errors", "off");
ini_set("allow_url_fopen", "on");
ini_set("magic_quotes_runtime", "off");
ini_set("magic_quotes_sybase", "off");
ini_set("memory_limit", -1);
ini_set("default_charset", "utf-8");
ini_set("iconv.internal_encoding", "utf-8");
ini_set("mbstring.internal_encoding", "utf-8");
date_default_timezone_set(@date_default_timezone_get());

// Подключение основного класса фреймвока
require_once(__DIR__ . "/classes/_helpers.php");
require_once(__DIR__ . "/classes/A5.php");

// Определение корневой папки ядра
if (!defined("CORE_DIR")) { define("CORE_DIR", normalize_path(__DIR__)); }

// Запомним текущий режим вывода ошибок
A5::$error_reporting = error_reporting();
// Считывание всех конфиг-файлов
A5::config_read_ini_files();
// Установка конфиг-параметров зависимых от среды
A5::config_set_runtime_params();
// Превращение конфиг-параметров в константы
A5::config_setup_constants();
// Считывание всех URL-мап создание именованных мапов
A5::setup_url_maps();
// Импортирование стандартных функций
A5::import_functions();

// Устанавливаем локаль
setlocale(LC_CTYPE, DEFAULT_LOCALE);

// Регистрируем стандартную функцию авто-загрузки классов
spl_autoload_register(array("A5", "autoload"));

// Если скрипт запускается из под консоли - то скорее всего это крон-скрипт
if (CONSOLE_MODE) { require_once(__DIR__ . "/cron.php"); }

// Подключаем вывод дебаг-информации если нужно
if (DEBUG_MODE) { require_once(__DIR__ . "/debug.php"); } else { function debug() { return true; } }

// Создаём объекты всех определённых в конфиге баз данных
if (DB_CONNECTIONS) { A5::create_db_connections(); }