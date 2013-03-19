<?php
include(__DIR__ . "/classes/_helpers.php");

$create_project_template = null;
$install_update_plugins = array();
$install_update_plugin_into = ".";

if (!isset($_SERVER["argv"][1]))
{
	echo "Usage: " . $_SERVER["argv"][0] . " [command <value>] ...\n";
	echo "Commands:\n";
	echo "\tcreate <project> - Create temlpate of application into specified folder <project>\n";
	echo "\t<install|update> <plugin> [into <folder>] - Install/Update plugin <plugin> into application folder <folder> or current folder\n";
	exit;
}
elseif ($_SERVER["argc"] == 2) { $create_project_template = $_SERVER["argv"][1]; }
else
{
	$args = $_SERVER["argv"];
	array_shift($args);
	for ($n = 0; $n < count($args); $n += 2)
	{
		$command = @$args[$n];
		$value = @$args[$n + 1];
		switch ($command)
		{
			case "create": $create_project_template = $value; break;
			case "install":
			case "update": $install_update_plugins[] = $value; break;
			case "into": $install_update_plugin_into = $value; break;
			default: die("ERROR: Unknown command '" . $command . "'\n"); break;
		}
	}
}

if ($create_project_template !== null) { A5_APP_Template::create($create_project_template); }
foreach ($install_update_plugins as $plugin_name) { A5_APP_Plugin::install($plugin_name, $install_update_plugin_into); }

class A5_APP_Utils
{
	static function copy($src_dir, $dst_dir, $tail_dir = null)
	{
		$full_src_dir = $src_dir . ($tail_dir === null ? null : "/" . $tail_dir);
		$full_dst_dir = $dst_dir . ($tail_dir === null ? null : "/" . $tail_dir);

		$dh = @opendir($full_src_dir) or die("Cannot open " . $full_src_dir . ": " . @$php_errormsg);
		while (false !== $file = readdir($dh))
		{
			if ($file == "." || $file == "..") { continue; }
			if (in_array($file, array("CVS", ".svn", ".git"))) { continue; }

			$src_file = $full_src_dir . "/" . $file;
			$dst_file = $full_dst_dir . "/" . $file;

			if (is_dir($src_file))
			{
				if (!is_dir($dst_file))
				{
					echo "creating " . $dst_file . ": ";
					@mkdir($dst_file) or die("failed: " . @$php_errormsg);
					echo "ok\n";
				}
				self::copy($src_dir, $dst_dir, ($tail_dir === null ? $file : $tail_dir . "/" . $file));
			}
			else
			{
				$is_changed = true;

				if (!file_exists($dst_file)) { echo "creating " . $dst_file . ": "; }
				else
				{
					if (md5(@file_get_contents($src_file)) == md5(file_get_contents($dst_file))) { $is_changed = false; }
					if ($is_changed) { echo "updating " . $dst_file . ": "; }
				}

				if ($is_changed)
				{
					@copy($src_file, $dst_file) or die("failed: " . @$php_errormsg);
					echo "ok\n";
				}
			}
		}
		closedir($dh);
	}

	static function rename($src, $dst)
	{
		if (file_exists($src))
		{
			if (file_exists($dst)) { self::delete($dst); }
			echo "renaming " . $src . " to " . $dst . ": ";
			if (is_dir(dirname($src) . "/.svn")) { @exec("svn rename " . $src . " " . $dst, $output, $result); if ($result) { die(); } }
			else { @rename($src, $dst) or die("failed: " . @$php_errormsg); }
			echo "ok\n";
		}
	}

	static function delete($dst)
	{
		if (file_exists($dst))
		{
			echo "deleting " . $dst . ": ";
			if (is_dir(dirname($dst) . "/.svn")) { @exec("svn del " . $dst, $output, $result); if ($result) { die(); } }
			else { @rmdirs($dst) or die("failed: " . @$php_errormsg); }
			echo "ok\n";
		}
	}

