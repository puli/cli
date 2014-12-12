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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HelpCommand extends Command
{
    private $command;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->ignoreValidationErrors();

        $this
            ->setName('help')
            ->setDescription('Displays help for a command')
            ->addArgument('command', InputArgument::OPTIONAL)
            ->setDefinition(array(
                new InputArgument('command', InputArgument::OPTIONAL, 'The command name'),
                new InputOption('xml', null, InputOption::VALUE_NONE, 'To output help as XML'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'To output help in other formats', 'txt'),
                new InputOption('raw', null, InputOption::VALUE_NONE, 'To output raw command help'),
            ))
        ;
    }

    /**
     * Sets the command
     *
     * @param Command $command The command to set
     */
    public function setCommand(Command $command)
    {
        $this->command = $command;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (null === $this->command) {
            $this->command = $this->getApplication()->find($input->getArgument('command_name'));
        }

        if ($input->getOption('xml')) {
            $input->setOption('format', 'xml');
        }

        $helper = new DescriptorHelper();
        $helper->describe($output, $this->command, array(
            'format' => $input->getOption('format'),
            'raw' => $input->getOption('raw'),
        ));

        $this->command = null;
    }
}
