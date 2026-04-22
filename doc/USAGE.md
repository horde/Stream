# Horde\Stream Usage Guide

## Overview

Horde\Stream is a managed stream abstraction designed for parsing and
constructing structured byte data. It sits below HTTP-level abstractions
like PSR-7 and above raw `fread`/`fwrite` calls, providing the operations
that protocol parsers and MIME processors need.

## Creating streams

### Temp -- general-purpose buffer

The most common entry point. Backed by `php://temp`, data lives in memory
until PHP's internal threshold triggers a spill to a temporary file.

```php
use Horde\Stream\Temp;

// Default: PHP manages the memory/disk threshold
$stream = new Temp();

// Custom: spill to disk after 512 KB
$stream = new Temp(maxMemory: 524288);
```

### Existing -- wrap an open resource

Wraps a socket, file handle, pipe or any PHP stream resource. The caller
retains ownership of the resource lifecycle unless `close()` is called on
the wrapper.

```php
use Horde\Stream\Existing;

$socket = fsockopen('imap.example.com', 993);
$stream = new Existing($socket);

// Read the server greeting
$greeting = $stream->getToChar("\r\n", all: false);
```

Throws `InvalidArgumentException` if the argument is not an open stream
resource.

### StringStream -- string-backed

Uses `horde/stream_wrapper` to expose a PHP string as a seekable stream.
Useful when data is already in memory and you want stream operations without
copying to `php://temp`.

```php
use Horde\Stream\StringStream;

$stream = new StringStream("From: user@example.com\r\nSubject: Test\r\n\r\n");
```

Requires `horde/stream_wrapper` (`composer require horde/stream_wrapper`).

### TempString -- auto-spill hybrid

Starts with a fast string backend and transparently switches to `php://temp`
when accumulated data exceeds `maxMemory`. Ideal when most streams are small
but occasional large payloads must be handled without blowing memory.

```php
use Horde\Stream\TempString;

// Spill to temp after 2 MB (default)
$stream = new TempString();

// Spill after 64 KB
$stream = new TempString(maxMemory: 65536);

$stream->add($smallHeader);  // stays in string backend
$stream->add($largeBody);    // triggers spill if total exceeds maxMemory

// Check which backend is active
if ($stream->isUsingTempStream()) {
    // data spilled to php://temp
}
```

## Writing data

`add()` accepts strings, PHP resources or other stream objects. Data is
appended at the current position.

```php
// String
$stream->add("EHLO example.com\r\n");

// Another Horde\Stream
$stream->add($otherStream);

// Raw PHP resource
$stream->add($fileHandle);

// Write and restore cursor position
$stream->add($data, reset: true);
```

With `reset: true`, the cursor returns to its position before the write.
This is useful when building a stream incrementally while another consumer
reads from the beginning.

## Reading data

### Character-level

```php
// Read one byte (or one UTF-8 character when utf8Char is true)
$char = $stream->getChar();  // string|false

// Peek without advancing the cursor
$next = $stream->peek();       // next 1 byte
$next3 = $stream->peek(3);    // next 3 bytes
```

`getChar()` returns `false` at EOF -- it does not throw. Only malformed
UTF-8 sequences throw `StreamException`.

### Range reads

```php
// From current position to end
$rest = $stream->substring();

// 100 bytes from current position
$chunk = $stream->substring(0, 100);

// Skip 10 bytes ahead, then read 50
$chunk = $stream->substring(10, 50);

// From absolute byte offset to end
$all = $stream->getString(0);

// From absolute position 10 to absolute position 19 (inclusive)
$slice = $stream->getString(10, 19);
```

### Delimiter scanning

`getToChar()` reads from the current position up to (but not including) a
delimiter string, then advances the cursor past it.

```php
// Read one line (stops at \n, skips the \n)
$line = $stream->getToChar("\n", all: false);

// Read until CRLF
$line = $stream->getToChar("\r\n");

// Read until space, skip consecutive spaces
$token = $stream->getToChar(' ', all: true);

// Read until space, stop at first (preserving empty tokens)
$token = $stream->getToChar(' ', all: false);
```

When the delimiter is not found, `getToChar()` returns all remaining data.

### Searching

`search()` finds the byte offset of a substring without consuming data.

