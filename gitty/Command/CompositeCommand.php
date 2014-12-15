<?php

/*
 * This file is part of the webmozart/gitty package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webmozart\Gitty\Command;

use Symfony\Component\Console\Input\InputArgument;
use Webmozart\Gitty\GittyApplication;

/**
 * A composite command.
 *
 * A composite command consists of a main command and a sub command. The main
 * command and the sub command are stored in the name of the command, separated
 * by a single space:
 *
 * ```php
 * $command = new CompositeCommand('package add');
 * ```
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CompositeCommand extends Command
{
    public function __construct($name = null)
    {
        parent::__construct($name);

        // Add "sub-command" argument in the beginning if the name is composite
        if (false === strpos($this->getName(), ' ')) {
            throw new \RuntimeException('Missing space in the name of the composite command.');
        }
    }

    public function mergeApplicationDefinition($mergeArgs = true)
    {
        parent::mergeApplicationDefinition($mergeArgs);

        $inputDefinition = $this->getDefinition();

        if ($mergeArgs && !$inputDefinition->hasArgument(GittyApplication::SUB_COMMAND_ARG)) {
            $arguments = $inputDefinition->getArguments();
            $inputDefinition->setArguments(array(new InputArgument(GittyApplication::SUB_COMMAND_ARG, InputArgument::REQUIRED)));
            $inputDefinition->addArguments($arguments);
        }
    }

}
