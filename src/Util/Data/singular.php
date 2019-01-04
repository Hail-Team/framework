<?php
return [
    '/(s)tatuses$/i' => '\1\2tatus',
    '/^(.*)(menu)s$/i' => '\1\2',
    '/(quiz)zes$/i' => '\\1',
    '/(matr)ices$/i' => '\1ix',
    '/(vert|ind)ices$/i' => '\1ex',
    '/^(ox)en/i' => '\1',
    '/(alias)(es)*$/i' => '\1',
    '/(alumn|bacill|cact|foc|fung|nucle|radi|stimul|syllab|termin|viri?)i$/i' => '\1us',
    '/([ftw]ax)es/i' => '\1',
    '/(cris|ax|test)es$/i' => '\1is',
    '/(shoe)s$/i' => '\1',
    '/(o)es$/i' => '\1',
    '/ouses$/' => 'ouse',
    '/([^a])uses$/' => '\1us',
    '/([m|l])ice$/i' => '\1ouse',
    '/(x|ch|ss|sh)es$/i' => '\1',
    '/(m)ovies$/i' => '\1\2ovie',
    '/(s)eries$/i' => '\1\2eries',
    '/([^aeiouy]|qu)ies$/i' => '\1y',
    '/(tive)s$/i' => '\1',
    '/(hive)s$/i' => '\1',
    '/(drive)s$/i' => '\1',
    '/([le])ves$/i' => '\1f',
    '/([^rfoa])ves$/i' => '\1fe',
    '/(^analy)ses$/i' => '\1sis',
    '/(analy|diagno|^ba|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i' => '\1\2sis',
    '/([ti])a$/i' => '\1um',
    '/(p)eople$/i' => '\1\2erson',
    '/(m)en$/i' => '\1an',
    '/(c)hildren$/i' => '\1\2hild',
    '/(n)ews$/i' => '\1\2ews',
    '/eaus$/' => 'eau',
    '/^(.*us)$/' => '\\1',
    '/s$/i' => '',
];