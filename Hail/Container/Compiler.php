<?php

namespace Hail\Container;

use Hail\Config;

/**
 * This class implements a simple dependency injection container.
 */
class Compiler
{
	public static $file = RUNTIME_PATH . 'Container.php';

	protected $config;

	protected $points = [];
	protected $methods = [];

	public function __construct()
	{
		$config = new Config();

		$this->config = $config->get('container');
	}


	public function compile()
	{
		$this->parseParameters();
		$this->parseServices();

		$code = "<?php\n";
		$code .= "class Container extends Hail\\Container\\Container\n";
		$code .= "{\n";

		$code .= "\tprotected static \$entryPoints = [\n";
		foreach ($this->points as $k => $v) {
			$code .= "\t\t" . $this->classname($k) . " => $v,\n";
		}
		$code .= "\t];\n\n";
		$code .= "\tpublic function get(\$name)\n";
		$code .= "\t{\n";
		$code .= "\t\tif (isset(\$this->active[\$name])) {\n";
		$code .= "\t\t\treturn \$this->values[\$name];\n";
		$code .= "\t\t}\n\n";
		$code .= "\t\tif (isset(static::\$entryPoints[\$name])) {\n";
		$code .= "\t\t\treturn \$this->{static::\$entryPoints[\$name]}();\n";
		$code .= "\t\t}\n\n";
		$code .= "\t\treturn parent::get(\$name);\n";
		$code .= "\t}\n\n";
		$code .= implode("\n\n", $this->methods);
		$code .= '}';

		file_put_contents(static::$file, $code);
	}

	protected function parseParameters()
	{
		$parameters = $this->config['parameters'] ?? [];

		foreach ($parameters as $k => $v) {
			$this->points[$k] = $this->methodName($k);
			$this->toMethod($k, $this->parseStr($v));
		}
	}

	protected function parseServices()
	{
		$services = $this->config['services'] ?? [];

		foreach ($services as $k => $v) {
			if (is_string($v) && $v[0] === '@') {
				$this->points[$k] = $this->methodName($k);
				$this->toMethod($k, $this->parseStr($v));

				continue;
			}

			if (!is_array($v)) {
				continue;
			}

			if (isset($v['alias'])) {
				$this->points[$k] = $this->methodName($k);
				$this->toMethod($k, $this->parseRef($v['alias']));
				continue;
			}

			$arguments = '';
			if (isset($v['arguments'])) {
				$arguments = $this->parseArguments($v['arguments']);
			}

			$suffix = array_merge(
				$this->parseProperty($v['property'] ?? []),
				$this->parseCalls($v['calls'] ?? [])
			);

			$class = $v['class'] ?? $k;

			$classRef = $v['classRef'] ?? true;

			if ($classRef) {
				if ($k !== $class) {
					$this->points[$k] = $this->methodName($k);
					$this->toMethod($k, $this->parseRef($class));
				}
			}

			$this->points[$class] = $this->methodName($class);

			if (isset($v['factory'])) {
				$factory = $v['factory'];
				if (is_array($v['factory'])) {
					[$class, $method] = $v['factory'];
					$factory = "{$class}::{$method}";
				}

				if (!is_string($factory)) {
					continue;
				}

				if (
					strpos($factory, ':') !== false &&
					strpos($factory, '::') === false
				) {
					[$ref, $method] = explode(':', $factory);
					$factory = $this->parseRef($ref) . "->{$method}";
				}
			} else {
				$factory = "new {$class}";
			}

			$this->toMethod($class, "{$factory}($arguments)", $suffix);
		}
	}

	protected function parseArguments(array $args): string
	{
		return implode(', ', array_map([$this, 'parseStr'], $args));
	}

	protected function parseProperty(array $props): array
	{
		if ($props === []) {
			return [];
		}

		$return = [];
		foreach ($props as $k => $v) {
			$return[] = $k . ' = ' . $this->parseStr($v);
		}

		return $return;
	}

	protected function parseCalls(array $calls): array
	{
		if ($calls === []) {
			return [];
		}

		$return = [];
		foreach ($calls as $v) {
			[$method, $args] = $v;
			$args = $this->parseArguments($args);
			$return[] = $method . '(' . $args . ')';
		}

		return $return;
	}

	protected function parseStr($str)
	{
		if (is_string($str)) {
			if (strpos($str, 'CONFIG.') === 0) {
				$str = var_export(substr($str, 7), true);

				return $this->parseRef('config') . '->get(' . $str . ')';
			}

			if (isset($str[0]) && $str[0] === '@') {
				$str = substr($str, 1);
				if ($str === '') {
					$str = '@';
				} elseif ($str[0] !== '@') {
					return $this->parseRef($str);
				}
			}
		}

		return var_export($str, true);
	}

	protected function parseRef($name)
	{
		return '$this->get(' . $this->classname($name) . ')';
	}

	protected function isClassname($name)
	{
		return class_exists($name) && strtoupper($name[0]) === $name[0];
	}

	protected function classname($name)
	{
		if ($name[0] === '\\') {
			$name = ltrim($name, '\\');
		}

		if ($this->isClassname($name)) {
			return "$name::class";
		}

		return var_export($name, true);
	}

	protected function methodName($string, $quote = true)
	{
		if ($string[0] === '\\') {
			$string = ltrim($string, '\\');
		}

		$name = 'HAIL_';
		if ($this->isClassname($string)) {
			$name .= 'CLASS__';
		} else {
			$name .= 'PARAM__';
		}

		$name .= str_replace(['\\', '.'], '__', $string);

		return $quote ? "'$name'" : $name;
	}

	protected function toMethod($name, $return, array $suffix = [])
	{
		$method = $this->methodName($name, false);

		$name = $this->classname($name);

		$code = "\tprotected function {$method}() {\n";
		if ($suffix !== []) {
			$code .= "\t\t\$object = $return;\n";
			$code .= implode(";\n\t\t\$objcet->", $suffix) . ";\n";
			$return = '$object';
		}

		$code .= "\t\t\$this->active[$name] = true;\n";
		$code .= "\t\treturn \$this->values[$name] = $return;\n";
		$code .= "\t}";

		$this->methods[] = $code;
	}
}
