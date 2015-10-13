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

use DateTime;
use Puli\Cli\Util\StringUtil;
use Puli\Repository\Api\Resource\PuliResource;
use Puli\Repository\Api\ResourceCollection;
use Puli\Repository\Api\ResourceRepository;
use RuntimeException;
use Webmozart\Console\Api\Args\Args;
use Webmozart\Console\Api\IO\IO;
use Webmozart\Console\UI\Component\Grid;
use Webmozart\Console\UI\Component\Table;
use Webmozart\Console\UI\Style\Alignment;
use Webmozart\Console\UI\Style\GridStyle;
use Webmozart\Console\UI\Style\TableStyle;
use Webmozart\PathUtil\Path;

/**
 * Handles the "ls" command.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class LsCommandHandler
{
    /**
     * @var ResourceRepository
     */
    private $repo;

    /**
     * @var string
     */
    private $currentPath = '/';

    /**
     * Creates the handler.
     *
     * @param ResourceRepository $repo The resource repository.
     */
    public function __construct(ResourceRepository $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Handles the "ls" command.
     *
     * @param Args $args The console arguments.
     * @param IO   $io   The I/O.
     *
     * @return int The status code.
     */
    public function handle(Args $args, IO $io)
    {
        $path = Path::makeAbsolute($args->getArgument('path'), $this->currentPath);

        $resource = $this->repo->get($path);

        if (!$resource->hasChildren()) {
            throw new RuntimeException(sprintf(
                'The resource "%s" does not have children.',
                $resource->getPath()
            ));
        }

        if ($args->isOptionSet('long')) {
            $this->listLong($io, $resource->listChildren());

            return 0;
        }

        $this->listShort($io, $resource->listChildren());

        return 0;
    }

    /**
     * Prints the resources in the short style (without the "-l" option).
     *
     * @param IO                 $io        The I/O.
     * @param ResourceCollection $resources The resources.
     */
    private function listShort(IO $io, ResourceCollection $resources)
    {
        $style = GridStyle::borderless();
        $style->getBorderStyle()->setLineVCChar('  ');
        $grid = new Grid($style);

        foreach ($resources as $resource) {
            $grid->addCell($this->formatName($resource));
        }

        $grid->render($io);
    }

    /**
     * Prints the resources in the long style (with the "-l" option).
     *
     * @param IO                 $io        The I/O.
     * @param ResourceCollection $resources The resources.
     */
    private function listLong(IO $io, ResourceCollection $resources)
    {
        $style = TableStyle::borderless();
        $style->setColumnAlignments(array(
            Alignment::LEFT,
            Alignment::RIGHT,
            Alignment::LEFT,
            Alignment::RIGHT,
            Alignment::RIGHT,
            Alignment::LEFT,
        ));
        $table = new Table($style);

        $today = new DateTime();
        $currentYear = (int) $today->format('Y');

        foreach ($resources as $resource) {
            // Create date from timestamp. Result is in the UTC timezone.
            $modifiedAt = new DateTime('@'.$resource->getMetadata()->getModificationTime());

            // Set timezone to server timezone.
            $modifiedAt->setTimezone($today->getTimezone());

            $year = (int) $modifiedAt->format('Y');

            $table->addRow(array(
                StringUtil::getShortClassName(get_class($resource)),
                $this->formatSize($resource->getMetadata()->getSize()),
                $modifiedAt->format('M'),
                $modifiedAt->format('j'),
                $year < $currentYear ? $year : $modifiedAt->format('H:i'),
                $this->formatName($resource),
            ));
        }

        $table->render($io);
    }

    /**
     * Formats the name of the resource.
     *
     * Resources with children are colored.
     *
     * @param PuliResource $resource The resource.
     *
     * @return string|null The formatted name.
     */
    private function formatName(PuliResource $resource)
    {
        $name = $resource->getName();

        if ($resource->hasChildren()) {
            return '<c1>'.$name.'</c1>';
        }

        return $name;
    }

    /**
     * Formats the given size.
     *
     * @param int $size The size in bytes.
     *
     * @return string
     */
    private function formatSize($size)
    {
        $suffixes = array('', 'K', 'M', 'G', 'T', 'P');
        reset($suffixes);

        $suffix = current($suffixes);

        while ($size > 1023) {
            next($suffixes);

            if (null === key($suffixes)) {
                break;
            }

            $size /= 1024;
            $suffix = current($suffixes);
        }

        if ($size < 10) {
            return number_format($size, 1).$suffix;
        }

        return round($size).$suffix;
    }
}
