<?php

declare(strict_types=1);

namespace Horde\Stream\Test\Unit;

use Horde\Stream\AbstractStream;
use Horde\Stream\StreamInterface;
use Horde\Stream\TempString;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TempString::class)]
#[CoversClass(AbstractStream::class)]
class TempStringStreamTest extends TestCase
{
    // -- Constructor & initial state --

    public function testDefaultConstructor(): void
    {
        $stream = new TempString();
        $this->assertInstanceOf(StreamInterface::class, $stream);
        $this->assertEquals(0, $stream->pos());
    }

    public function testStartsWithStringBackend(): void
    {
        $stream = new TempString();
        $this->assertFalse($stream->isUsingTempStream());
    }

    public function testGetResourceReturnsResource(): void
    {
        $stream = new TempString();
        $this->assertIsResource($stream->getResource());
    }

    // -- Before spill (string backend) --

    public function testSmallAddStaysInStringBackend(): void
    {
        $stream = new TempString();
        $stream->add('hello');

        $this->assertFalse($stream->isUsingTempStream());
        $this->assertEquals('hello', (string) $stream);
    }

    public function testReadWriteSeekBeforeSpill(): void
    {
        $stream = new TempString();
        $stream->add('ABCDEF');
        $stream->seek(2, false);

        $this->assertEquals('CDEF', $stream->getString());
        $this->assertFalse($stream->isUsingTempStream());
    }

    public function testMultipleSmallAddsBeforeSpill(): void
    {
        $stream = new TempString(maxMemory: 1024);
        $stream->add('aaa');
        $stream->add('bbb');

        $this->assertFalse($stream->isUsingTempStream());
        $this->assertEquals('aaabbb', (string) $stream);
    }

    // -- Spill transition --

    public function testSpillOnExceedingMaxMemory(): void
    {
        $stream = new TempString(maxMemory: 10);
        $stream->add('12345');
        $this->assertFalse($stream->isUsingTempStream());

        $stream->add('67890EXTRA');
        $this->assertTrue($stream->isUsingTempStream());
    }

    public function testDataIntegrityAcrossSpill(): void
    {
        $stream = new TempString(maxMemory: 10);
        $stream->add('ABCDE');
        $stream->add('FGHIJKLMNO');

        $this->assertTrue($stream->isUsingTempStream());
        $this->assertEquals('ABCDEFGHIJKLMNO', (string) $stream);
    }

    public function testPositionPreservedAcrossSpill(): void
    {
        $stream = new TempString(maxMemory: 20);
        $stream->add('hello');

        $stream->add(str_repeat('X', 30), reset: true);

        $this->assertTrue($stream->isUsingTempStream());
        $this->assertEquals(5, $stream->pos());
        $this->assertEquals('hello' . str_repeat('X', 30), (string) $stream);
    }

    public function testSubsequentWritesAfterSpill(): void
    {
        $stream = new TempString(maxMemory: 10);
        $stream->add(str_repeat('A', 20));
        $this->assertTrue($stream->isUsingTempStream());

        $stream->add('MORE');
        $this->assertEquals(24, $stream->length());
    }

    // -- maxMemory parameter --

    public function testCustomMaxMemory(): void
    {
        $stream = new TempString(maxMemory: 5);
        $stream->add('1234');
        $this->assertFalse($stream->isUsingTempStream());

        $stream->add('56');
        $this->assertTrue($stream->isUsingTempStream());
    }

    public function testMaxMemoryOneSpillsImmediately(): void
    {
        $stream = new TempString(maxMemory: 1);
        $stream->add('AB');

        $this->assertTrue($stream->isUsingTempStream());
        $this->assertEquals('AB', (string) $stream);
    }

    // -- Non-string data bypasses string backend --

    public function testAddResourceTriggersParentAdd(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'from resource');
        fseek($resource, 0);

        $stream = new TempString(maxMemory: 1024);
        $stream->add($resource);

        $this->assertEquals('from resource', $stream->getString(0));

        fclose($resource);
    }

    // -- Lifecycle --

    public function testCloneBeforeSpillProducesIndependentCopy(): void
    {
        $stream = new TempString();
        $stream->add('data');

        $clone = clone $stream;
        $stream->close();

        $this->assertFalse($clone->isUsingTempStream());
        $this->assertEquals('data', (string) $clone);
    }

    public function testCloneAfterSpillProducesIndependentCopy(): void
    {
        $stream = new TempString(maxMemory: 5);
        $stream->add('too long for string backend');

        $clone = clone $stream;
        $stream->close();

        $this->assertTrue($clone->isUsingTempStream());
        $this->assertEquals('too long for string backend', (string) $clone);
    }

    public function testCloseBeforeSpill(): void
    {
        $stream = new TempString();
        $stream->add('data');
        $resource = $stream->getResource();

        $stream->close();
        $this->assertFalse(is_resource($resource));
    }

    public function testCloseAfterSpill(): void
    {
        $stream = new TempString(maxMemory: 1);
        $stream->add('data');
        $resource = $stream->getResource();

        $stream->close();
        $this->assertFalse(is_resource($resource));
    }

    public function testToString(): void
    {
        $stream = new TempString();
        $stream->add('hello');
        $this->assertEquals('hello', (string) $stream);
    }
}
