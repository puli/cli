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

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Descriptor\ApplicationDescription;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Webmozart\Gitty\Command\Command;
use Webmozart\Gitty\Command\CompositeCommand;
use Webmozart\Gitty\GittyApplication;

/**
 * Describes an object as text on the console output.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TextDescriptor extends \Symfony\Component\Console\Descriptor\TextDescriptor
{
    /**
     * {@inheritdoc}
     */
    protected function describeInputDefinition(InputDefinition $definition, array $options = array())
    {
        $definition = clone $definition;
        $arguments = $definition->getArguments();

        // Filter out the "command" and "sub-command" arguments
        $filter = function (InputArgument $arg) {
            return !in_array($arg->getName(), array(
                Command::COMMAND_ARG,
                CompositeCommand::SUB_COMMAND_ARG
            ));
        };

        $arguments = array_filter($arguments, $filter);

        $definition->setArguments($this->wrapArgumentNames($arguments));

        parent::describeInputDefinition($definition, $options);
    }

    /**
     * {@inheritdoc}
     */
    protected function describeApplication(Application $application, array $options = array())
    {
        if (!$application instanceof GittyApplication) {
            throw new \InvalidArgumentException(sprintf(
                'Expected argument of type GittyApplication. Got: %s',
                is_object($application) ? get_class($application) : gettype($application)
            ));
        }

        $description = new ApplicationDescription($application);

        if (isset($options['raw_text']) && $options['raw_text']) {
            $width = $this->getColumnWidth($description->getCommands());

            foreach ($description->getCommands() as $command) {
                $this->writeText(sprintf("%-${width}s %s", $command->getName(), $command->getDescription()), $options);
                $this->writeText("\n");
            }
        } else {
            if ('' != $help = $application->getHelp()) {
                $this->writeText("$help\n\n", $options);
            }

            $this->writeText('<comment>Usage:</comment>', $options);
            $this->writeText("\n");
            $this->writeText(' '.$application->getSynopsis(), $options);
            $this->writeText("\n");

            if ($definition = $application->getDefinition()) {
                $this->writeText("\n");
                $this->describeInputDefinition($definition, $options);
            }

            $this->writeText("\n", $options);

            $width = $this->getColumnWidth($description->getCommands());

            $this->writeText('<comment>Available commands:</comment>', $options);

            foreach ($description->getNamespaces() as $namespace) {
                foreach ($namespace['commands'] as $name) {
                    $this->writeText("\n");
                    $this->writeText(sprintf(" <info>%-${width}s</info> %s", $name, $description->getCommand($name)->getDescription()), $options);
                }
            }

            $this->writeText("\n");
        }
    }

    /**
     * {@inheritdoc}
     */
    private function writeText($content, array $options = array())
    {
        $this->write(
            isset($options['raw_text']) && $options['raw_text'] ? strip_tags($content) : $content,
            isset($options['raw_output']) ? !$options['raw_output'] : true
        );
    }

    /**
     * @param Command[] $commands
     *
     * @return int
     */
    private function getColumnWidth(array $commands)
    {
        $width = 0;
        foreach ($commands as $command) {
            $width = strlen($command->getName()) > $width ? strlen($command->getName()) : $width;
        }

        return $width + 2;
    }

    /**
     * @param $arguments
     *
     * @return mixed
     */
    protected function wrapArgumentNames($arguments)
    {
// Wrap argument names with "<" and ">"
        foreach ($arguments as $key => $argument) {
            $mode = $argument->isRequired() ? InputArgument::REQUIRED
                : InputArgument::OPTIONAL;

            if ($argument->isArray()) {
                $mode |= InputArgument::IS_ARRAY;
            }

            $arguments[$key] = new InputArgument(
                '<'.$argument->getName().'>',
                $mode,
                $argument->getDescription(),
                $argument->getDefault()
            );
        }

        return $arguments;
    }
}
