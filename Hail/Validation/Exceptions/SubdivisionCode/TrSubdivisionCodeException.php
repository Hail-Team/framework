<?php

/*
 * This file is part of Respect/Validation.
 *
 * (c) Alexandre Gomes Gaigalas <alexandre@gaigalas.net>
 *
 * For the full copyright and license information, please view the "LICENSE.md"
 * file that was distributed with this source code.
 */

namespace Hail\Validation\Exceptions\SubdivisionCode;

use Hail\Validation\Exceptions\SubdivisionCodeException;

/**
 * Exception class for Turkey subdivision code.
 *
 * ISO 3166-1 alpha-2: TR
 */
class TrSubdivisionCodeException extends SubdivisionCodeException
{
    public static $defaultTemplates = [
        self::MODE_DEFAULT => [
            self::STANDARD => '{{name}} must be a subdivision code of Turkey',
        ],
        self::MODE_NEGATIVE => [
            self::STANDARD => '{{name}} must not be a subdivision code of Turkey',
        ],
    ];
}