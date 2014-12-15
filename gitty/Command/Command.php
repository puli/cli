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

use Webmozart\Gitty\Input\InputDefinition;

/**
 * A command.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Command extends \Symfony\Component\Console\Command\Command
{
    /**
     * {@inheritdoc}
     */
    public function __construct($name = null)
    {
        parent::__construct($name);

        $inputDefinition = new InputDefinition();
        $inputDefinition->addArguments($this->getDefinition()->getArguments());
        $inputDefinition->addOptions($this->getDefinition()->getOptions());

        $this->setDefinition($inputDefinition);
    }
}
