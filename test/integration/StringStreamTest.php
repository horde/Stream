<?php

declare(strict_types=1);

namespace Horde\Stream\Test\Integration;

use Horde\Stream\AbstractStream;
use Horde\Stream\StringStream;
use Horde\Stream\StreamInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StringStream::class)]
#[CoversClass(AbstractStream::class)]
class StringStreamTest extends TestCase
{
    // -- Constructor --

    public function testDefaultConstructorCreatesEmptyStream(): void
    {
        $stream = new StringStream();
        $this->assertInstanceOf(StreamInterface::class, $stream);
        $this->assertEquals(0, $stream->length());
    }

    public function testConstructorWithInitialData(): void
    {
        $stream = new StringStream('hello');
        $this->assertEquals(5, $stream->length());
        $this->assertEquals('hello', $stream->getString(0));
    }

    public function testGetResourceReturnsResource(): void
    {
        $stream = new StringStream();
        $this->assertIsResource($stream->getResource());
    }

    // -- Read/write --

    public function testAddAndReadBack(): void
    {
        $stream = new StringStream();
        $stream->add('hello world');

        $this->assertEquals(11, $stream->length());
        $this->assertEquals('hello world', $stream->getString(0));
    }

    public function testMultipleAdds(): void
    {
        $stream = new StringStream();
        $stream->add('hello');
        $stream->add(' world');

        $this->assertEquals('hello world', (string) $stream);
    }

    public function testSeekAndRead(): void
    {
        $stream = new StringStream('ABCDEF');
        $stream->seek(2, false);

        $this->assertEquals('CDEF', $stream->getString());
    }

    // -- Lifecycle --

    public function testToStringReturnsFullContent(): void
    {
        $stream = new StringStream('test');
        $this->assertEquals('test', (string) $stream);
    }

    public function testCloneProducesIndependentCopy(): void
    {
        $stream = new StringStream();
        $stream->add('data');

        $clone = clone $stream;
        $stream->close();

        $this->assertEquals('data', (string) $clone);
    }

    public function testSerializeRoundTrip(): void
    {
        $stream = new StringStream();
        $stream->add('hello');
        $stream->seek(3, false);

        $restored = unserialize(serialize($stream));

        $this->assertEquals(3, $restored->pos());
        $this->assertEquals('hello', $restored->getString(0));
    }
}
