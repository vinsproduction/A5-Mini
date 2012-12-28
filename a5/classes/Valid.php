<?php
class Valid
{
	static function date($indate) { return (Date::format($indate) !== false); }
	static function float($val) { return preg_match("/^(?:\+|-)?\d+(?:\.\d+(?:[eE][+-]\d+)?)?$/u", @trim($val)); }
	static function age($val) { return preg_match("/^\+?\d+$/u", @trim($val)); }
	static function integer($val) { return preg_match("/^(?:\+|-)?\d+$/u", @trim($val)); }
	static function email($val) { return preg_match("/^[a-zA-Z0-9_]+[a-zA-Z0-9_-]*(\.[a-zA-Z0-9_][a-zA-Z0-9_-]*)*@[a-zA-Z0-9_][a-zA-Z0-9_-]*\.([a-zA-Z0-9_][a-zA-Z0-9_-]*\.)*[a-zA-Z0-9_]{2,}$/su", @trim($val)); }
}