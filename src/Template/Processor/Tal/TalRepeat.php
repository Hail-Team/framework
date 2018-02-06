<?php
namespace Hail\Template\Processor\Tal;

use Hail\Template\Tokenizer\Token\Element;
use Hail\Template\Processor\Processor;
use Hail\Template\Processor\ProcessorInterface;

final class TalRepeat implements ProcessorInterface
{
    public static function process(Element $element): bool
    {
        $expression = $element->getAttribute('tal:repeat');
        if ($expression === null) {
            return false;
        }

        [$item, $lists] = \explode(' ', $expression, 2);

        $name = Syntax::variable($item);
        $lists = Syntax::variable($lists);

        $startCode = "\$__{$item}_num = 1;\n\$__{$item}_count = count($lists);\nforeach ($lists as \$__{$item}_key => $name) {";
        $endCode = "++\$__{$item}_num;\n}";

        Processor::before($element, $startCode);
        Processor::after($element, $endCode);

        $element->removeAttribute('tal:repeat');

        return false;
    }
}