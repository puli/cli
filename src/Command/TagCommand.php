<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Command;

use Puli\RepositoryManager\ManagerFactory;
use Puli\RepositoryManager\Package\PackageManager;
use Puli\RepositoryManager\Tag\TagDefinition;
use Puli\RepositoryManager\Tag\TagManager;
use Puli\RepositoryManager\Tag\TagMapping;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Console\Command\Command;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class TagCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('tag')
            ->setDescription('Tag resources and list the current tags')
            ->addArgument('path', InputArgument::OPTIONAL, 'The Puli path')
            ->addArgument('tag', InputArgument::OPTIONAL, 'The tag to add to the path')
            ->addOption('root', null, InputOption::VALUE_NONE, 'Filter tags and resources by the root package')
            ->addOption('package', 'p', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Filter tags and resources by a package')
            ->addOption('resources', null, InputOption::VALUE_NONE, 'Show only tagged resources')
            ->addOption('tags', null, InputOption::VALUE_NONE, 'Show only tags')
            ->addOption('enabled', null, InputOption::VALUE_NONE, 'Show only enabled tag mappings')
            ->addOption('disabled', null, InputOption::VALUE_NONE, 'Show only disabled tag mappings')
            ->addOption('new', null, InputOption::VALUE_NONE, 'Show only tag mappings that are neither enabled nor disabled')
            ->addOption('untag', 'u', InputOption::VALUE_NONE, 'Untag a resource')
            ->addOption('add', null, InputOption::VALUE_REQUIRED, 'Add a new tag')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'The description of a tag added with <u>--add</u>')
            ->addOption('delete', 'd', InputOption::VALUE_REQUIRED, 'Delete a tag')
            ->addSynopsis('<path> <tag>')
            ->addSynopsis('[--root] [--package <package>] [--resources|--tags|--enabled|--disabled|--new]')
            ->addSynopsis('--untag <path> [<tag>]')
            ->addSynopsis('--add <tag> [--description <description>]')
            ->addSynopsis('--delete <tag>')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ConsoleOutputInterface $output */
        $environment = ManagerFactory::createProjectEnvironment(getcwd());
        $packageManager = ManagerFactory::createPackageManager($environment);
        $tagManager = ManagerFactory::createTagManager($environment, $packageManager);

        if ($input->getOption('add')) {
            return $this->defineTag($tagManager, $input->getOption('add'), $input->getArgument('description'));
        }

        if ($input->getOption('delete')) {
            return $this->undefineTag($tagManager, $input->getOption('delete'));
        }

        if ($input->getOption('untag')) {
            return $this->unmapTag($tagManager, $input->getArgument('path'), $input->getArgument('tag'));
        }

        if ($input->getArgument('path')) {
            return $this->mapTag($tagManager, $input->getArgument('path'), $input->getArgument('tag'));
        }

        $this->printList($input, $output, $packageManager, $tagManager);

        return 0;
    }

    private function defineTag(TagManager $tagManager, $tag, $description = null)
    {
        // Accept redefinition of tags as long as they are in the root package
        if ($tagManager->hasRootTagDefinition($tag)) {
            $tagManager->removeRootTagDefinition($tag);
        }

        $tagManager->addRootTagDefinition(new TagDefinition($tag, $description));

        return 0;
    }

    private function undefineTag(TagManager $tagManager, $tag)
    {
        // TODO Check whether mappings for the tag exist and, if yes,
        // ask for confirmation (default false) unless --force is given
        // Also delete the tag mappings in that case
        $tagManager->removeRootTagDefinition($tag);

        return 0;
    }

    private function mapTag(TagManager $tagManager, $selector, $tag)
    {
        $tagManager->addRootTagMapping(new TagMapping($selector, $tag));

        return 0;
    }

    private function unmapTag(TagManager $tagManager, $selector, $tag)
    {
        $mappings = $tagManager->findRootTagMappings($selector, $tag);

        foreach ($mappings as $mapping) {
            $tagManager->removeRootTagMapping($mapping);
        }

        return 0;
    }

    private function printList(InputInterface $input, OutputInterface $output, PackageManager $packageManager, TagManager $tagManager)
    {
        // The following options restrict the output to a subset
        $typeRestricted = $input->getOption('tags')
            || $input->getOption('enabled')
            || $input->getOption('disabled')
            || $input->getOption('new');

        $packageNames = $this->parsePackageNames($input, $packageManager);

        if (!$typeRestricted && ($input->getOption('root') || !$input->getOption('package'))) {
            $this->printRootTagMappings($output, $tagManager);
        }

        if (!$typeRestricted || $input->getOption('tags')) {
            $this->printTagDefinitions($output, $tagManager, $packageNames);
        }

        // Hide package mappings if "--root" only is given
        if ($input->getOption('package') || !$input->getOption('root')) {
            if (!$typeRestricted || $input->getOption('enabled')) {
                $this->printEnabledPackageTagMappings($output, $tagManager, $packageNames);
            }

            if (!$typeRestricted || $input->getOption('disabled')) {
                $this->printDisabledPackageTagMappings($output, $tagManager, $packageNames);
            }

            if (!$typeRestricted || $input->getOption('new')) {
                $this->printNewPackageTagMappings($output, $tagManager, $packageNames);
            }
        }
    }

    private function parsePackageNames(InputInterface $input, PackageManager $packageManager)
    {
        $packageNames = array();

        if ($input->getOption('root')) {
            $packageNames[] = $packageManager->getRootPackage()->getName();
        }

        if ($input->getOption('package')) {
            foreach ($input->getOption('package') as $packageSelector) {
                $packageNames = array_merge(
                    $packageNames,
                    $packageManager->findPackages($packageSelector)
                );
            }
        }

        return $packageNames;
    }

    private function printRootTagMappings(OutputInterface $output, TagManager $tagManager)
    {
        $mappings = $tagManager->getRootTagMappings();

        $output->writeln('<h>TAGGED RESOURCES</h>');

        if (0 === count($mappings)) {
            $output->writeln(array(
                '  No tagged resources.',
                '  (use "puli tag <path> <tag>" to tag resources)',
                '',
            ));

            return;
        }

        $this->printTagMappings($output, $mappings);
    }

    private function printTagDefinitions(OutputInterface $output, TagManager $tagManager, $packageName = null)
    {
        $definitions = $tagManager->getTagDefinitions($packageName);

        $output->writeln('<h>TAGS</h>');

        if (0 === count($definitions)) {
            $output->writeln(array(
                '  No tag definitions.',
                '  (use "puli tag define <tag> [Description]" to define tags)',
                '',
            ));

            return;
        }

        $table = new Table($output);
        $table->setStyle('compact');
        $table->getStyle()->setBorderFormat('  ');

        foreach ($definitions as $definition) {
            $table->addRow(array(
                '<tt>'.$definition->getTag().'</tt>',
                $definition->getDescription()
            ));
        }

        $table->render();

        $output->writeln('');
    }

    private function printEnabledPackageTagMappings(OutputInterface $output, TagManager $tagManager, $packageName = null)
    {
        $mappings = $tagManager->getEnabledPackageTagMappings(null, $packageName);

        if (0 === count($mappings)) {
            return;
        }

        $output->writeln(array(
            'Enabled tags in installed packages:',
            '(use "puli tag disable <path> [tag]" to disable)',
        ));

        $this->printTagMappings($output, $mappings);
    }

    private function printDisabledPackageTagMappings(OutputInterface $output, TagManager $tagManager, $packageName = null)
    {
        $mappings = $tagManager->getDisabledPackageTagMappings(null, $packageName);

        if (0 === count($mappings)) {
            return;
        }

        $output->writeln(array(
            'Disabled tags in installed packages:',
            '(use "puli tag enable <path> [tag]" to enable)',
        ));

        $this->printTagMappings($output, $mappings);
    }

    private function printNewPackageTagMappings(OutputInterface $output, TagManager $tagManager, $packageName = null)
    {
        $mappings = $tagManager->getNewPackageTagMappings(null, $packageName);

        if (0 === count($mappings)) {
            return;
        }

        $output->writeln(array(
            'New tags in installed packages:',
            '(use "puli tag enable <path> [tag]" to enable)',
            '(use "puli tag disable <path> [tag]" to disable)',
        ));

        $this->printTagMappings($output, $mappings);
    }

    /**
     * @param OutputInterface $output
     * @param TagMapping[]    $mappings
     */
    private function printTagMappings(OutputInterface $output, $mappings)
    {
        $table = new Table($output);
        $table->setStyle('compact');
        $table->getStyle()->setBorderFormat('  ');

        foreach ($mappings as $mapping) {
            $table->addRow(array(
                '<em>'.$mapping->getPuliSelector().'</em>',
                '<tt>'.$mapping->getTag().'</tt>'
            ));
        }

        $table->render();

        $output->writeln('');
    }
}
