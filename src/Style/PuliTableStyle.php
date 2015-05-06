<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Style;

use Webmozart\Console\Api\Formatter\Style;
use Webmozart\Console\UI\Style\BorderStyle;
use Webmozart\Console\UI\Style\TableStyle;

/**
 * Contains the default table styles of Puli.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class PuliTableStyle extends TableStyle
{
    /**
     * @var TableStyle
     */
    private static $borderless;

    /**
     * A borderless style.
     *
     * @return TableStyle The style.
     */
    public static function borderless()
    {
        if (!self::$borderless) {
            $borderStyle = BorderStyle::none();
            $borderStyle->setLineVCChar('  ');

            self::$borderless = new static();
            self::$borderless->setBorderStyle($borderStyle);
            self::$borderless->setHeaderCellStyle(Style::noTag()->bold());
        }

        return clone self::$borderless;
    }
}
