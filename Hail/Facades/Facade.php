<?php
namespace Hail\Facades;

/**
 * Class Facade
 * @package Hail\Facades
 * @author  Hao Feng <flyinghail@msn.com>
 */
abstract class Facade
{
	/**
	 * Class alias name
	 *
	 * @var string
	 */
	protected static $name = '';

	/**
	 * @var bool
	 */
	protected static $inDI = true;

	/**
	 * The resolved object instances.
	 *
	 * @var array
	 */
	protected static $instances;


	/**
	 * Get the root object behind the facade.
	 *
	 * @return mixed
	 */
	public static function getInstance()
	{
		$name = static::class;

		return static::$instances[$name] ?? (static::$instances[$name] = static::instance());
	}

	/**
	 * Handle dynamic, static calls to the object.
	 *
	 * @param  string $method
	 * @param  array  $args
	 *
	 * @return mixed
	 * @throws \LogicException if instance() method not defined in sub class
	 */
	public static function __callStatic($method, $args)
	{
		$instance = static::$instances[static::class] ?? static::getInstance();
		return $instance->$method(...$args);
	}

	/**
	 * 由子类定义获取实例的具体实现
	 *
	 * @return object
	 * @throws \LogicException
	 */
	protected static function instance()
	{
		throw new \LogicException('Class should define instance() method');
	}

	public static function getName()
	{
		if (static::$name !== '') {
			return static::$name;
		}

		return strtolower(self::getClass());
	}

	public static function getClass()
	{
		$name = static::class;

		return substr($name, strrpos($name, '\\') + 1);
	}

	public static function inDI()
	{
		return static::$inDI;
	}

	public static function alias()
	{
		return static::class;
	}
}