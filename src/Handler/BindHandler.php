<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Handler;

use Puli\Cli\Util\StringUtil;
use Puli\RepositoryManager\Api\Discovery\BindingDescriptor;
use Puli\RepositoryManager\Api\Discovery\BindingState;
use Puli\RepositoryManager\Api\Discovery\DiscoveryManager;
use Puli\RepositoryManager\Api\Package\PackageCollection;
use Symfony\Component\Console\Helper\Table;
use Webmozart\Console\Adapter\IOOutput;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BindHandler
{
    /**
     * @var DiscoveryManager
     */
    private $discoveryManager;

    /**
     * @var PackageCollection
     */
    private $packages;

    public function __construct(DiscoveryManager $discoveryManager, PackageCollection $packages)
    {
        $this->discoveryManager = $discoveryManager;
        $this->packages = $packages;
    }

    public function handleList(Args $args, IO $io)
    {
        $packageNames = self::getPackageNames($args, $this->packages->getPackageNames());
        $bindingStates = $this->getBindingStates($args);

        $printBindingState = count($bindingStates) > 1;
        $printPackageName = count($packageNames) > 1;
        $printHeaders = $printBindingState || $printPackageName;

        foreach ($bindingStates as $bindingState) {
            $bindingStatePrinted = !$printBindingState;

            foreach ($packageNames as $packageName) {
                $bindings = $this->discoveryManager->getBindings($packageName, $bindingState);

                if (!$bindings) {
                    continue;
                }

                if (!$bindingStatePrinted) {
                    $this->printBindingState($io, $bindingState);
                    $bindingStatePrinted = true;
                }

                if ($printPackageName) {
                    $prefix = $printBindingState ? '    ' : '';
                    $io->writeLine("<h>$prefix$packageName</h>");
                }

                $styleTag = BindingState::ENABLED === $bindingState ? null : 'fg=red';

                $this->printBindingTable($io, $bindings, $styleTag, $printBindingState);

                if ($printHeaders) {
                    $io->writeLine('');
                }
            }
        }

        return 0;
    }

    public function handleSave(Args $args)
    {
        $bindingParams = array();

        foreach ($args->getOption('param') as $parameter) {
            $pos = strpos($parameter, '=');

            if (false === $pos) {
                // invalid parameter
            }

            $key = substr($parameter, 0, $pos);
            $value = StringUtil::parseValue(substr($parameter, $pos + 1));

            $bindingParams[$key] = $value;
        }

        $this->discoveryManager->addBinding(new BindingDescriptor(
            $args->getArgument('query'),
            $args->getArgument('type'),
            $bindingParams,
            $args->getOption('language')
        ));

        return 0;
    }

    public function handleDelete(Args $args)
    {
        $bindings = $this->discoveryManager->findBindings($args->getArgument('uuid'));

        if (0 === count($bindings)) {
            return 0;
        }

        if (count($bindings) > 1) {
            // ambiguous
        }

        $bindingToRemove = reset($bindings);
        $this->discoveryManager->removeBinding($bindingToRemove->getUuid());

        return 0;
    }

    public function handleEnable(Args $args)
    {
        $packageNames = $this->getPackageNames($args, $this->packages->getInstalledPackageNames());
        $bindings = $this->discoveryManager->findBindings($args->getArgument('uuid'));

        if (0 === count($bindings)) {
            return 0;
        }

        if (count($bindings) > 1) {
            // ambiguous
        }

        $this->discoveryManager->enableBinding(reset($bindings)->getUuid(), $packageNames);

        return 0;
    }

    public function handleDisable(Args $args)
    {
        $packageNames = $this->getPackageNames($args, $this->packages->getInstalledPackageNames());
        $bindings = $this->discoveryManager->findBindings($args->getArgument('uuid'));

        if (0 === count($bindings)) {
            return 0;
        }

        if (count($bindings) > 1) {
            // ambiguous
        }

        $this->discoveryManager->disableBinding(reset($bindings)->getUuid(), $packageNames);

        return 0;
    }

    private function getBindingStates(Args $args)
    {
        $states = array();

        if ($args->isOptionSet('enabled')) {
            $states[] = BindingState::ENABLED;
        }

        if ($args->isOptionSet('disabled')) {
            $states[] = BindingState::DISABLED;
        }

        if ($args->isOptionSet('duplicate')) {
            $states[] = BindingState::DUPLICATE;
        }

        if ($args->isOptionSet('undecided')) {
            $states[] = BindingState::UNDECIDED;
        }

        if ($args->isOptionSet('held-back')) {
            $states[] = BindingState::HELD_BACK;
        }

        if ($args->isOptionSet('invalid')) {
            $states[] = BindingState::INVALID;
        }

        return $states ?: BindingState::all();
    }

    /**
     * @param IO                  $io
     * @param BindingDescriptor[] $bindings
     * @param string              $styleTag
     * @param bool                $indent
     */
    private function printBindingTable(IO $io, array $bindings, $styleTag = null, $indent = false)
    {
        $table = new Table(new IOOutput($io));
        $table->setStyle('compact');
        $table->getStyle()->setBorderFormat('');

        $prefix = $indent ? '    ' : '';
        $paramTag = $styleTag ?: 'comment';
        $uuidTag = $styleTag ?: 'comment';
        $queryTag = $styleTag ?: 'em';
        $typeTag = $styleTag ?: 'tt';

        foreach ($bindings as $binding) {
            $parameters = array();

            foreach ($binding->getParameterValues() as $parameterName => $value) {
                $parameters[] = $parameterName.'='.StringUtil::formatValue($value);
            }

            $uuid = substr($binding->getUuid(), 0, 6);
            $paramString = $parameters ? " <$paramTag>(".implode(', ', $parameters).")</$paramTag>" : '';

            $table->addRow(array(
                "$prefix<$uuidTag>".$uuid."</$uuidTag> ".
                "<$queryTag>".$binding->getQuery()."</$queryTag>",
                " <$typeTag>".$binding->getTypeName()."</$typeTag>".$paramString
            ));
        }

        $table->render();
    }

    private function printBindingState(IO $io, $bindingState)
    {
        switch ($bindingState) {
            case BindingState::ENABLED:
                $io->writeLine('Enabled bindings:');
                $io->writeLine('');
                return;
            case BindingState::DISABLED:
                $io->writeLine('Disabled bindings:');
                $io->writeLine(' (use "puli bind --enable <uuid>" to enable)');
                $io->writeLine('');
                return;
            case BindingState::UNDECIDED:
                $io->writeLine('Bindings that are neither enabled nor disabled:');
                $io->writeLine(' (use "puli bind --enable <uuid>" to enable)');
                $io->writeLine('');
                return;
            case BindingState::DUPLICATE:
                $io->writeLine('The following bindings are duplicates and ignored:');
                $io->writeLine('');
                return;
            case BindingState::HELD_BACK:
                $io->writeLine('The following bindings are held back:');
                $io->writeLine(' (install or fix their type definitions to enable)');
                $io->writeLine('');
                return;
            case BindingState::INVALID:
                $io->writeLine('The following bindings have invalid parameters:');
                $io->writeLine(' (remove the binding and add again with correct parameters)');
                $io->writeLine('');
                return;
        }
    }

    /**
     * @param Args  $args
     * @param array $default
     *
     * @return string[]
     */
    private function getPackageNames(Args $args, array $default)
    {
        // Display all packages if "all" is set
        if ($args->isOptionSet('all')) {
            return $this->packages->getPackageNames();
        }

        $packageNames = array();

        if ($args->isOptionSet('root')) {
            $packageNames[] = $this->packages->getRootPackage()->getName();
        }

        foreach ($args->getOption('package') as $packageName) {
            $packageNames[] = $packageName;
        }

        return $packageNames ?: $default;
    }
}
