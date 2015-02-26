<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Util;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class StringUtil
{
    public static function parseValue($string)
    {
        switch ($string) {
            case '': return null;
            case 'null': return null;
            case 'true': return true;
            case 'false': return false;
        }

        if ($string === (string) ($int = (int) $string)) {
            return $int;
        }

        if ($string === (string) ($float = (int) $string)) {
            return $float;
        }

        $length = strlen($string);

        // Check for " or ' delimiters
        if ($length > 1) {
            $first = $string[0];
            $last = $string[$length - 1];

            if ($first === $last && ("'" === $first || '"' === $first)) {
                return substr($string, 1, -1);
            }
        }

        return $string;
    }

    public static function formatValue($value, $quote = true)
    {
        if (null === $value) {
            return 'null';
        }

        if (true === $value) {
            return 'true';
        }

        if (false === $value) {
            return 'false';
        }

        if (is_string($value)) {
            $q = $quote ? '"' : '';

            return $q.$value.$q;
        }

        return (string) $value;
    }

    private function __construct() {}
}
