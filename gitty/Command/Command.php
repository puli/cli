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

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Webmozart\Gitty\GittyApplication;
use Webmozart\Gitty\Input\InputDefinition;

/**
 * A command.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Command extends \Symfony\Component\Console\Command\Command
{
    const COMMAND_ARG = 'command-name';

    /**
     * @var InputDefinition
     */
    private $localDefinition;

    /**
     * {@inheritdoc}
     */
    public function __construct($name = null)
    {
        parent::__construct($name);

        // Use custom InputDefinition implementation
        $inputDefinition = new InputDefinition();
        $inputDefinition->addArguments($this->getDefinition()->getArguments());
        $inputDefinition->addOptions($this->getDefinition()->getOptions());

        $this->setDefinition($inputDefinition);

        // Remember this input definition later on to get the synopsis without
        // the application options/arguments
        $this->localDefinition = clone $inputDefinition;
    }

    /**
     * {@inheritdoc}
     */
    public function getLocalDefinition()
    {
        return $this->localDefinition;
    }

    /**
     * Sets the application instance for this command.
     *
     * @param GittyApplication $application The application.
     *
     * @throws \InvalidArgumentException If the application is not an instance
     *                                   of {@link GittyApplication}.
     */
    public function setApplication(Application $application = null)
    {
        if ($application !== null && !$application instanceof GittyApplication) {
            throw new \InvalidArgumentException(sprintf(
                'The application should be an instance of GittyApplication or '.
                'null. Got: %s',
                is_object($application) ? get_class($application) : gettype($application)
            ));
        }

        parent::setApplication($application);
    }

    /**
     * Returns the application instance for this command.
     *
     * @return GittyApplication An Application instance
     */
    public function getApplication()
    {
        return parent::getApplication();
    }

    public function mergeApplicationDefinition($mergeArgs = true)
    {
        // Never merge application arguments
        parent::mergeApplicationDefinition(false);

        $inputDefinition = $this->getDefinition();

        // Add "command-name" argument
        if ($mergeArgs && !$inputDefinition->hasArgument(self::COMMAND_ARG)) {
            $arguments = $inputDefinition->getArguments();
            $inputDefinition->setArguments(array(new InputArgument(self::COMMAND_ARG, InputArgument::REQUIRED)));
            $inputDefinition->addArguments($arguments);
        }
    }
}
