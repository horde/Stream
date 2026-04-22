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

use Horde_Stream;

abstract class AbstractStream implements StreamInterface
{
    /** @var resource */
    protected mixed $resource;

    public bool $utf8Char = false;

    public function __toString(): string
    {
        $this->rewind();
        return $this->substring();
    }

    public function add(mixed $data, bool $reset = false): void
    {
        if ($reset) {
            $pos = $this->pos();
        }

        if (is_resource($data)) {
            $dpos = ftell($data);
            while (!feof($data)) {
                $this->add(fread($data, 8192));
            }
            fseek($data, $dpos);
        } elseif ($data instanceof StreamInterface || $data instanceof Horde_Stream) {
            $dpos = $data->pos();
            while (!$data->eof()) {
                $this->add($data->substring(0, 65536));
            }
            $data->seek($dpos, false);
        } else {
            fwrite($this->resource, $data);
        }

        if ($reset) {
            $this->seek($pos, false);
        }
    }

    /**
     * @throws StreamException
     */
    public function length(bool $utf8 = false): int
    {
        $pos = $this->pos();

        if ($utf8 && $this->utf8Char) {
            $this->rewind();
            $len = 0;
            while ($this->getChar() !== false) {
                ++$len;
            }
        } else {
            if (fseek($this->resource, 0, SEEK_END) !== 0) {
                throw new StreamException('Failed to seek to end of stream.');
            }
            $len = $this->pos();
        }

        $this->seek($pos, false);

        return $len;
    }

    public function getToChar(string $end, bool $all = true): string
    {
        if (($len = strlen($end)) === 1) {
            $out = '';
            do {
                if (($tmp = stream_get_line($this->resource, 8192, $end)) === false) {
                    return $out;
                }

                $out .= $tmp;
                if ((strlen($tmp) < 8192) || ($this->peek(-1) == $end)) {
                    break;
                }
            } while (true);
        } else {
            $res = $this->search($end);

            if (is_null($res)) {
                return $this->substring();
            }

            $out = substr($this->getString(null, $res + $len - 1), 0, $len * -1);
        }

        if ($all) {
            while ($this->peek($len) == $end) {
                $this->seek($len);
            }
        }

        return $out;
    }

    public function peek(int $length = 1): string
    {
        $out = '';

        for ($i = 0; $i < $length; ++$i) {
            if (($c = $this->getChar()) === false) {
                break;
            }
            $out .= $c;
        }

        $this->seek(strlen($out) * -1);

        return $out;
    }

    public function search(string $char, bool $reverse = false, bool $reset = true): ?int
    {
        $found_pos = null;

        if ($len = strlen($char)) {
            $pos = $this->pos();
            $single_char = ($len === 1);

            do {
                if ($reverse) {
                    for ($i = $pos - 1; $i >= 0; --$i) {
                        $this->seek($i, false);
                        $c = $this->peek();
                        if ($c == ($single_char ? $char : substr($char, 0, strlen($c)))) {
                            $found_pos = $i;
                            break;
                        }
                    }
                } else {
                    $fgetc = ($single_char && !$this->utf8Char);

                    while (($c = ($fgetc ? fgetc($this->resource) : $this->getChar())) !== false) {
                        if ($c == ($single_char ? $char : substr($char, 0, strlen($c)))) {
                            $found_pos = $this->pos() - ($single_char ? 1 : strlen($c));
                            break;
                        }
                    }
                }

                if ($single_char
                    || is_null($found_pos)
                    || ($this->getString($found_pos, $found_pos + $len - 1) == $char)) {
                    break;
                }

                $this->seek($found_pos + ($reverse ? 0 : 1), false);
                $found_pos = null;
            } while (true);

            $this->seek(
                ($reset || is_null($found_pos)) ? $pos : $found_pos,
                false
            );
        }

        return $found_pos;
    }

    public function getString(?int $start = null, ?int $end = null): string
    {
        if ($start !== null && $start >= 0) {
            $this->seek($start, false);
            $start = 0;
        }

        if ($end === null) {
            $len = null;
        } else {
            $end = ($end >= 0)
                ? $end - $this->pos() + 1
                : $this->length() - $this->pos() + $end;
            $len = max($end, 0);
        }

        return $this->substring($start ?? 0, $len);
    }

