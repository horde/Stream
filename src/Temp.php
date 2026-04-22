<?php

declare(strict_types=1);

/**
 * Copyright 2012-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2012-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Stream
 */

namespace Horde\Stream;

final class Temp extends AbstractStream
{
    /**
     * @throws StreamException
     */
    public function __construct(?int $maxMemory = null)
    {
        $uri = 'php://temp';
        if ($maxMemory !== null) {
            $uri .= '/maxmemory:' . $maxMemory;
        }

        $resource = fopen($uri, 'r+');
        if ($resource === false) {
            throw new StreamException('Failed to open temporary stream.');
        }
        $this->resource = $resource;
    }
}
