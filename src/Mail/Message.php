<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Hail\Mail;

use Hail\Exception\FileNotFoundException;
use Hail\Util\{
    Exception\RegexpException, MimeType, Generators
};


/**
 * Mail provides functionality to compose and send both text and MIME-compliant multipart email messages.
 *
 * @property   string $subject
 * @property   string $htmlBody
 */
class Message extends MimePart
{
    /** Priority */
    public const HIGH = 1,
        NORMAL = 3,
        LOW = 5;

    /** @var array */
    public static $defaultHeaders = [
        'MIME-Version' => '1.0',
        'X-Mailer' => 'Hail Framework',
    ];

    /** @var array */
    private $attachments = [];

    /** @var array */
    private $inlines = [];

    /** @var string */
    private $htmlBody = '';


    public function __construct()
    {
        foreach (static::$defaultHeaders as $name => $value) {
            $this->setHeader($name, $value);
        }
        $this->setHeader('Date', \date('r'));
    }


    /**
     * Sets the sender of the message. Email or format "John Doe" <doe@example.com>
     *
     * @param string $email
     * @param string $name
     *
     * @return self
     */
    public function setFrom(string $email, string $name = null): self
    {
        $this->setHeader('From', $this->formatEmail($email, $name));

        return $this;
    }


    /**
     * Returns the sender of the message.
     */
    public function getFrom(): array
    {
        return $this->getHeader('From');
    }


    /**
     * Adds the reply-to address. Email or format "John Doe" <doe@example.com>
     *
     * @param string $email
     * @param string $name
     *
     * @return self
     */
    public function addReplyTo(string $email, string $name = null): self
    {
        $this->setHeader('Reply-To', $this->formatEmail($email, $name), true);

        return $this;
    }


    /**
     * Sets the subject of the message.
     *
     * @param string $subject
     *
     * @return self
     */
    public function setSubject(string $subject): self
    {
        $this->setHeader('Subject', $subject);

        return $this;
    }


    /**
     * Returns the subject of the message.
     */
    public function getSubject(): ?string
    {
        return $this->getHeader('Subject');
    }


    /**
     * Adds email recipient. Email or format "John Doe" <doe@example.com>
     *
     * @param string      $email
     * @param string|null $name
     *
     * @return self
     */
    public function addTo(string $email, string $name = null): self // addRecipient()
    {
        $this->setHeader('To', $this->formatEmail($email, $name), true);

        return $this;
    }


    /**
     * Adds carbon copy email recipient. Email or format "John Doe" <doe@example.com>
     *
     * @param string      $email
     * @param string|null $name
     *
     * @return self
     */
    public function addCc(string $email, string $name = null): self
    {
        $this->setHeader('Cc', $this->formatEmail($email, $name), true);

        return $this;
    }


    /**
     * Adds blind carbon copy email recipient. Email or format "John Doe" <doe@example.com>
     *
     * @param string      $email
     * @param string|null $name
     *
     * @return self
     */
    public function addBcc(string $email, string $name = null): self
    {
        $this->setHeader('Bcc', $this->formatEmail($email, $name), true);

        return $this;
    }


    /**
     * Formats recipient email.
     *
     * @param string      $email
     * @param string|null $name
     *
     * @return array
     */
    private function formatEmail(string $email, string $name = null): array
    {
        if (!$name && \preg_match('#^(.+) +<(.*)>\z#', $email, $matches)) {
            return [$matches[2] => $matches[1]];
        }

        return [$email => $name];
    }


    /**
     * Sets the Return-Path header of the message.
     *
     * @param string $email
     *
     * @return static
     */
    public function setReturnPath(string $email)
    {
        $this->setHeader('Return-Path', $email);

        return $this;
    }


    /**
     * Returns the Return-Path header.
     */
    public function getReturnPath(): string
    {
        return $this->getHeader('Return-Path');
    }


    /**
     * Sets email priority.
     *
     * @param int $priority
     *
     * @return static
     */
    public function setPriority(int $priority)
    {
        $this->setHeader('X-Priority', $priority);

        return $this;
    }


    /**
     * Returns email priority.
     */
    public function getPriority(): int
    {
        return $this->getHeader('X-Priority');
    }