	static function clean_diff($src_dir, $dst_dir, $tail_dir = null)
	{
		$full_src_dir = $src_dir . ($tail_dir === null ? null : "/" . $tail_dir);
		$full_dst_dir = $dst_dir . ($tail_dir === null ? null : "/" . $tail_dir);

		$dh = @opendir($full_dst_dir) or die("Cannot open " . $full_src_dir . ": " . @$php_errormsg);
		while (false !== $file = readdir($dh))
		{
			if ($file == "." || $file == "..") { continue; }
			if (in_array($file, array("CVS", ".svn", ".git"))) { continue; }

			$src_file = $full_src_dir . "/" . $file;
			$dst_file = $full_dst_dir . "/" . $file;

			if
			(
				(is_dir($dst_file) && !is_dir($src_file))
				||
				(!is_dir($dst_file) && !file_exists($src_file))
			)
			{ self::delete($dst_file); }
			elseif (is_dir($src_file))
			{ self::clean_diff($src_dir, $dst_dir, ($tail_dir === null ? $file : $tail_dir . "/" . $file)); }
		}
		closedir($dh);
	}
}

class A5_APP_Environment
{
	static private $destination = null;
	static private $plugins_dir = null;
	static private $app_dir = null;
	static private $public_dir = null;

	static function setup($destination)
	{
		if (self::$destination !== null)
		{
			if ($destination != self::$destination) { die("environment already configured for destination:: " . $destination); }
			return true;
		}
		else { self::$destination = $destination; }

		$environment_file = normalize_path($destination . "/config/environment.php");
		if (!file_exists($environment_file))
		{
			$environment_file = normalize_path($destination . "/application/config/environment.php");
			if (!file_exists($environment_file))
			{
				$environment_file = normalize_path($destination . "/config/environment.php");
				die("environment file not found in: " . $environment_file);
			}
		}

		echo "connecting application $environment_file: ";
		if (is_readable($environment_file)) { require_once($environment_file); echo "ok\n"; }
		else { die("failed: not readable or not exists\n"); }

		if (!defined("CORE_DIR")) { die("detecting core folder: failed\n"); }

		echo "detecting application folder: ";
		if (defined("APP_DIR")) { echo APP_DIR . "\n"; } else { die("failed\n"); }

		echo "detecting public folder: ";
		if (defined("PUBLIC_DIR")) { echo PUBLIC_DIR . "\n"; } else { die("failed\n"); }

		self::$plugins_dir = normalize_path(CORE_DIR . "/plugins");
		self::$app_dir = normalize_path(APP_DIR);
		self::$public_dir = normalize_path(PUBLIC_DIR);
	}

	static function app_dir() { return self::$app_dir; }
	static function public_dir() { return self::$public_dir; }
	static function plugins_dir() { return self::$plugins_dir; }
}

class A5_APP_Template extends A5_APP_Utils
{
	static private $template_dir = null;
	static private $project_name = null;

	static function create($project_name)
	{
		if (file_exists($project_name))
		{
			if (!is_dir($project_name)) { die("ERROR: " . $project_name . " exists and it not a folder\n"); }
			else
			{
				$dh = @opendir($project_name) or die("Cannot open " . $project_name . ": " . @$php_errormsg);
				while (false !== $item = readdir($dh))
				{
					if ($item == "." || $item == "..") { continue; }
					echo "WARNING: " . $project_name . " folder is not empty!\n";
					echo "Do you want to continue? (Y/n): ";
					if (strtolower(trim(fgets(STDIN))) == "n") { exit; } else { break; }
				}
				closedir($dh);
			}
		}
		else { @mkdir($project_name) or die("ERROR: Cannot create " . $project_name . ": " . @$php_errormsg); }

		self::$project_name = $project_name;
		self::$template_dir = normalize_path(__DIR__ . "/app-template");
		self::copy(self::$template_dir, self::$project_name);
	}
}

class A5_APP_Plugin extends A5_APP_Utils
{
	static function install($plugin, $destination)
	{
		A5_APP_Environment::setup($destination);
		$install_script = normalize_path(A5_APP_Environment::plugins_dir() . "/" . $plugin . "/install.php");
		if (file_exists($install_script)) { require($install_script); } else { die("invalid plugin or plugin name: $plugin\n"); }
	}

	static function install_application($source, $prefix = null) { self::install_folder($source, APP_DIR, $prefix); }
	static function install_controllers($source, $prefix = null) { self::install_folder($source, CONTROLLERS_DIR, $prefix); }
	static function install_helpers($source, $prefix = null) { self::install_folder($source, HELPERS_DIR, $prefix); }
	static function install_layouts($source, $prefix = null) { self::install_folder($source, LAYOUTS_DIR, $prefix); }
	static function install_views($source, $prefix = null) { self::install_folder($source, VIEWS_DIR, $prefix); }
	static function install_public($source, $prefix = null) { self::install_folder($source, PUBLIC_DIR, $prefix); }

