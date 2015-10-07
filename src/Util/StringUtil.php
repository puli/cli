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
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class StringUtil
{
    /**
     * Returns the short class name for a fully-qualified class name.
     *
     * @param string $className The fully-qualified class name.
     *
     * @return string The short class name.
     */
    public static function getShortClassName($className)
    {
        if (false !== ($pos = strrpos($className, '\\'))) {
            return substr($className, $pos + 1);
        }

        return $className;
    }

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

        // Check for " or ' delimiters
        // https://3v4l.org/5u0AU
        if (preg_match('/^(["\']).*\1$/m', $string)) {
            return substr($string, 1, -1);
        }

        return $string;
    }

    public static function formatValue($value, $quote = true)
    {
        if (null === $value) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_string($value)) {
            $q = $quote ? '"' : '';

            return $q.$value.$q;
        }

        return (string) $value;
    }

    private function __construct()
    {
    }
}
