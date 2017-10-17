<?php

/**
 * QueryBrowser
 *
 * @link      https://gitlab.kapma.nl/paulhekkema/querybrowser
 * @license   MIT (see LICENSE for details)
 * @author    Paul Hekkema <paul@hekkema.nl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace QueryBrowser\StorageDriver;

use QueryBrowser\StorageDriver\StorageDriverInterface;

/**
 * StorageDriver for null (used for testing).
 */
class NullDriver implements StorageDriverInterface
{
    /**
     * {@inheritDoc}
     */
    public function get(string $key)
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, string $value)
    {
        return false;
    }
}
