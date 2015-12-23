<?php

/*
 * This file is part of the webmozart/glob package.
 *
 * (c) Bernhard Schussek <bschussek@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webmozart\Glob\Iterator;

use ArrayIterator;
use EmptyIterator;
use InvalidArgumentException;
use IteratorIterator;
use RecursiveIteratorIterator;
use Webmozart\Glob\Glob;

/**
 * Returns filesystem paths matching a glob.
 *
 * @since  1.0
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see    Glob
 */
class GlobIterator extends IteratorIterator
{
    /**
     * Creates a new iterator.
     *
     * @param string $glob  The glob pattern.
     * @param int    $flags A bitwise combination of the flag constants in
     *                      {@link Glob}.
     */
    public function __construct($glob, $flags = 0)
    {
        $basePath = Glob::getBasePath($glob, $flags);

        if (!Glob::isDynamic($glob) && file_exists($glob)) {
            // If the glob is a file path, return that path
            $innerIterator = new ArrayIterator(array($glob));
        } elseif (is_dir($basePath)) {
            // Use the system's much more efficient glob() function where we can
            if (
                // glob() does not support /**/
                false === strpos($glob, '/**/') &&
                // glob() does not support stream wrappers
                false === strpos($glob, '://') &&
                // glob() does not support [^...] on Windows
                ('\\' !== DIRECTORY_SEPARATOR || false === strpos($glob, '[^'))
            ) {
                if (false === $results = glob($glob, GLOB_BRACE)) {
                    $innerIterator = new EmptyIterator();
                } else {
                    $innerIterator = new ArrayIterator($results);
                }
            } else {
                try {
                    // Otherwise scan the glob's base directory for matches
                    $innerIterator = new GlobFilterIterator(
                        $glob,
                        new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator(
                                $basePath,
                                RecursiveDirectoryIterator::CURRENT_AS_PATHNAME
                                    | RecursiveDirectoryIterator::SKIP_DOTS
                            ),
                            RecursiveIteratorIterator::SELF_FIRST
                        ),
                        GlobFilterIterator::FILTER_VALUE,
                        $flags
                    );
                } catch (InvalidArgumentException $e) {
                    if (0 === strpos($e->getMessage(), 'Invalid glob: missing ]')
                        || 0 === strpos($e->getMessage(), 'Invalid glob: missing }')) {
                        // Remain compatible with glob() which simply returns
                        // nothing in this case
                        $innerIterator = new EmptyIterator();
                    } else {
                        throw $e;
                    }
                }
            }
        } else {
            // If the glob's base directory does not exist, return nothing
            $innerIterator = new EmptyIterator();
        }

        parent::__construct($innerIterator);
    }
}