	static function rename_application($source, $destination, $prefix = null) { self::rename_folder($source, $destination, APP_DIR, $prefix); }
	static function rename_controllers($source, $destination, $prefix = null) { self::rename_folder($source, $destination, CONTROLLERS_DIR, $prefix); }
	static function rename_helpers($source, $destination, $prefix = null) { self::rename_folder($source, $destination, HELPERS_DIR, $prefix); }
	static function rename_layouts($source, $destination, $prefix = null) { self::rename_folder($source, $destination, LAYOUTS_DIR, $prefix); }
	static function rename_views($source, $destination, $prefix = null) { self::rename_folder($source, $destination, VIEWS_DIR, $prefix); }
	static function rename_public($source, $destination, $prefix = null) { self::rename_folder($source, $destination, PUBLIC_DIR, $prefix); }

	static function delete_application($destination, $prefix = null) { self::delete_folder($destination, APP_DIR, $prefix); }
	static function delete_controllers($destination, $prefix = null) { self::delete_folder($destination, CONTROLLERS_DIR, $prefix); }
	static function delete_helpers($destination, $prefix = null) { self::delete_folder($destination, HELPERS_DIR, $prefix); }
	static function delete_layouts($destination, $prefix = null) { self::delete_folder($destination, LAYOUTS_DIR, $prefix); }
	static function delete_views($destination, $prefix = null) { self::delete_folder($destination, VIEWS_DIR, $prefix); }
	static function delete_public($destination, $prefix = null) { self::delete_folder($destination, PUBLIC_DIR, $prefix); }

	static function clean_application($source, $prefix = null) { self::clean_folder($source, APP_DIR, $prefix); }
	static function clean_controllers($source, $prefix = null) { self::clean_folder($source, CONTROLLERS_DIR, $prefix); }
	static function clean_helpers($source, $prefix = null) { self::clean_folder($source, HELPERS_DIR, $prefix); }
	static function clean_layouts($source, $prefix = null) { self::clean_folder($source, LAYOUTS_DIR, $prefix); }
	static function clean_views($source, $prefix = null) { self::clean_folder($source, VIEWS_DIR, $prefix); }
	static function clean_public($source, $prefix = null) { self::clean_folder($source, PUBLIC_DIR, $prefix); }

	static function clean_folder($source, $destination, $prefix = null)
	{
		$destination .= ($prefix ? "/" . $prefix: null);
		if (!is_absolute_path($source)) { $source = normalize_path(CORE_DIR . "/" . $source); }
		self::clean_diff($source, $destination);
	}

	static function delete_folder($destination, $base_folder = null, $prefix = null)
	{
		$base_folder = $base_folder . ($prefix ? "/" . $prefix: null);
		$destination = ($base_folder ? $base_folder . "/" : null) . $destination;
		if (!is_absolute_path($destination)) { $destination = normalize_path(CORE_DIR . "/" . $destination); }
		self::delete($destination);
	}

	static function rename_folder($source, $destination, $base_folder = null, $prefix = null)
	{
		$base_folder = $base_folder . ($prefix ? "/" . $prefix: null);
		$source = ($base_folder ? $base_folder . "/" : null) . $source;
		$destination = ($base_folder ? $base_folder . "/" : null) . $destination;
		if (!is_absolute_path($source)) { $source = normalize_path(CORE_DIR . "/" . $source); }
		if (!is_absolute_path($destination)) { $destination = normalize_path(CORE_DIR . "/" . $destination); }
		self::rename($source, $destination);
	}

	static function install_folder($source, $destination, $prefix = null)
	{
		if (!is_absolute_path($source)) { $source = normalize_path(CORE_DIR . "/" . $source); }
		$destination .= ($prefix ? "/" . $prefix: null);
		if (file_exists($destination))
		{
			if (!is_dir($destination))
			{ die("ERROR: " . $destination . " exists and it not a folder\n"); }
		}
		else
		{
			echo "creating " . $destination . ": ";
			@mkdirs($destination) or die("failed: " . @$GLOBALS["php_errormsg"]);
			echo "ok\n";
		}
		self::copy($source, $destination, null);
	}
}