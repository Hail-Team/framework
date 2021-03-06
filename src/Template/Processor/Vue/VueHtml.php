<?php
namespace Hail\Template\Processor\Vue;

use Hail\Template\Expression\Expression;
use Hail\Template\Tokenizer\Token\Element;
use Hail\Template\Processor\Processor;
use Hail\Template\Tokenizer\Token\TokenInterface;

final class VueHtml extends Processor
{
    public static function process(TokenInterface $element): bool
    {
        if (!$element instanceof Element) {
            return false;
        }

        $expression = $element->getAttribute('v-html');
        if ($expression === null) {
            return false;
        }

        $expression = Expression::parse($expression);

        self::text($element, 'echo ' . $expression);

        $element->removeAttribute('v-html');

        return false;
    }
}