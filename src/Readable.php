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

interface Readable
{
    public function getChar(): string|false;

    public function peek(int $length = 1): string;

    public function getString(?int $start = null, ?int $end = null): string;

    public function substring(int $start = 0, ?int $length = null): string;

    public function getToChar(string $end, bool $all = true): string;

    public function search(string $char, bool $reverse = false, bool $reset = true): ?int;

    public function eof(): bool;

    public function length(): int;
}