```php
// Forward search from current position
$pos = $stream->search('{');         // int|null

// Reverse search from current position
$pos = $stream->search('}', reverse: true);

// Search and move cursor to found position
$pos = $stream->search('LITERAL', reset: false);

// Multi-byte search
$pos = $stream->search("\r\n.\r\n");
```

Returns `null` when the target is not found.

## Positioning

```php
$stream->rewind();              // move to byte 0
$stream->end();                 // move to end
$stream->end(-5);               // move to 5 bytes before end
$stream->seek(10);              // forward 10 bytes from current
$stream->seek(-3);              // back 3 bytes from current
$stream->seek(100, fromCurrent: false);  // absolute byte 100

$pos = $stream->pos();          // current byte offset
$atEnd = $stream->eof();        // true if past last byte
$size = $stream->length();      // total byte count
```

All positioning methods throw `StreamException` on failure. Seeking before
byte 0 clamps to 0 rather than throwing.

## UTF-8 character mode

Set `utf8Char` to `true` to make character-oriented methods work in
UTF-8 characters instead of raw bytes.

```php
$stream = new Temp();
$stream->add('Aönön');
$stream->utf8Char = true;

$stream->rewind();
$stream->getChar();  // 'A' (1 byte)
$stream->getChar();  // 'ö' (2 bytes)

$stream->length();              // 7 (bytes)
$stream->length(utf8: true);   // 5 (characters)

$stream->rewind();
$stream->seek(2, fromCurrent: false, char: true);  // byte 3 (after 'A' + 'ö')

$stream->rewind();
$stream->substring(0, 3, char: true);  // 'Aön' (3 characters)
```

Invalid or truncated UTF-8 sequences cause `StreamException`.

## EOL detection

```php
$eol = $stream->getEOL();  // "\n", "\r\n" or null
```

Scans for the first newline and determines whether the stream uses LF or
CRLF line endings.

## Lifecycle

### Cloning

Cloning produces an independent copy. Modifying or closing the clone does
not affect the original.

```php
$copy = clone $stream;
$stream->close();
echo (string) $copy;  // still works
```

### Serialization

Streams support `serialize()` / `unserialize()`. Both data content and
cursor position are preserved.

```php
$frozen = serialize($stream);
$thawed = unserialize($frozen);
echo $thawed->pos();       // same as before serialize
echo (string) $thawed;     // same content
```

### Resource access

When you need the underlying PHP resource for functions like
`stream_filter_append()` or `stream_copy_to_stream()`:

```php
$resource = $stream->getResource();
stream_filter_append($resource, 'convert.base64-encode');
```

## Type-hinting with capability interfaces

`StreamInterface` composes three capability interfaces. Consumer code can
type-hint on the narrowest capability it needs:

```php
use Horde\Stream\Readable;
use Horde\Stream\Seekable;
use Horde\Stream\Writable;
use Horde\Stream\StreamInterface;

// Full access
function processMessage(StreamInterface $stream): void { ... }

// Read-only parser
function parseHeaders(Readable $stream): void { ... }

// Needs to scan and reposition
function findBoundary(Readable&Seekable $stream): ?int { ... }

// Write-only sink
function appendData(Writable $sink): void { ... }
```

## Horde\Stream vs PSR-7 streams

PSR-7 `Psr\Http\Message\StreamInterface` and `Horde\Stream\StreamInterface`
serve different layers of an application.

### Design goals

| | PSR-7 | Horde\Stream |
|---|-------|-------------|
| **Purpose** | HTTP message body transport | Protocol parsing and data construction |
| **Read model** | Bulk: `read($length)`, `getContents()` | Structured: `getToChar()`, `search()`, `peek()`, `getChar()` |
| **Write model** | `write($string)` | `add($string\|$resource\|$stream, reset: ...)` |
| **Capability query** | `isReadable()`, `isWritable()`, `isSeekable()` | Statically guaranteed by interface composition |
| **Error model** | `RuntimeException` on detached stream | `StreamException` on I/O failure; `false` on expected EOF |
| **UTF-8 awareness** | None | Character-level seek, read and length |
| **Clone / serialize** | Not defined | Built-in, position-preserving |
| **EOL detection** | None | `getEOL()` auto-detects LF vs CRLF |
| **Memory strategy** | Caller-managed | `TempString` auto-spill from string to temp |

