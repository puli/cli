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

use Symfony\Component\Console\Descriptor\ApplicationDescription;
use Symfony\Component\Console\Descriptor\DescriptorInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Gitty\Command\Command;
use Webmozart\Gitty\Command\CompositeCommand;
use Webmozart\Gitty\GittyApplication;

/**
 * Describes an object as text on the console output.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TextDescriptor implements DescriptorInterface
{
    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var int|null
     */
    private $terminalWidth;

    /**
     * Describes an object as text on the console output.
     *
     * @param OutputInterface          $output  The output.
     * @param Command|GittyApplication $object  The object to describe.
     * @param array                    $options Additional options.
     */
    public function describe(OutputInterface $output, $object, array $options = array())
    {
        $this->output = $output;

        if ($object instanceof Command) {
            list ($this->terminalWidth) = $object->getApplication()->getTerminalDimensions();
            $this->describeCommand($object, $options);

            return;
        }

        if ($object instanceof GittyApplication) {
            list ($this->terminalWidth) = $object->getTerminalDimensions();
            $this->describeApplication($object, $options);

            return;
        }

        throw new \InvalidArgumentException(sprintf(
            'Object of type "%s" is not describable.',
            is_object($object) ? get_class($object) : gettype($object)
        ));
    }

    /**
     * Describes an application.
     *
     * @param GittyApplication $application The application to describe.
     * @param array            $options     Additional options.
     */
    protected function describeApplication(GittyApplication $application, array $options = array())
    {
        $description = new ApplicationDescription($application);
        $help = $application->getHelp();
        $commands = $description->getCommands();
        $definition = $application->getDefinition();
        $inputArgs = $definition ? $definition->getArguments() : array();
        $inputOpts = $definition ? $definition->getOptions() : array();

        $options['nameWidth'] = max(
            $this->getMaxCommandWidth($commands),
            $this->getMaxOptionWidth($inputOpts),
            $this->getMaxArgumentWidth($inputArgs)
        );

        if ($help) {
            $this->printApplicationHelp($help, $options);
            $this->write("\n");
        }

        $this->printApplicationUsage($application, $options);

        $this->write("\n");

        if ($inputArgs) {
            $this->printInputArguments($inputArgs, $options);
        }

        if ($inputArgs && ($inputOpts || $commands)) {
            $this->write("\n");
        }

        if ($inputOpts) {
            $this->printInputOptions($inputOpts, $options);
        }

        if ($inputOpts && $commands) {
            $this->write("\n");
        }

        $this->printCommands($commands, $options);

        $this->write("\n");
    }

    /**
     * Describes a command.
     *
     * @param Command $command The command to describe.
     * @param array   $options Additional options.
     */
    protected function describeCommand(Command $command, array $options = array())
    {
        // Print command usage before merging the application definition
        $this->printCommandUsage($command, $options);

        $command->mergeApplicationDefinition(false);

        $aliases = $command->getAliases();
        $help = $command->getProcessedHelp();
        $definition = $command->getNativeDefinition();
        $inputArgs = $this->filterArguments($definition ? $definition->getArguments() : array());
        $inputOpts = $definition ? $definition->getOptions() : array();

        $options['nameWidth'] = max(
            $this->getMaxOptionWidth($inputOpts),
            $this->getMaxArgumentWidth($inputArgs)
        );

        $this->write("\n");

        if ($aliases) {
            $this->printAliases($aliases, $options);
        }

        if ($aliases && ($inputArgs || $inputOpts || $help)) {
            $this->write("\n");
        }

        if ($inputArgs) {
            $this->printInputArguments($inputArgs, $options);
        }

        if ($inputArgs && ($inputOpts || $help)) {
            $this->write("\n");
        }

        if ($inputOpts) {
            $this->printInputOptions($inputOpts, $options);
        }

        if ($inputOpts && $help) {
            $this->write("\n");
        }

        if ($help) {
            $this->printCommandHelp($help, $options);
        }

        $this->write("\n");
    }

    /**
     * Prints the usage of an application.
     *
     * @param GittyApplication $application The application to describe.
     * @param array            $options     Additional options.
     */
    protected function printApplicationUsage(GittyApplication $application, array $options = array())
    {
        $executableName = $application->getExecutableName();
        $synopsis = $application->getDefinition()->getSynopsis();

        $this->printUsage($executableName, $synopsis, $options);
    }

    /**
     * Prints the usage of a command.
     *
     * @param Command $command The command to describe.
     * @param array   $options Additional options.
     */
    protected function printCommandUsage(Command $command, array $options = array())
    {
        $executableName = $command->getApplication()->getExecutableName();
        $commandName = $executableName.' '.$command->getName();
        $synopsis = $command->getNativeDefinition()->getSynopsis();

        $this->printUsage($commandName, $synopsis, $options);
    }

    /**
     * Prints the usage of a console command.
     *
     * @param string $command  The command to describe.
     * @param string $synopsis The synopsis of the command.
     * @param array  $options  Additional options.
     */
    protected function printUsage($command, $synopsis, array $options = array())
    {
        $this->write('<comment>Usage:</comment>');
        $this->write("\n");
        $this->printWrappedText($synopsis, $command);
        $this->write("\n");
    }

    /**
     * Prints a list of input arguments.
     *
     * @param InputArgument[] $inputArgs The input arguments to describe.
     * @param array           $options   Additional options.
     */
    protected function printInputArguments($inputArgs, array $options = array())
    {
        $this->write('<comment>Arguments:</comment>');
        $this->write("\n");

        foreach ($inputArgs as $argument) {
            $this->printInputArgument($argument, $options);
            $this->write("\n");
        }
    }

    /**
     * Prints an input argument.
     *
     * @param InputArgument $argument The input argument to describe.
     * @param array         $options  Additional options.
     */
    protected function printInputArgument(InputArgument $argument, array $options = array())
    {
        $nameWidth = isset($options['nameWidth']) ? $options['nameWidth'] : null;
        $description = $argument->getDescription();
        $name = $argument->getName();

        if (null !== $argument->getDefault() && (!is_array($argument->getDefault()) || count($argument->getDefault()))) {
            $description .= sprintf('<comment> (default: %s)</comment>', $this->formatDefaultValue($argument->getDefault()));
        }

        $this->printWrappedText($description, '<'.$name.'>', true, $nameWidth, 2);
    }

    /**
     * Prints a list of input options.
     *
     * @param InputOption[] $inputOpts The input options to describe.
     * @param array         $options   Additional options.
     */
    protected function printInputOptions($inputOpts, array $options = array())
    {
        $this->write('<comment>Options:</comment>');
        $this->write("\n");

        foreach ($inputOpts as $option) {
            $this->printInputOption($option, $options);
            $this->write("\n");
        }
    }

    /**
     * Prints an input option.
     *
     * @param InputOption $option  The input option to describe.
     * @param array       $options Additional options.
     */
    protected function printInputOption(InputOption $option, array $options = array())
    {
        $nameWidth = isset($options['nameWidth']) ? $options['nameWidth'] : null;
        $description = $option->getDescription();
        $name = '--'.$option->getName();

        if ($option->getShortcut()) {
            $name .= sprintf(' (-%s)', $option->getShortcut());
        }

        if ($option->acceptValue() && null !== $option->getDefault() && (!is_array($option->getDefault()) || count($option->getDefault()))) {
            $description .= sprintf('<comment> (default: %s)</comment>', $this->formatDefaultValue($option->getDefault()));
        }

        if ($option->isArray()) {
            $description .= '<comment> (multiple values allowed)</comment>';
        }

        $this->printWrappedText($description, $name, true, $nameWidth, 2);
    }

    /**
     * Prints the commands of an application.
     *
     * @param Command[] $commands The commands to describe.
     * @param array     $options  Additional options.
     */
    protected function printCommands($commands, array $options = array())
    {
        if (!isset($options['printCompositeCommands'])) {
            $options['printCompositeCommands'] = false;
        }

        $this->write('<comment>Available commands:</comment>');
        $this->write("\n");

        foreach ($commands as $command) {
            if ($command instanceof CompositeCommand && !$options['printCompositeCommands']) {
                continue;
            }

            $this->printCommand($command, $options);
            $this->write("\n");
        }
    }

    /**
     * Prints a command of an application.
     *
     * @param Command $command The command to describe.
     * @param array   $options Additional options.
     */
    protected function printCommand(Command $command, array $options = array())
    {
        $nameWidth = isset($options['nameWidth']) ? $options['nameWidth'] : null;
        $description = $command->getDescription();
        $name = $command->getName();

        $this->printWrappedText($description, $name, true, $nameWidth, 2);
    }

    /**
     * Prints the aliases of a command.
     *
     * @param string[] $aliases The aliases to describe.
     * @param array    $options Additional options.
     */
    protected function printAliases($aliases, array $options = array())
    {
        $this->write('<comment>Aliases:</comment> <info>'.implode(', ', $aliases).'</info>');
        $this->write("\n");
    }

    /**
     * Prints the help of an application.
     *
     * @param string $help    The help text.
     * @param array  $options Additional options.
     */
    protected function printApplicationHelp($help, array $options = array())
    {
        $this->write("$help\n");
    }

    /**
     * Prints the help of a command.
     *
     * @param string $help    The help text.
     * @param array  $options Additional options.
     */
    protected function printCommandHelp($help, array $options = array())
    {
        $this->write('<comment>Help:</comment>');
        $this->write("\n");
        $this->write(' '.str_replace("\n", "\n ", $help));
        $this->write("\n");
    }

    /**
     * Prints wrapped text.
     *
     * The text will be wrapped to match the terminal width (if available) with
     * a leading and a trailing space.
     *
     * You can optionally pass a label that is written before the text. The
     * text will then be wrapped to start each line one space to the right of
     * the label.
     *
     * If the label should have a minimum width, pass the `$labelWidth`
     * parameter. You can highlight the label by setting `$highlightLabel` to
     * `true`.
     *
     * @param string   $text           The text to write.
     * @param string   $label          The label.
     * @param bool     $highlightLabel Whether to highlight the label.
     * @param int|null $minLabelWidth  The minimum width of the label.
     * @param int      $labelDistance  The distance between the label and the
     *                                 text in spaces.
     */
    protected function printWrappedText($text, $label = '', $highlightLabel = false, $minLabelWidth = null, $labelDistance = 1)
    {
        if (!$minLabelWidth) {
            $minLabelWidth = strlen($label);
        }

        // If we know the terminal width, wrap the text
        if ($this->terminalWidth) {
            // 1 space after the label
            $indentation = $minLabelWidth ? $minLabelWidth + $labelDistance : 0;
            $linePrefix = ' '.str_repeat(' ', $indentation);

            // 1 leading space, 1 trailing space
            $textWidth = $this->terminalWidth - $indentation - 2;

            $text = str_replace("\n", "\n".$linePrefix, wordwrap($text, $textWidth));
        }

        if ($label) {
            $text = sprintf(
                "%s%-${minLabelWidth}s%s%-{$labelDistance}s%s",
                $highlightLabel ? '<info>' : '',
                $label,
                $highlightLabel ? '</info>' : '',
                '',
                $text
            );
        }

        $this->write(' '.$text);
    }

    /**
     * Writes text to the output.
     *
     * @param string $text
     */
    protected function write($text)
    {
        $this->output->write($text, false, OutputInterface::OUTPUT_NORMAL);
    }

    /**
     * Returns the maximum width of the names of a list of options.
     *
     * @param InputOption[] $options The options.
     *
     * @return int The maximum width.
     */
    protected function getMaxOptionWidth(array $options)
    {
        $width = 0;

        foreach ($options as $option) {
            // Respect leading dashes "--"
            $length = strlen($option->getName()) + 2;

            if ($option->getShortcut()) {
                // Respect space, dash and braces " (-", ")"
                $length += strlen($option->getShortcut()) + 4;
            }

            $width = max($width, $length);
        }

        return $width;
    }

    /**
     * Returns the maximum width of the names of a list of arguments.
     *
     * @param InputArgument[] $arguments The arguments.
     *
     * @return int The maximum width.
     */
    protected function getMaxArgumentWidth(array $arguments)
    {
        $width = 0;

        foreach ($arguments as $argument) {
            // Respect wrapping brackets "<", ">"
            $width = max($width, strlen($argument->getName()) + 2);
        }

        return $width;
    }

    /**
     * Returns the maximum width of the names of a list of commands.
     *
     * @param Command[] $commands The commands.
     *
     * @return int The maximum width.
     */
    protected function getMaxCommandWidth(array $commands)
    {
        $width = 0;

        foreach ($commands as $command) {
            $width = max($width, strlen($command->getName()));
        }

        return $width;
    }

    /**
     * Filters arguments that should not be described.
     *
     * Commands contain additional arguments that contain the command name.
     * This is necessary so that the input definition can be correctly bound
     * to the input. However, that argument should not be displayed on the
     * output, since it is not really an argument, but rather part of the
     * called command.
     *
     * @param InputArgument[] $arguments The arguments to filter.
     *
     * @return InputArgument[] The filtered arguments.
     */
    protected function filterArguments($arguments)
    {
        $filter = function (InputArgument $arg) {
            return !in_array($arg->getName(), array(
                Command::COMMAND_ARG,
                CompositeCommand::SUB_COMMAND_ARG
            ));
        };

        return array_filter($arguments, $filter);
    }

    /**
     * Formats the default value of an argument or an option.
     *
     * @param mixed $default The default value to format.
     *
     * @return string The formatted value.
     */
    private function formatDefaultValue($default)
    {
        if (PHP_VERSION_ID < 50400) {
            return str_replace('\/', '/', json_encode($default));
        }

        return json_encode($default, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
