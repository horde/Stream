<?php

declare(strict_types=1);

namespace Horde\Stream\Test\Unit;

use Horde\Stream\AbstractStream;
use Horde\Stream\StreamException;
use Horde\Stream\StreamInterface;
use Horde\Stream\Temp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Temp::class)]
#[CoversClass(AbstractStream::class)]
class TempStreamTest extends TestCase
{
    // -- Constructor --

    public function testDefaultConstructor(): void
    {
        $stream = new Temp();
        $this->assertInstanceOf(StreamInterface::class, $stream);
        $this->assertEquals(0, $stream->pos());
    }

    public function testMaxMemoryParameter(): void
    {
        $stream = new Temp(maxMemory: 1024);
        $stream->add('hello');
        $this->assertEquals('hello', (string) $stream);
    }

    // -- add + read --

    public function testAddStringAndReadBack(): void
    {
        $stream = new Temp();
        $stream->add('hello world');

        $this->assertEquals(11, $stream->length());
        $this->assertEquals('hello world', $stream->getString(0));
    }

    public function testAddWithResetPreservesPosition(): void
    {
        $stream = new Temp();
        $stream->add('abc');

        $stream->add('xyz', reset: true);

        $this->assertEquals(3, $stream->pos());
        $this->assertEquals('abcxyz', (string) $stream);
    }

    public function testAddFromResource(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'hello world');
        fseek($resource, 0);

        $stream = new Temp();
        $stream->add($resource);

        $this->assertEquals(11, $stream->length());
        $this->assertEquals('hello world', $stream->getString(0));
        $this->assertEquals(0, ftell($resource));

