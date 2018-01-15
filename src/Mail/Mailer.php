<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Hail\Mail;

use Hail\Facade\Request;
use Hail\Mail\Exception\SmtpException;
use Hail\Util\SafeStorageTrait;

/**
 * Sends emails via the SMTP server.
 */
class Mailer
{
    use SafeStorageTrait;

	/** @var resource */
	private $connection;

	/** @var string */
	private $host;

	/** @var int */
	private $port;

	/** @var string */
	private $username;

	/** @var string ssl | tls | (empty) */
	private $secure;

	/** @var int */
	private $timeout;

	/** @var resource */
	private $context;

	/** @var bool */
	private $persistent;

    /** @var string */
	private $clientHost;

	public function __construct(array $options = [])
	{
		if (isset($options['host'])) {
			$this->host = $options['host'];
			$this->port = isset($options['port']) ? (int) $options['port'] : null;
		} else {
			$this->host = \ini_get('SMTP');
			$this->port = (int) \ini_get('smtp_port');
		}
		$this->username = $options['username'] ?? '';
        $this->setPassword($options['password'] ?? '');
		$this->secure = $options['secure'] ?? '';
		$this->timeout = isset($options['timeout']) ? (int) $options['timeout'] : 20;
		$this->context = isset($options['context']) ? \stream_context_create($options['context']) : \stream_context_get_default();
		if (!$this->port) {
			$this->port = $this->secure === 'ssl' ? 465 : 25;
		}
		$this->persistent = !empty($options['persistent']);

        if (isset($options['clientHost'])) {
			$this->clientHost = $options['clientHost'];
		} else {
            $host = Request::server('host');
			$this->clientHost = $host && \preg_match('#^[\w.-]+\z#', $host) ? $host : 'localhost';
		}
	}

	/**
	 * Sends email.
	 *
	 * @throws SmtpException
	 */
	public function send(Message $mail): void
	{
        $tmp = clone $mail;
		$tmp->setHeader('Bcc', null);
		$data = $tmp->generateMessage();

		try {
			if (!$this->connection) {
				$this->connect();
			}

			if (($from = $mail->getHeader('Return-Path'))
				|| ($from = \key($mail->getHeader('From')))
			) {
				$this->write("MAIL FROM:<$from>", 250);
			}

			foreach (\array_merge(
				         (array) $mail->getHeader('To'),
				         (array) $mail->getHeader('Cc'),
				         (array) $mail->getHeader('Bcc')
			         ) as $email => $name) {
				$this->write("RCPT TO:<$email>", [250, 251]);
			}

			$this->write('DATA', 354);
			$data = \preg_replace('#^\.#m', '..', $data);
			$this->write($data);
			$this->write('.', 250);

			if (!$this->persistent) {
				$this->write('QUIT', 221);
				$this->disconnect();
			}
		} catch (SmtpException $e) {
			if ($this->connection) {
				$this->disconnect();
			}
			throw $e;
		}
	}


	/**
	 * Connects and authenticates to SMTP server.
	 *
	 * @throws SmtpException
	 */
	protected function connect(): void
	{
		$this->connection = @\stream_socket_client( // @ is escalated to exception
			($this->secure === 'ssl' ? 'ssl://' : '') . $this->host . ':' . $this->port,
			$errno, $error, $this->timeout, STREAM_CLIENT_CONNECT, $this->context
		);
		if (!$this->connection) {
			throw new SmtpException($error, $errno);
		}
		\stream_set_timeout($this->connection, $this->timeout, 0);
		$this->read(); // greeting

		$this->write("EHLO {$this->clientHost}");
		$ehloResponse = $this->read();
		if ((int) $ehloResponse !== 250) {
			$this->write("HELO {$this->clientHost}", 250);
		}

		if ($this->secure === 'tls') {
			$this->write('STARTTLS', 220);
			if (!\stream_socket_enable_crypto($this->connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
				throw new SmtpException('Unable to connect via TLS.');
			}
			$this->write("EHLO {$this->clientHost}", 250);
		}

		$password = $this->getPassword();
		if ($this->username !== '' && $password !== '') {
			$authMechanisms = [];
			if (\preg_match('~^250[ -]AUTH (.*)$~im', $ehloResponse, $matches)) {
				$authMechanisms = \explode(' ', \trim($matches[1]));
			}

			if (\in_array('PLAIN', $authMechanisms, true)) {
				$credentials = $this->username . "\0" . $this->username . "\0" . $password;
				$this->write('AUTH PLAIN ' . \base64_encode($credentials), 235, 'PLAIN credentials');
			} else {
				$this->write('AUTH LOGIN', 334);
				$this->write(\base64_encode($this->username), 334, 'username');
				$this->write(\base64_encode($password), 235, 'password');
			}
		}
	}


	/**
	 * Disconnects from SMTP server.
	 */
	protected function disconnect(): void
	{
		\fclose($this->connection);
		$this->connection = null;
	}


	/**
	 * Writes data to server and checks response against expected code if some provided.
	 *
	 * @param  int|int[] $expectedCode
	 *
	 * @throws SmtpException
	 */
	protected function write(string $line, $expectedCode = null, string $message = null): void
	{
		\fwrite($this->connection, $line . Message::EOL);
		if ($expectedCode) {
			$response = $this->read();
			if (!\in_array((int) $response, (array) $expectedCode, true)) {
				throw new SmtpException('SMTP server did not accept ' . ($message ? $message : $line) . ' with error: ' . \trim($response));
			}
		}
	}


	/**
	 * Reads response from server.
	 */
	protected function read(): string
	{
		$s = '';
		while (($line = \fgets($this->connection, 1000)) != null) { // intentionally ==
			$s .= $line;
			if (isset($line[3]) && $line[3] === ' ') {
				break;
			}
		}

		return $s;
	}

}