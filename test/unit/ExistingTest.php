<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Stream\Test\Unit;

use Horde_Stream;
use Horde_Stream_Existing;
use Horde\Stream\Test\TestBase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the Horde_Stream_Existing class.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(Horde_Stream_Existing::class)]
#[CoversClass(Horde_Stream::class)]
class ExistingTest extends TestBase
{
    protected function _getOb(): Horde_Stream
    {
        return new Horde_Stream_Existing([
            'stream' => fopen('php://temp', 'r+'),
        ]);
    }
}
