<?php

/*
 * This file is part of the puli/cli package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Puli\Cli\Console\Application;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;

/**
 * A console application with support for composite commands.
 *
 * The application distinguishes between simple and composite commands:
 *
 *  * Simple commands consist of one command only, for example "package".
 *  * Composite commands consist of a command and a sub command, for example
 *    "package add".
 *
 * Arguments and options are supported for both kinds of commands. However,
 * all options must go behind the sub command for composite commands.
 *
 * @since  1.0
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class CompositeCommandApplication extends Application
{
    /**
     * {@inheritdoc}
     */
    protected function getCommandName(InputInterface $input)
    {
        // Extract the first two arguments of the commands
        // The sub-command must be written before any options
        preg_match('/^(\S+)(\s+([^-]\S*))?/', (string) $input, $matches);

        if (isset($matches[3])) {
            return $matches[1].' '.$matches[3];
        }

        return $matches[1];
    }

    /**
     * {@inheritdoc}
     */
    public function find($name)
    {
        $commands = $this->all();
        $commandNames = array_keys($commands);

        // Extract "package" and "add" out of "package add"
        // The second argument may be a sub-command or an argument
        list($mainCommand, $subOrArg) = $this->parseCommandName($name);

        // Replace "pack" by "pack[^ ]*" to match "pack" and "package"
        $simpleMatches = preg_grep('~^'.$mainCommand.'[^ ]*$~', $commandNames);
        $compositeMatches = array();

        if (null !== $subOrArg) {
            // Replace "pack add" by "pack[^ ]* add[^ ]*" to match
            // "package add" and "package addon"
            $compositeMatches = preg_grep('~^'.$mainCommand.'[^ ]* '.$subOrArg.'[^ ]*$~', $commandNames);
        }

        // If we found an exact match, return it now
        if (in_array($name, $compositeMatches, true) || in_array($name, $simpleMatches, true)) {
            return $this->get($name);
        }

        // No matches found -> throw exception
        if (0 === count($simpleMatches) && 0 === count($compositeMatches)) {
            throw $this->createCommandNotDefinedException($name, $mainCommand, $subOrArg);
        }

        // Before checking commands for ambiguity, we should remove aliases.
        // Otherwise, if a command and its alias have the same prefix
        // (e.g. "package" and "package-alias"), they will be suggested as
        // alternatives even though in reality they point to the same command
        $filterAliases = function ($nameOrAlias) use ($commands) {
            // For aliases, the name in the command list and the name returned
            // from getName() differ
            return $nameOrAlias === $commands[$nameOrAlias]->getName();
        };

        $compositeMatches = array_filter($compositeMatches, $filterAliases);

        // If we found just one composite match, return it
        // For example, if we search "pack add" and find "package add" and
        // "package", we assume that "package add" is the correct match
        if (1 === count($compositeMatches)) {
            return $this->get(reset($compositeMatches));
        }

        // If we found more than one composite match (e.g. "package add"
        // and "package addon"), only suggest composite matches
        if (count($compositeMatches) > 1) {
            throw $this->createCommandAmbiguousException($name, $compositeMatches);
        }

        // We did not find any composite matches
        // If we found an exact match for the main command, return it now
        if (in_array($mainCommand, $simpleMatches)) {
            return $this->get($mainCommand);
        }

        $simpleMatches = array_filter($simpleMatches, $filterAliases);

        // If we found just one simple match, return it
        if (1 === count($simpleMatches)) {
            return $this->get(reset($simpleMatches));
        }

        // Otherwise suggest simple matches{
        throw $this->createCommandAmbiguousException($name, $simpleMatches);
    }

    private function createCommandNotDefinedException($name, $mainCommand, $subOrArg)
    {
        $commandNames = array_keys($this->all());

        // Only report "package" of "package"
        $message = sprintf('Command "%s" is not defined.', $mainCommand);

        // Find alternatives for the complete command "packy arg"
        $alternatives = $this->findAlternatives($name, $commandNames);

        if (null !== $subOrArg) {
            // If an argument was provided, also provide alternatives for
            // only the main command "packy"
            $alternatives = array_merge(
                $alternatives,
                $this->findAlternatives($mainCommand, $commandNames)
            );

            // Sort and eliminate duplicates
            sort($alternatives);
            $alternatives = array_unique($alternatives);
        }

        if (count($alternatives) > 0) {
            if (1 === count($alternatives)) {
                $message .= "\n\nDid you mean this?\n    ";
            } else {
                $message .= "\n\nDid you mean one of these?\n    ";
            }
            $message .= implode("\n    ", $alternatives);
        }

        $exception = new \InvalidArgumentException($message);

        return $exception;
    }

    private function createCommandAmbiguousException($name, $alternatives)
    {
        $suggestions = $this->getAbbreviationSuggestions(array_values($alternatives));

        return new \InvalidArgumentException(sprintf(
            'Command "%s" is ambiguous (%s).',
            $name,
            $suggestions
        ));
    }

    private function parseCommandName($name)
    {
        $pos = strpos($name, ' ');
        $mainCommand = false === $pos ? $name : substr($name, 0, $pos);
        $subOrArg = false === $pos ? null : substr($name, $pos + 1);

        return array($mainCommand, $subOrArg);
    }

    /**
     * Finds alternative of $name among $collection,
     *
     * @param string             $name       The string
     * @param array|\Traversable $collection The collection
     *
     * @return array A sorted array of similar string
     */
    private function findAlternatives($name, $collection)
    {
        $threshold = 1e3;
        $alternatives = array();

        $collectionParts = array();
        foreach ($collection as $item) {
            $collectionParts[$item] = explode(':', $item);
        }

        foreach (explode(':', $name) as $i => $subname) {
            foreach ($collectionParts as $collectionName => $parts) {
                $exists = isset($alternatives[$collectionName]);
                if (!isset($parts[$i]) && $exists) {
                    $alternatives[$collectionName] += $threshold;
                    continue;
                } elseif (!isset($parts[$i])) {
                    continue;
                }

                $lev = levenshtein($subname, $parts[$i]);
                if ($lev <= strlen($subname) / 3 || '' !== $subname && false !== strpos($parts[$i], $subname)) {
                    $alternatives[$collectionName] = $exists ? $alternatives[$collectionName] + $lev : $lev;
                } elseif ($exists) {
                    $alternatives[$collectionName] += $threshold;
                }
            }
        }

        foreach ($collection as $item) {
            $lev = levenshtein($name, $item);
            if ($lev <= strlen($name) / 3 || false !== strpos($item, $name)) {
                $alternatives[$item] = isset($alternatives[$item]) ? $alternatives[$item] - $lev : $lev;
            }
        }

        $alternatives = array_filter($alternatives, function ($lev) use ($threshold) { return $lev < 2*$threshold; });
        asort($alternatives);

        return array_keys($alternatives);
    }

    /**
     * Returns abbreviated suggestions in string format.
     *
     * @param array $abbrevs Abbreviated suggestions to convert
     *
     * @return string A formatted string of abbreviated suggestions
     */
    private function getAbbreviationSuggestions($abbrevs)
    {
        return sprintf('%s, %s%s', $abbrevs[0], $abbrevs[1], count($abbrevs) > 2 ? sprintf(' and %d more', count($abbrevs) - 2) : '');
    }
}