### When to use which

**Use PSR-7 streams** when you are sending or receiving HTTP messages through
a PSR-18 client or PSR-15 middleware. The PSR-7 contract ensures
interoperability across HTTP libraries.

**Use Horde\Stream** when you are:

- **Parsing line-oriented protocols** (IMAP, SMTP, ManageSieve, POP3) where
  you need to read until a delimiter, search for markers and handle literal
  data chunks.
- **Processing MIME structures** where headers must be read line by line and
  body parts may be large binary blobs.
- **Buffering data of unpredictable size** where automatic spill from memory
  to disk is valuable.
- **Handling multibyte text** in protocol data where character-level
  positioning matters (e.g., UTF-8 mailbox names in IMAP).
- **Building protocol output** by assembling strings, resources and other
  streams into a single buffer.

### Bridging the two

When interoperability with PSR-7 code is needed, use `getResource()` to
extract the underlying PHP resource and wrap it in a PSR-7 stream or copy
content between the two:

```php
// Horde\Stream -> PSR-7
$psr7stream = new Horde\Http\Stream($hordeStream->getResource());

// PSR-7 -> Horde\Stream
$hordeStream = new Horde\Stream\Existing($psr7stream->detach());
```

## Protocol use cases

### IMAP response parsing

IMAP servers send structured responses with literal data blocks denoted by
`{length}\r\n`. A tokenizer uses `getToChar()` and `search()` to extract
these:

```php
use Horde\Stream\Temp;

$buffer = new Temp();
$buffer->add($rawImapResponse, reset: true);

// Read tokens until we hit a literal marker
$token = $buffer->getToChar('}', all: false);
$literalLen = (int) $token;

// Read exactly that many bytes of literal data
$literal = $buffer->substring(0, $literalLen);

// Store large literals in their own stream
$bodyStream = new Temp();
$bodyStream->add($literal);
```

Stream filters can be applied to the underlying resource for encoding
transformations (e.g., detecting whether data requires IMAP literal
quoting):

```php
$resource = $stream->getResource();
stream_filter_append($resource, 'horde_imap_client_string', STREAM_FILTER_WRITE);
```

### SMTP DATA transmission

SMTP sends message bodies after the `DATA` command. Large messages are
streamed in chunks rather than loaded fully into memory:

```php
use Horde\Stream\Existing;

$connection = new Existing($smtpSocket);

// Stream message body from a file handle
$message = fopen('/path/to/message.eml', 'r');
$connection->add($message);
fclose($message);
```

### MIME header parsing

MIME headers are line-oriented with continuation (folded) lines. The stream
makes this natural:

```php
use Horde\Stream\Temp;

$stream = new Temp();
$stream->add($rawHeaders, reset: true);

$headers = [];
while (!$stream->eof()) {
    $line = $stream->getToChar("\n", all: false);
    $line = rtrim($line, "\r");

    if ($line === '') {
        break; // blank line ends headers
    }

    // Check for folded continuation line
    $next = $stream->peek();
    while ($next === ' ' || $next === "\t") {
        $line .= ' ' . trim($stream->getToChar("\n", all: false));
        $next = $stream->peek();
    }

    [$name, $value] = explode(':', $line, 2);
    $headers[trim($name)] = trim($value);
}
```

### ManageSieve command parsing

ManageSieve (RFC 5804) uses a line-oriented protocol similar to IMAP, with
literal strings for script content:

```php
use Horde\Stream\Temp;

$response = new Temp();
$response->add($rawResponse, reset: true);

$status = $response->getToChar(' ', all: false);  // "OK", "NO" or "BYE"
$detail = $response->getToChar("\r\n", all: false);
```

### Large message handling with TempString

When handling mailbox operations that process many small messages but
occasionally encounter large attachments:

```php
use Horde\Stream\TempString;

// Each message gets its own stream. Most stay in the fast string
// backend; large ones spill to temp automatically.
foreach ($messageIds as $id) {
    $stream = new TempString(maxMemory: 262144); // 256 KB threshold
    $stream->add($fetchResponse);

    // Process headers (small, stays in memory)
    // Process body (may be large, may spill -- transparent to caller)
    processMessage($stream);

    $stream->close();
}
```
