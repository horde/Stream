<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Stream\Test\Integration;

use Horde_Stream;
use Horde_Stream_String;
use Horde\Stream\Test\TestBase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the Horde_Stream_String class.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(Horde_Stream_String::class)]
#[CoversClass(Horde_Stream::class)]
class StringTest extends TestBase
{
    protected function _getOb(): Horde_Stream
    {
        $temp = '';
        return new Horde_Stream_String([
            'string' => $temp,
        ]);
    }
}
