<?php

/*
 * This file is part of the webmozart/gitty package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webmozart\Gitty\Input;

/**
 * An input definition with a tweaked synopsis.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InputDefinition extends \Symfony\Component\Console\Input\InputDefinition
{
    /**
     * {@inheritdoc}
     */
    public function getSynopsis()
    {
        $elements = array();

        foreach ($this->getOptions() as $option) {
            if ($option->isValueRequired()) {
                $format = '--%s="..."';
            } elseif ($option->isValueOptional()) {
                $format = '--%s[="..."]';
            } else {
                $format = '--%s';
            }

            $elements[] = sprintf('['.$format.']', $option->getName());
        }

        foreach ($this->getArguments() as $argument) {
            $elements[] = sprintf(
                $argument->isRequired() ? '<%s>' : '[<%s>]',
                $argument->getName().($argument->isArray() ? '1' : '')
            );

            if ($argument->isArray()) {
                $elements[] = sprintf('... [%sN]', $argument->getName());
            }
        }

        return implode(' ', $elements);
    }
}
