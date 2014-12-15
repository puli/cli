<?php

/*
 * This file is part of the webmozart/gitty package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webmozart\Console\Input;

use Webmozart\Console\Command\Command;
use Webmozart\Console\Command\CompositeCommand;

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
            $name = $argument->getName();

            if (in_array($name, array(Command::COMMAND_ARG, CompositeCommand::SUB_COMMAND_ARG))) {
                continue;
            }

            $elements[] = sprintf(
                $argument->isRequired() ? '<%s>' : '[<%s>]',
                $name.($argument->isArray() ? '1' : '')
            );

            if ($argument->isArray()) {
                $elements[] = sprintf('... [<%sN>]', $name);
            }
        }

        return implode(' ', $elements);
    }
}
