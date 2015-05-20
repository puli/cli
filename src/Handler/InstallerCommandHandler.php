<?php

/*
 * This file is part of the puli/asset-plugin package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Handler;

use Puli\Cli\Util\StringUtil;
use Puli\Manager\Api\Installer\InstallerDescriptor;
use Puli\Manager\Api\Installer\InstallerManager;
use Puli\Manager\Api\Installer\InstallerParameter;
use RuntimeException;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\UI\Component\Table;
use Webmozart\Console\UI\Style\TableStyle;

/**
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InstallerCommandHandler
{
    /**
     * @var InstallerManager
     */
    private $installerManager;

    public function __construct(InstallerManager $installerManager)
    {
        $this->installerManager = $installerManager;
    }

    public function handleList(Args $args, IO $io)
    {
        $table = new Table(TableStyle::borderless());

        foreach ($this->installerManager->getInstallerDescriptors() as $descriptor) {
            $className = $descriptor->getClassName();

            if (!$args->isOptionSet('long')) {
                $className = StringUtil::getShortClassName($className);
            }

            $parameters = array();

            foreach ($descriptor->getParameters() as $parameterName => $parameter) {
                if (!$parameter->isRequired()) {
                    $parameterName .= '='.StringUtil::formatValue($parameter->getDefaultValue());
                }

                $parameters[] = $parameterName;
            }

            $description = $descriptor->getDescription();

            if ($parameters) {
                // non-breaking space
                $description .= ' <c1>('.implode(",\xc2\xa0", $parameters).')</c1>';
            }

            $table->addRow(array(
                '<u>'.$descriptor->getName().'</u>',
                '<c1>'.$className.'</c1>',
                $description
            ));
        }

        $table->render($io);

        return 0;
    }

    public function handleAdd(Args $args)
    {
        $descriptions = $args->getOption('description');
        $parameters = array();

        // The first description is for the installer
        $description = $descriptions ? array_shift($descriptions) : null;

        foreach ($args->getOption('param') as $parameter) {
            // Subsequent descriptions are for the parameters
            $paramDescription = $descriptions ? array_shift($descriptions) : null;

            // Optional parameter with default value
            if (false !== ($pos = strpos($parameter, '='))) {
                $parameters[] = new InstallerParameter(
                    substr($parameter, 0, $pos),
                    InstallerParameter::OPTIONAL,
                    StringUtil::parseValue(substr($parameter, $pos + 1)),
                    $paramDescription
                );

                continue;
            }

            // Required parameter
            $parameters[] = new InstallerParameter(
                $parameter,
                InstallerParameter::REQUIRED,
                null,
                $paramDescription
            );
        }

        $this->installerManager->addRootInstallerDescriptor(new InstallerDescriptor(
            $args->getArgument('name'),
            $args->getArgument('class'),
            $description,
            $parameters
        ));

        return 0;
    }

    public function handleDelete(Args $args)
    {
        $installerName = $args->getArgument('name');

        if (!$this->installerManager->hasInstallerDescriptor($installerName)) {
            throw new RuntimeException(sprintf(
                'The installer "%s" does not exist.',
                $installerName
            ));
        }

        $this->installerManager->removeRootInstallerDescriptor($installerName);

        return 0;
    }
}
