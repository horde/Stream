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

use Horde_Stream;

final class TempString extends AbstractStream
{
    private ?StringStream $stringBackend;
    private int $maxMemory;

    public function __construct(int $maxMemory = 2_097_152)
    {
        $this->maxMemory = $maxMemory;
        $this->stringBackend = new StringStream();
        $this->resource = $this->stringBackend->getResource();
    }

    public function add(mixed $data, bool $reset = false): void
    {
        if ($this->stringBackend !== null && is_string($data)) {
            if ((strlen($data) + $this->stringBackend->length()) < $this->maxMemory) {
                $this->stringBackend->add($data, $reset);
                return;
            }

            $pos = $this->stringBackend->pos();
            $contents = (string) $this->stringBackend;
            $this->stringBackend = null;

            $temp = new Temp($this->maxMemory);
            $this->resource = $temp->getResource();
            fwrite($this->resource, $contents);
            $this->seek($pos, false);
        }

        parent::add($data, $reset);
    }

    public function isUsingTempStream(): bool
    {
        return $this->stringBackend === null;
    }

    public function close(): void
    {
        if ($this->stringBackend !== null) {
            $this->stringBackend->close();
            $this->stringBackend = null;
        }
        parent::close();
    }

    public function __clone()
    {
        if ($this->stringBackend !== null) {
            $this->stringBackend = clone $this->stringBackend;
            $this->resource = $this->stringBackend->getResource();
        } else {
            parent::__clone();
        }
    }
}
