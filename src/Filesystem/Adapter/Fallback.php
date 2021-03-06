<?php

namespace Hail\Filesystem\Adapter;

use Hail\Filesystem\AdapterInterface;
use Hail\Filesystem\Exception\FileNotFoundException;
use Hail\Filesystem\Filesystem;
use InvalidArgumentException;

class Fallback implements AdapterInterface
{
	/**
	 * @var AdapterInterface
	 */
	protected $mainAdapter;

	/**
	 * @var AdapterInterface
	 */
	protected $fallback;

	/**
	 * @var bool
	 */
	protected $forceCopyOnMain;

	/**
	 * Constructor.
	 *
	 * @param array $config
	 * @throws InvalidArgumentException
	 */
	public function __construct(array $config)
	{
		if (!isset($config['main'], $config['fallback'])) {
			throw new InvalidArgumentException('Fallback file system not defined');
		}

		$this->mainAdapter = new Filesystem($config['main']);
		$this->fallback = new Filesystem($config['fallback']);

		$this->forceCopyOnMain = (bool) ($config['forceCopyOnMain'] ?? false);
	}

	/**
	 * Returns the main adapter.
	 *
	 * @return AdapterInterface
	 */
	public function getMainAdapter()
	{
		return $this->mainAdapter;
	}

	/**
	 * Returns the fallback adapter.
	 *
	 * @return AdapterInterface
	 */
	public function getFallbackAdapter()
	{
		return $this->fallback;
	}

	/**
	 * {@inheritdoc}
	 */
	public function write($path, $contents, array $config)
	{
		return $this->mainAdapter->write($path, $contents, $config);
	}

	/**
	 * {@inheritdoc}
	 */
	public function writeStream($path, $resource, array $config)
	{
		return $this->mainAdapter->writeStream($path, $resource, $config);
	}

	/**
	 * {@inheritdoc}
	 */
	public function update($path, $contents, array $config)
	{
		// This is done to allow "append" mode in the underlying main adapter
		if (!$this->mainAdapter->has($path) && $this->fallback->has($path)) {
			$this->portFromFallback($path, $path);
		}

		return $this->mainAdapter->update($path, $contents, $config);
	}

	/**
	 * {@inheritdoc}
	 */
	public function updateStream($path, $resource, array $config)
	{
		if ($this->mainAdapter->has($path)) {
			return $this->mainAdapter->updateStream($path, $resource, $config);
		}

        // TODO: Review, is this necessary?
        return $this->mainAdapter->writeStream($path, $resource, $config);
	}

	/**
	 * {@inheritdoc}
	 */
	public function rename($path, $newpath)
	{
		if ($this->mainAdapter->has($path)) {
			return $this->mainAdapter->rename($path, $newpath);
		}

		if (false !== $this->portFromFallback($path, $newpath)) {
			return $this->fallback->delete($path);
		}

		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function copy($path, $newpath)
	{
		if ($this->mainAdapter->has($path)) {
			return $this->mainAdapter->copy($path, $newpath);
		}

		if ($this->fallback->has($path)) {
			return $this->portFromFallback($path, $newpath);
		}

		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete($path)
	{
		$found = false;

		if ($this->fallback->has($path)) {
			$fallbackResult = $this->fallback->delete($path);
			$found = true;
		} else {
			$fallbackResult = true;
		}

		if ($this->mainAdapter->has($path)) {
			$mainResult = $this->mainAdapter->delete($path);
			$found = true;
		} else {
			$mainResult = true;
		}

		if (!$found) {
			throw new FileNotFoundException($path);
		}

		return ($fallbackResult && $mainResult);
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteDir($dirname)
	{
		$found = false;

		if ($this->fallback->has($dirname)) {
			$fallbackResult = $this->fallback->deleteDir($dirname);
			$found = true;
		} else {
			$fallbackResult = true;
		}

		if ($this->mainAdapter->has($dirname)) {
			$mainResult = $this->mainAdapter->deleteDir($dirname);
			$found = true;
		} else {
			$mainResult = true;
		}

		if (!$found) {
			throw new FileNotFoundException($dirname);
		}

		return ($fallbackResult && $mainResult);
	}

	/**
	 * {@inheritdoc}
	 */
	public function createDir($dirname, array $config)
	{
		return $this->mainAdapter->createDir($dirname, $config);
	}

	/**
	 * {@inheritdoc}
	 */
	public function setVisibility($path, $visibility)
	{
		if ($this->mainAdapter->has($path)) {
			return $this->mainAdapter->setVisibility($path, $visibility);
		}

		return $this->fallback->setVisibility($path, $visibility);
	}

	/**
	 * {@inheritdoc}
	 */
	public function has($path)
	{
		return $this->mainAdapter->has($path) || $this->fallback->has($path);
	}

	/**
	 * {@inheritdoc}
	 */
	public function read($path)
	{
		$result = $this->mainAdapter->read($path);

		if (false !== $result) {
			return $result;
		}

		$result = $this->fallback->read($path);

		if (false !== $result && $this->forceCopyOnMain) {
			$this->mainAdapter->write($path, $result['contents'], []);
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function readStream($path)
	{
		$result = $this->mainAdapter->readStream($path);

		if (false !== $result) {
			return $result;
		}

		$result = $this->fallback->readStream($path);

		if (false !== $result && $this->forceCopyOnMain) {
			$this->writeStream($path, $result['stream'], []);
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function listContents($directory = '', $recursive = false)
	{
		$tmpResult = $this->mainAdapter->listContents($directory, $recursive);

		$inverseRef = [];
		foreach ($tmpResult as $index => $mainContent) {
			$inverseRef[$mainContent['path']] = $index;
		}

		$fallbackContents = $this->fallback->listContents($directory, $recursive);
		foreach ($fallbackContents as $fallbackContent) {
			if (!isset($inverseRef[$fallbackContent['path']])) {
				$tmpResult[] = $fallbackContent;
			}
		}

		return $tmpResult;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMetadata($path)
	{
		if ($this->mainAdapter->has($path)) {
			return $this->mainAdapter->getMetadata($path);
		}

		return $this->fallback->getMetadata($path);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSize($path)
	{
		if ($this->mainAdapter->has($path)) {
			return $this->mainAdapter->getSize($path);
		}

		return $this->fallback->getSize($path);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMimetype($path)
	{
		if ($this->mainAdapter->has($path)) {
			return $this->mainAdapter->getMimetype($path);
		}

		return $this->fallback->getMimetype($path);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getTimestamp($path)
	{
		if ($this->mainAdapter->has($path)) {
			return $this->mainAdapter->getTimestamp($path);
		}

		return $this->fallback->getTimestamp($path);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getVisibility($path)
	{
		if ($this->mainAdapter->has($path)) {
			return $this->mainAdapter->getVisibility($path);
		}

		return $this->fallback->getVisibility($path);
	}

	/**
	 * Copies a resource accessible through the fallback adapter to the filesystem abstracted with the main adapter.
	 *
	 * @param $path
	 * @return bool
	 */
	private function portFromFallback($path, $newpath)
	{
		$buffer = $this->fallback->readStream($path);

		if (false === $buffer) {
			return false;
		}

		$result = $this->mainAdapter->writeStream($newpath, $buffer['stream'], []);

		if (\is_resource($buffer['stream'])) {
			\fclose($buffer['stream']);
		}

		return (false !== $result);
	}
}

