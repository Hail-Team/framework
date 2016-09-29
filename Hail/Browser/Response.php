<?php

namespace Hail\Browser;

class Response
{
	private $code;
	private $rawBody;
	private $body;
	private $headers;
	private $info;

	/**
	 * @param array $info cURL info
	 * @param string $rawBody the raw body of the cURL response
	 * @param string $headers raw header string from cURL response
	 * @param array $jsonArgs arguments to pass to json_decode function
	 */
	public function __construct($info, $rawBody, $headers, $jsonArgs = [])
	{
		$this->info = $info;
		$this->code = $info['http_code'];
		$this->headers = $this->parseHeaders($headers);
		$this->rawBody = $rawBody;
		$this->body = $rawBody;

		// make sure raw_body is the first argument
		array_unshift($jsonArgs, $rawBody);

		$json = call_user_func_array('json_decode', $jsonArgs);

		if (json_last_error() === JSON_ERROR_NONE) {
			$this->body = $json;
		}
	}

	/**
	 * if PECL_HTTP is not available use a fall back function
	 *
	 * thanks to ricardovermeltfoort@gmail.com
	 * http://php.net/manual/en/function.http-parse-headers.php#112986
	 *
	 * @param string $raw_headers raw headers
	 *
	 * @return array
	 */
	private function parseHeaders($raw_headers)
	{
		if (function_exists('http_parse_headers')) {
			return http_parse_headers($raw_headers);
		} else {
			$key = '';
			$headers = [];

			foreach (explode("\n", $raw_headers) as $i => $h) {
				$h = explode(':', $h, 2);

				if (isset($h[1])) {
					if (!isset($headers[$h[0]])) {
						$headers[$h[0]] = trim($h[1]);
					} elseif (is_array($headers[$h[0]])) {
						$headers[$h[0]] = array_merge($headers[$h[0]], [trim($h[1])]);
					} else {
						$headers[$h[0]] = array_merge([$headers[$h[0]]], [trim($h[1])]);
					}

					$key = $h[0];
				} else {
					if (0 === strpos($h[0], "\t")) {
						$headers[$key] .= "\r\n\t" . trim($h[0]);
					} else if (!$key) {
						$headers[0] = trim($h[0]);
					}
				}
			}

			return $headers;
		}
	}

	public function getRaw()
	{
		return $this->rawBody;
	}

	public function getBody()
	{
		return $this->body;
	}

	public function getHeader()
	{
		return $this->headers;
	}

	public function getCode()
	{
		return $this->code;
	}

	public function getInfo()
	{
		return $this->info;
	}
}