    public function substring(int $start = 0, ?int $length = null, bool $char = false): string
    {
        if ($start !== 0) {
            $this->seek($start, true, $char);
        }

        $out = '';
        $to_end = is_null($length);

        if ($char
            && $this->utf8Char
            && !$to_end
            && ($length >= 0)
            && ($length < ($this->length() - $this->pos()))) {
            while ($length-- && (($c = $this->getChar()) !== false)) {
                $out .= $c;
            }
            return $out;
        }

        if (!$to_end && ($length < 0)) {
            $pos = $this->pos();
            $this->end();
            $this->seek($length, true, $char);
            $length = $this->pos() - $pos;
            $this->seek($pos, false);
            if ($length < 0) {
                return '';
            }
        }

        while (!feof($this->resource) && ($to_end || $length)) {
            $read = fread($this->resource, $to_end ? 16384 : $length);
            $out .= $read;
            if (!$to_end) {
                $length -= strlen($read);
            }
        }

        return $out;
    }

    public function getEOL(): ?string
    {
        $pos = $this->pos();

        $this->rewind();
        $pos2 = $this->search("\n", false, false);
        if ($pos2) {
            $this->seek(-1);
            $eol = ($this->getChar() == "\r")
                ? "\r\n"
                : "\n";
        } else {
            $eol = is_null($pos2)
                ? null
                : "\n";
        }

        $this->seek($pos, false);

        return $eol;
    }

    /**
     * @throws StreamException
     */
    public function getChar(): string|false
    {
        $char = fgetc($this->resource);
        if ($char === false || !$this->utf8Char) {
            return $char;
        }

        $c = ord($char);
        if ($c < 0x80) {
            return $char;
        }

        if ($c < 0xe0) {
            $n = 1;
        } elseif ($c < 0xf0) {
            $n = 2;
        } elseif ($c < 0xf8) {
            $n = 3;
        } else {
            throw new StreamException('Invalid UTF-8 lead byte.');
        }

        for ($i = 0; $i < $n; ++$i) {
            if (($c = fgetc($this->resource)) === false) {
                throw new StreamException('Truncated UTF-8 byte sequence.');
            }
            $char .= $c;
        }

        return $char;
    }

    /**
     * @throws StreamException
     */
    public function pos(): int
    {
        $pos = ftell($this->resource);
        if ($pos === false) {
            throw new StreamException('Failed to get stream position.');
        }
        return $pos;
    }

    /**
     * @throws StreamException
     */
    public function rewind(): void
    {
        if (!rewind($this->resource)) {
            throw new StreamException('Failed to rewind stream.');
        }
    }

    /**
     * @throws StreamException
     */
    public function seek(int $offset, bool $fromCurrent = true, bool $char = false): void
    {
        if ($offset === 0) {
            if (!$fromCurrent) {
                $this->rewind();
            }
            return;
        }

        if ($offset < 0) {
            if (!$fromCurrent) {
                return;
            }
            if (abs($offset) > $this->pos()) {
                $this->rewind();
                return;
            }
        }

        if ($char && $this->utf8Char) {
            if ($offset > 0) {
                if (!$fromCurrent) {
                    $this->rewind();
                }
                do {
                    $this->getChar();
                } while (--$offset);
            } else {
                $pos = $this->pos();
                $offset = abs($offset);
                while ($pos-- && $offset) {
                    fseek($this->resource, -1, SEEK_CUR);
                    if ((ord($this->peek()) & 0xC0) != 0x80) {
                        --$offset;
                    }
                }
            }
            return;
        }

        if (fseek($this->resource, $offset, $fromCurrent ? SEEK_CUR : SEEK_SET) !== 0) {
            if ($fromCurrent && $offset > 0) {
                $this->end();
                return;
            }
            throw new StreamException('Failed to seek in stream.');
        }
    }

    /**
     * @throws StreamException
     */
    public function end(int $offset = 0): void
    {
        if (fseek($this->resource, $offset, SEEK_END) !== 0) {
            throw new StreamException('Failed to seek to end of stream.');
        }
    }

    public function eof(): bool
    {
        return feof($this->resource);
    }

    public function close(): void
    {
        if (is_resource($this->resource)) {
            fclose($this->resource);
        }
    }

    /**
     * @return resource
     */
    public function getResource(): mixed
    {
        return $this->resource;
    }

    public function __clone()
    {
        $data = strval($this);
        $this->resource = fopen('php://temp', 'r+');
        fwrite($this->resource, $data);
    }

    public function __serialize(): array
    {
        $pos = $this->pos();
        return [
            'data' => strval($this),
            'pos' => $pos,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->resource = fopen('php://temp', 'r+');
        fwrite($this->resource, $data['data']);
        $this->seek($data['pos'], false);
    }
}
