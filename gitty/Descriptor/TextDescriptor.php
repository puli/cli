<?php

/*
 * This file is part of the webmozart/gitty package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webmozart\Gitty\Descriptor;

use Symfony\Component\Console\Descriptor\TextDescriptor as BaseTextDescriptor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Webmozart\Gitty\GittyApplication;

/**
 * Describes an object as text on the console output.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TextDescriptor extends BaseTextDescriptor
{
    /**
     * {@inheritdoc}
     */
    protected function describeInputDefinition(InputDefinition $definition, array $options = array())
    {
        $definition = clone $definition;

        // Filter out the "main-command" and "sub-command" arguments
        $filter = function (InputArgument $arg) {
            return !in_array($arg->getName(), array(
                GittyApplication::MAIN_COMMAND_ARG,
                GittyApplication::SUB_COMMAND_ARG
            ));
        };

        $definition->setArguments(array_filter($definition->getArguments(), $filter));

        parent::describeInputDefinition($definition, $options);
    }
}
