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
use Horde_Stream_Temp;
use Horde_Stream_TempString;
use Horde\Stream\Test\TestBase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the Horde_Stream_TempString class, with the data being stored
 * in a PHP temp stream internally.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @copyright  2014-2026 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(Horde_Stream_TempString::class)]
#[CoversClass(Horde_Stream_Temp::class)]
#[CoversClass(Horde_Stream::class)]
class TempStringStreamTest extends TestBase
{
    protected function _getOb(): Horde_Stream
    {
        return new Horde_Stream_TempString([
            'max_memory' => 1,
        ]);
    }

    public function testUsingStream(): void
    {
        $ob = $this->_getOb();
        $ob->add('123');

        $this->assertTrue($ob->use_stream);
    }
}
