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

use Puli\Discovery\Api\ResourceDiscovery;
use Puli\Repository\Api\ResourceRepository;
use Puli\RepositoryManager\ManagerFactory;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Console\Command\Command;
use Webmozart\Console\Input\InputOption;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FindCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('find')
            ->setDescription('Find resources by different criteria')
            ->addArgument('pattern', InputArgument::OPTIONAL, 'A resource path pattern')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'The short name of a resource class')
            ->addOption('bound-to', 'b', InputOption::VALUE_REQUIRED, 'The name of a binding type')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $factory = new ManagerFactory();
        $environment = $factory->createProjectEnvironment(getcwd());
        $repo = $environment->getRepository();
        $discovery = $environment->getDiscovery();
        $criteria = array();

        if ($input->getArgument('pattern')) {
            $criteria['pattern'] = $input->getArgument('pattern');
        }

        if ($input->getOption('type')) {
            $criteria['shortClass'] = $input->getOption('type');
        }

        if ($input->getOption('bound-to')) {
            $criteria['bindingType'] = $input->getOption('bound-to');
        }

        if (!$criteria) {
            $stderr = $output instanceof ConsoleOutput
                ? $output->getErrorOutput() : $output;
            $stderr->writeln('fatal: No search criteria specified.');

            return 1;
        }

        return $this->listMatches($output, $criteria, $repo, $discovery);
    }

    private function listMatches(OutputInterface $output, array $criteria, ResourceRepository $repo, ResourceDiscovery $discovery)
    {
        if (isset($criteria['pattern']) && isset($criteria['bindingType'])) {
            $matches = array_intersect_key(
                $this->findByPattern($criteria['pattern'], $repo),
                $this->findByBindingType($criteria['bindingType'], $discovery)
            );
        } elseif (isset($criteria['pattern'])) {
            $matches = $this->findByPattern($criteria['pattern'], $repo);
        } elseif (isset($criteria['bindingType'])) {
            $matches = $this->findByBindingType($criteria['bindingType'], $discovery);
        } else {
            $matches = $this->findByPattern('/*', $repo);
        }

        if (isset($criteria['shortClass'])) {
            $shortClass = $criteria['shortClass'];

            $matches = array_filter($matches, function ($value) use ($shortClass) {
                return $value === $shortClass;
            });
        }

        $this->printTable($output, $matches);

        return 0;
    }

    private function findByPattern($pattern, ResourceRepository $repo)
    {
        $matches = array();

        if ('/' !== $pattern[0]) {
            $pattern = '/'.$pattern;
        }

        foreach ($repo->find($pattern) as $resource) {
            $matches[$resource->getPath()] = $this->getShortName(get_class($resource));
        }

        return $matches;
    }

    private function findByBindingType($typeName, ResourceDiscovery $discovery)
    {
        $matches = array();

        foreach ($discovery->find($typeName) as $binding) {
            foreach ($binding->getResources() as $resource) {
                $matches[$resource->getPath()] = $this->getShortName(get_class($resource));
            }
        }

        ksort($matches);

        return $matches;
    }

    private function printTable(OutputInterface $output, array $matches)
    {
        $table = new Table($output);
        $table->setStyle('compact');
        $table->getStyle()->setBorderFormat('');

        foreach ($matches as $path => $shortClass) {
            $table->addRow(array(
                $shortClass,
                " <em>$path</em>"
            ));
        }

        $table->render();
    }

    private function getShortName($className)
    {
        if (false !== ($pos = strrpos($className, '\\'))) {
            return substr($className, $pos + 1);
        }

        return $className;
    }
}