    /**
     * Sets HTML body.
     *
     * @param string      $html
     * @param string|null $basePath
     *
     * @return $this
     *
     * @throws RegexpException
     */
    public function setHtmlBody(string $html, string $basePath = null)
    {
        if ($basePath) {
            $cids = [];
            $matches = \Strings::matchAll(
                $html,
                '#
					(<img[^<>]*\s src\s*=\s*
					|<body[^<>]*\s background\s*=\s*
					|<[^<>]+\s style\s*=\s* ["\'][^"\'>]+[:\s] url\(
					|<style[^>]*>[^<]+ [:\s] url\()
					(["\']?)(?![a-z]+:|[/\\#])([^"\'>)\s]+)
					|\[\[ ([\w()+./@~-]+) \]\]
				#ix',
                PREG_OFFSET_CAPTURE
            );
            foreach (\array_reverse($matches) as $m) {
                $file = \rtrim($basePath, '/\\') . '/' . (isset($m[4]) ? $m[4][0] : \urldecode($m[3][0]));
                if (!isset($cids[$file])) {
                    $cids[$file] = \substr($this->addEmbeddedFile($file)->getHeader('Content-ID'), 1, -1);
                }
                $html = \substr_replace($html,
                    "{$m[1][0]}{$m[2][0]}cid:{$cids[$file]}",
                    $m[0][1], \strlen($m[0][0])
                );
            }
        }

        if ($this->getSubject() == null) { // intentionally ==
            $html = \Strings::replace($html, '#<title>(.+?)</title>#is', function (array $m): void {
                $this->setSubject(\html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
            });
        }

        $this->htmlBody = \ltrim(\str_replace("\r", '', $html), "\n");

        if ($html !== '' && $this->getBody() === '') {
            $this->setBody($this->buildText($html));
        }

        return $this;
    }


    /**
     * Gets HTML body.
     */
    public function getHtmlBody(): string
    {
        return $this->htmlBody;
    }


    /**
     * Adds embedded file.
     *
     * @param string      $file
     * @param string|null $content
     * @param string|null $contentType
     *
     * @return MimePart
     */
    public function addEmbeddedFile(string $file, string $content = null, string $contentType = null): MimePart
    {
        return $this->inlines[$file] = $this->createAttachment($file, $content, $contentType, 'inline')
            ->setHeader('Content-ID', $this->getRandomId());
    }


    /**
     * Adds inlined Mime Part.
     *
     * @param MimePart $part
     *
     * @return $this
     */
    public function addInlinePart(MimePart $part)
    {
        $this->inlines[] = $part;

        return $this;
    }


    /**
     * Adds attachment.
     *
     * @param string      $file
     * @param string|null $content
     * @param string|null $contentType
     *
     * @return MimePart
     */
    public function addAttachment(string $file, string $content = null, string $contentType = null): MimePart
    {
        return $this->attachments[] = $this->createAttachment($file, $content, $contentType, 'attachment');
    }


    /**
     * Gets all email attachments.
     *
     * @return MimePart[]
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }


    /**
     * Creates file MIME part.
     *
     * @param string      $file
     * @param string|null $content
     * @param string|null $contentType
     * @param string      $disposition
     *
     * @return MimePart
     */
    private function createAttachment(
        string $file,
        string $content = null,
        string $contentType = null,
        string $disposition
    ): MimePart {
        $part = new MimePart;
        if ($content === null) {
            $content = @\file_get_contents($file); // @ is escalated to exception
            if ($content === false) {
                throw new FileNotFoundException("Unable to read file '$file'.");
            }
        }

        if (!$contentType) {
            $contentType = MimeType::getMimeTypeByContent($content);
        }

        if (!\strcasecmp($contentType, 'message/rfc822')) { // not allowed for attached files
            $contentType = 'application/octet-stream';
        } elseif (!\strcasecmp($contentType, 'image/svg')) { // Troublesome for some mailers...
            $contentType = 'image/svg+xml';
        }

        $part->setBody($content);
        $part->setContentType($contentType);
        $part->setEncoding(\preg_match('#(multipart|message)/#A', $contentType) ?
            self::ENCODING_8BIT : self::ENCODING_BASE64);
        $part->setHeader('Content-Disposition',
            $disposition . '; filename="' . \Strings::fixEncoding(\basename($file)) . '"');

        return $part;
    }


    /********************* building and sending ****************d*g**/


    /**
     * Returns encoded message.
     */
    public function generateMessage(): string
    {
        return $this->build()->getEncodedMessage();
    }


    /**
     * Builds email. Does not modify itself, but returns a new object.
     *
     * @return static
     */
    protected function build()
    {
        $mail = clone $this;
        $mail->setHeader('Message-ID', $this->getRandomId());

        $cursor = $mail;
        if ($mail->attachments) {
            $tmp = $cursor->setContentType('multipart/mixed');
            $cursor = $cursor->addPart();
            foreach ($mail->attachments as $value) {
                $tmp->addPart($value);
            }
        }

        if ($mail->htmlBody !== '') {
            $tmp = $cursor->setContentType('multipart/alternative');
            $cursor = $cursor->addPart();
            $alt = $tmp->addPart();
            if ($mail->inlines) {
                $tmp = $alt->setContentType('multipart/related');
                $alt = $alt->addPart();
                foreach ($mail->inlines as $value) {
                    $tmp->addPart($value);
                }
            }
            $alt->setContentType('text/html', 'UTF-8')
                ->setEncoding(\preg_match('#[^\n]{990}#', $mail->htmlBody)
                    ? self::ENCODING_QUOTED_PRINTABLE
                    : (\preg_match('#[\x80-\xFF]#', $mail->htmlBody) ? self::ENCODING_8BIT : self::ENCODING_7BIT))
                ->setBody($mail->htmlBody);
        }

        $text = $mail->getBody();
        $mail->setBody('');
        $cursor->setContentType('text/plain', 'UTF-8')
            ->setEncoding(\preg_match('#[^\n]{990}#', $text)
                ? self::ENCODING_QUOTED_PRINTABLE
                : (\preg_match('#[\x80-\xFF]#', $text) ? self::ENCODING_8BIT : self::ENCODING_7BIT))
            ->setBody($text);

        return $mail;
    }


    /**
     * Builds text content.
     *
     * @param string $html
     *
     * @return string
     * @throws RegexpException
     */
    protected function buildText(string $html): string
    {
        $text = \Strings::replace($html, [
            '#<(style|script|head).*</\\1>#Uis' => '',
            '#<t[dh][ >]#i' => ' $0',
            '#<a\s[^>]*href=(?|"([^"]+)"|\'([^\']+)\')[^>]*>(.*?)</a>#is' => '$2 &lt;$1&gt;',
            '#[\r\n]+#' => ' ',
            '#<(/?p|/?h\d|li|br|/tr)[ >/]#i' => "\n$0",
        ]);
        $text = \html_entity_decode(\strip_tags($text), ENT_QUOTES, 'UTF-8');
        $text = \Strings::replace($text, '#[ \t]+#', ' ');

        return \trim($text);
    }


    private function getRandomId(): string
    {
        return '<' . Generators::random() . '@'
            . \preg_replace('#[^\w.-]+#', '', $_SERVER['HTTP_HOST'] ?? \php_uname('n'))
            . '>';
    }
}