        fclose($resource);
    }

    public function testAddFromStreamInterface(): void
    {
        $source = new Temp();
        $source->add('foo');
        $source->rewind();

        $target = new Temp();
        $target->add($source, reset: true);

        $this->assertEquals(3, $target->length());
        $this->assertEquals('foo', $target->getString(0));
        $this->assertEquals(0, $source->pos());
    }

    // -- getString --

    public function testGetStringFromStart(): void
    {
        $stream = new Temp();
        $stream->add('A B C');

        $this->assertEquals('A B C', $stream->getString(0));
    }

    public function testGetStringFromCurrentPosition(): void
    {
        $stream = new Temp();
        $stream->add('A B C');
        $stream->seek(2, false);

        $this->assertEquals('B C', $stream->getString());
    }

    public function testGetStringWithNegativeEnd(): void
    {
        $stream = new Temp();
        $stream->add('A B C');
        $stream->seek(2, false);

        $this->assertEquals('B', $stream->getString(null, -2));
    }

    public function testGetStringAtEndWithNegativeEnd(): void
    {
        $stream = new Temp();
        $stream->add('A B C');
        $stream->end();

        $this->assertEquals('', $stream->getString(null, -1));
    }

    // -- substring --

    public function testSubstringWithOffsetAndLength(): void
    {
        $stream = new Temp();
        $stream->add('1234567890');
        $stream->rewind();

        $this->assertEquals('123', $stream->substring(0, 3));
        $this->assertEquals('456', $stream->substring(0, 3));
        $this->assertEquals('7890', $stream->substring(0));
    }

    public function testSubstringWithPositiveStartOffset(): void
    {
        $stream = new Temp();
        $stream->add('1234567890');
        $stream->rewind();

        $this->assertEquals('456', $stream->substring(3, 3));
    }

    public function testSubstringWithNegativeStartOffset(): void
    {
        $stream = new Temp();
        $stream->add('1234567890');
        $stream->rewind();

        $this->assertEquals('123', $stream->substring(-3, 3));
    }

    public function testSubstringWithNegativeLength(): void
    {
        $stream = new Temp();
        $stream->add('1234567890');
        $stream->rewind();

        $this->assertEquals('1234567', $stream->substring(0, -3));
    }

    public function testSubstringNegativeLengthLargerThanRemaining(): void
    {
        $stream = new Temp();
        $stream->add('1234567890');
        $stream->seek(8, false);

        $this->assertEquals('', $stream->substring(0, -5));
    }

    // -- pos / rewind / seek / end / eof --

    public function testPosReturnsCurrentPosition(): void
    {
        $stream = new Temp();
        $stream->add('123');

        $this->assertEquals(3, $stream->pos());
    }

    public function testRewindMovesToStart(): void
    {
        $stream = new Temp();
        $stream->add('123');

        $stream->rewind();
        $this->assertEquals(0, $stream->pos());
    }

    public function testSeekForwardFromCurrent(): void
    {
        $stream = new Temp();
        $stream->add('12345');
        $stream->rewind();

        $stream->seek(3);
        $this->assertEquals(3, $stream->pos());
    }

    public function testSeekBackwardFromCurrent(): void
    {
        $stream = new Temp();
        $stream->add('123');

        $stream->seek(-2);
        $this->assertEquals(1, $stream->pos());
    }

    public function testSeekFromAbsolutePosition(): void
    {
        $stream = new Temp();
        $stream->add('12345');

        $stream->seek(1, false);
        $this->assertEquals(1, $stream->pos());
    }

    public function testSeekPastStartClampsToZero(): void
    {
        $stream = new Temp();
        $stream->add('123');

        $stream->seek(-100);
        $this->assertEquals(0, $stream->pos());
    }

    public function testSeekZeroFromStartRewinds(): void
    {
        $stream = new Temp();
        $stream->add('123');

        $stream->seek(0, false);
        $this->assertEquals(0, $stream->pos());
    }

    public function testEndMovesToEnd(): void
    {
        $stream = new Temp();
        $stream->add('123');
        $stream->rewind();

        $stream->end();
        $this->assertEquals(3, $stream->pos());
    }

    public function testEndWithNegativeOffset(): void
    {
        $stream = new Temp();
        $stream->add('123');
        $stream->rewind();

        $stream->end(-1);
        $this->assertEquals(2, $stream->pos());
    }

    public function testEofFalseBeforeEnd(): void
    {
        $stream = new Temp();
        $stream->add('123');
        $stream->rewind();

        $this->assertFalse($stream->eof());
    }

    public function testEofTrueAfterReadingPastEnd(): void
    {
        $stream = new Temp();
        $stream->add('123');

        $stream->getChar();
        $this->assertTrue($stream->eof());
    }

    // -- getChar / peek --

    public function testGetCharReturnsSingleByte(): void
    {
        $stream = new Temp();
        $stream->add('ABC');
        $stream->rewind();

        $this->assertEquals('A', $stream->getChar());
        $this->assertEquals('B', $stream->getChar());
        $this->assertEquals('C', $stream->getChar());
    }

    public function testGetCharReturnsFalseAtEof(): void
    {
        $stream = new Temp();
        $stream->add('A');

        $this->assertFalse($stream->getChar());
    }

    public function testPeekDoesNotAdvancePosition(): void
    {
        $stream = new Temp();
        $stream->add('ABC');
        $stream->rewind();

        $this->assertEquals('A', $stream->peek());
        $this->assertEquals('A', $stream->peek());
        $this->assertEquals(0, $stream->pos());
    }

    public function testPeekMultipleChars(): void
    {
        $stream = new Temp();
        $stream->add('ABC');
        $stream->rewind();

        $this->assertEquals('AB', $stream->peek(2));
        $this->assertEquals(0, $stream->pos());
    }

    // -- search --

    public function testSearchSingleCharForward(): void
    {
        $stream = new Temp();
        $stream->add('0123456789');
        $stream->rewind();

        $this->assertEquals(5, $stream->search('5'));
        $this->assertEquals(0, $stream->pos());
    }

    public function testSearchSingleCharReverse(): void
    {
        $stream = new Temp();
        $stream->add('0123456789');
        $stream->end();

        $this->assertEquals(3, $stream->search('3', reverse: true));
    }

    public function testSearchMultiCharString(): void
    {
        $stream = new Temp();
        $stream->add('0123456789');
        $stream->rewind();

        $this->assertEquals(3, $stream->search('34'));
    }

    public function testSearchWithResetFalseMovesPosition(): void
    {
        $stream = new Temp();
        $stream->add('0123456789');
        $stream->rewind();

        $this->assertEquals(5, $stream->search('5', reset: false));
        $this->assertEquals(5, $stream->pos());
    }

    public function testSearchNotFoundReturnsNull(): void
    {
        $stream = new Temp();
        $stream->add('0123456789');
        $stream->rewind();

        $this->assertNull($stream->search('X'));
    }

    public function testSearchNotFoundFromMiddle(): void
    {
        $stream = new Temp();
        $stream->add('0123456789');
        $stream->rewind();

        $stream->search('5', reset: false);
        $this->assertNull($stream->search('3', reset: false));
    }

    // -- getToChar --

    public function testGetToCharSingleCharDelimiter(): void
    {
        $stream = new Temp();
        $stream->add('A B');
        $stream->rewind();

        $this->assertEquals('A', $stream->getToChar(' '));
        $this->assertEquals('B', $stream->getToChar(' '));
    }

    public function testGetToCharMultiCharDelimiter(): void
    {
        $stream = new Temp();
        $stream->add("ABC\r\nDEF");
        $stream->rewind();

        $this->assertEquals('ABC', $stream->getToChar("\r\n"));
    }

    public function testGetToCharStripsRepeatedDelimiters(): void
    {
        $stream = new Temp();
        $stream->add('A  B');
        $stream->rewind();

        $this->assertEquals('A', $stream->getToChar(' ', all: true));
        $this->assertEquals('B', $stream->getToChar(' ', all: true));
    }

    public function testGetToCharAllFalseStopsAtFirst(): void
    {
        $stream = new Temp();
        $stream->add("A\n\n\nB");
        $stream->rewind();

        $this->assertEquals('A', $stream->getToChar("\n", all: false));
        $this->assertEquals('', $stream->getToChar("\n", all: false));
        $this->assertEquals('', $stream->getToChar("\n", all: false));
        $this->assertEquals('B', $stream->getToChar("\n", all: false));
    }

    public function testGetToCharDelimiterNotFound(): void
    {
        $stream = new Temp();
        $stream->add('ABCDEF');
        $stream->rewind();

        $this->assertEquals('ABCDEF', $stream->getToChar('XY'));
    }

    public function testGetToCharLongStringAcrossBufferBoundary(): void
    {
        $long = str_repeat('A', 15000);
        $stream = new Temp();
        $stream->add($long . "B\n");
        $stream->rewind();

        $this->assertEquals($long, $stream->getToChar('B', all: false));
    }

    // -- EOL detection --

    public function testGetEolDetectsLf(): void
    {
        $stream = new Temp();
        $stream->add("123\n456");

        $this->assertEquals("\n", $stream->getEOL());
    }

    public function testGetEolDetectsCrlf(): void
    {
        $stream = new Temp();
        $stream->add("123\r\n456");

        $this->assertEquals("\r\n", $stream->getEOL());
    }

    public function testGetEolReturnsNullWhenNone(): void
    {
        $stream = new Temp();
        $stream->add('123456');

        $this->assertNull($stream->getEOL());
    }

    public function testGetEolDetectsLeadingLf(): void
    {
        $stream = new Temp();
        $stream->add("\n123456\n");

        $this->assertEquals("\n", $stream->getEOL());
    }

    // -- UTF-8 mode --

    public function testUtf8CharPropertyDefaultsFalse(): void
    {
        $stream = new Temp();
        $this->assertFalse($stream->utf8Char);
    }

    public function testUtf8CharPropertyCanBeSet(): void
    {
        $stream = new Temp();
        $stream->utf8Char = true;
        $this->assertTrue($stream->utf8Char);
    }

    public function testGetCharReadsTwoByte(): void
    {
        $stream = new Temp();
        $stream->add('Aö');
        $stream->rewind();
        $stream->utf8Char = true;

        $this->assertEquals('A', $stream->getChar());
        $this->assertEquals('ö', $stream->getChar());
    }

    public function testGetCharReadsThreeByte(): void
    {
        $stream = new Temp();
        $stream->add('A€B');
        $stream->rewind();
        $stream->utf8Char = true;

        $this->assertEquals('A', $stream->getChar());
        $this->assertEquals('€', $stream->getChar());
        $this->assertEquals('B', $stream->getChar());
    }

    public function testGetCharReadsFourByte(): void
    {
        $stream = new Temp();
        $stream->add("A\xF0\x90\x8D\x88B");
        $stream->rewind();
        $stream->utf8Char = true;

        $this->assertEquals('A', $stream->getChar());
        $this->assertEquals("\xF0\x90\x8D\x88", $stream->getChar());
        $this->assertEquals('B', $stream->getChar());
    }

    public function testLengthUtf8CountsCharacters(): void
    {
        $stream = new Temp();
        $stream->add('Aönön');
        $stream->utf8Char = true;

        $this->assertEquals(7, $stream->length());
        $this->assertEquals(5, $stream->length(utf8: true));
    }

    public function testSeekUtf8CharMode(): void
    {
        $stream = new Temp();
        $stream->add('Aönön');
        $stream->utf8Char = true;

        $stream->seek(2, false, char: true);
        $this->assertEquals(3, $stream->pos());

        $stream->seek(2, true, char: true);
        $this->assertEquals(6, $stream->pos());

        $stream->seek(-2, true, char: true);
        $this->assertEquals(3, $stream->pos());
    }

    public function testSubstringUtf8CharMode(): void
    {
        $stream = new Temp();
        $stream->add('AönönAönön');
        $stream->utf8Char = true;
        $stream->rewind();

        $this->assertEquals('Aön', $stream->substring(0, 3, char: true));
    }

    public function testGetCharThrowsOnInvalidLeadByte(): void
    {
        $stream = new Temp();
        $stream->add("\xFC");
        $stream->rewind();
        $stream->utf8Char = true;

        $this->expectException(StreamException::class);
        $stream->getChar();
    }

    public function testGetCharThrowsOnTruncatedSequence(): void
    {
        $stream = new Temp();
        $stream->add("\xC3");
        $stream->rewind();
        $stream->utf8Char = true;

        $this->expectException(StreamException::class);
        $stream->getChar();
    }

    // -- Lifecycle --

    public function testToStringReturnsFullContent(): void
    {
        $stream = new Temp();
        $stream->add('hello');

        $this->assertEquals('hello', (string) $stream);
    }

    public function testGetResourceReturnsResource(): void
    {
        $stream = new Temp();
        $this->assertIsResource($stream->getResource());
    }

    public function testCloneProducesIndependentCopy(): void
    {
        $stream = new Temp();
        $stream->add('123');

        $clone = clone $stream;
        $stream->close();

        $this->assertEquals('123', (string) $clone);
    }

    public function testSerializeRoundTrip(): void
    {
        $stream = new Temp();
        $stream->add('hello');
        $stream->seek(2, false);

        $restored = unserialize(serialize($stream));

        $this->assertEquals(2, $restored->pos());
        $this->assertEquals('hello', $restored->getString(0));
    }

    public function testCloseClosesResource(): void
    {
        $stream = new Temp();
        $resource = $stream->getResource();

        $stream->close();
        $this->assertFalse(is_resource($resource));
    }

    // -- Large stream --

    public function testLargeStreamAddAndRead(): void
    {
        $stream = new Temp();
        $stream->add(str_repeat('1234567890', 10000));
        $stream->rewind();

        $this->assertEquals(100000, $stream->length());

        $stream2 = new Temp();
        $stream2->add($stream);
        $this->assertEquals(100000, $stream2->length());
    }
}
