<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2014-2026 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Stream
 */

namespace Horde\Stream;

use Horde\Stream\Wrapper\StringWrapper;
use InvalidArgumentException;

final class StringStream extends AbstractStream
{
    public function __construct(string $data = '')
    {
        $resource = StringWrapper::getStream($data);
        if (!is_resource($resource)) {
            throw new StreamException('Failed to create string stream.');
        }
        $this->resource = $resource;
    }
}
