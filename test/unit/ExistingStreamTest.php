<?php

declare(strict_types=1);

namespace Horde\Stream\Test\Unit;

use Horde\Stream\AbstractStream;
use Horde\Stream\Existing;
use Horde\Stream\StreamInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Existing::class)]
#[CoversClass(AbstractStream::class)]
class ExistingStreamTest extends TestCase
{
    // -- Constructor --

    public function testWrapsExistingResource(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'hello');
        fseek($resource, 0);

        $stream = new Existing($resource);
        $this->assertInstanceOf(StreamInterface::class, $stream);
        $this->assertEquals('hello', $stream->getString(0));

        fclose($resource);
    }

    public function testConstructorRejectsNonResource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Existing('not a resource');
    }

    public function testConstructorRejectsClosedResource(): void
    {
        $resource = fopen('php://temp', 'r+');
        fclose($resource);

        $this->expectException(InvalidArgumentException::class);
        new Existing($resource);
    }

    public function testGetResourceReturnsOriginal(): void
    {
        $resource = fopen('php://temp', 'r+');
        $stream = new Existing($resource);

        $this->assertSame($resource, $stream->getResource());

        fclose($resource);
    }

    // -- Read/write through wrapper --

    public function testAddAndReadBack(): void
    {
        $resource = fopen('php://temp', 'r+');
        $stream = new Existing($resource);

        $stream->add('hello world');
        $this->assertEquals(11, $stream->length());
        $this->assertEquals('hello world', $stream->getString(0));

        fclose($resource);
    }

    public function testPreservesExistingContent(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'existing');

        $stream = new Existing($resource);
        $stream->add(' appended');

        $this->assertEquals('existing appended', $stream->getString(0));

        fclose($resource);
    }

    // -- Lifecycle --

    public function testCloseClosesResource(): void
    {
        $resource = fopen('php://temp', 'r+');
        $stream = new Existing($resource);

        $stream->close();
        $this->assertFalse(is_resource($resource));
    }

    public function testToStringReturnsFullContent(): void
    {
        $resource = fopen('php://temp', 'r+');
        $stream = new Existing($resource);
        $stream->add('test data');

        $this->assertEquals('test data', (string) $stream);

        fclose($resource);
    }
}
