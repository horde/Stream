<?php
/**
 * Copyright 2014-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Stream
 * @subpackage UnitTests
 */
namespace Horde\Stream\Stream;
use \Horde_Stream_Temp;

/**
 * Tests for the Horde_Stream_Temp class.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Stream
 * @subpackage UnitTests
 */
class TempTest extends TestBase
{
    protected function _getOb()
    {
        return new Horde_Stream_Temp();
    }

}
