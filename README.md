# Horde\Stream

A managed stream abstraction for PHP that provides type guarantees,
buffering strategies, lifecycle management and protocol-oriented
read operations. Part of the [Horde](https://www.horde.org/) project.

## Why not PSR-7 StreamInterface?

PSR-7 streams (`Psr\Http\Message\StreamInterface`) model HTTP message bodies:
a thin wrapper around a PHP resource with `read()`, `write()` and `seek()`.
They are the right tool for HTTP request/response transport.

Horde\Stream targets a different problem space: **parsing and constructing
structured data over byte streams**, the kind of work that IMAP, SMTP, MIME,
and ManageSieve libraries do on every connection. This requires operations
that PSR-7 does not offer:

- **Delimiter scanning** - read until `}`, `\r\n` or any multi-byte
  delimiter without buffering the entire stream into a string first.
- **Positional search** - find the byte offset of a substring, forward or
  reverse, without consuming data.
- **Peek without consume** - inspect upcoming bytes and rewind automatically.
- **UTF-8 character-level seeking and reading** - navigate by character count
  rather than byte count for multibyte-safe protocol handling.
- **EOL auto-detection** - determine whether a stream uses LF or CRLF line
  endings.
- **Polymorphic write** - `add()` accepts a string, a PHP resource or
  another `StreamInterface`, copying data efficiently in chunks.
- **Automatic memory management** - `TempString` starts in a pure-PHP string
  backend and transparently spills to `php://temp` when size exceeds a
  configurable threshold.
- **Safe cloning and serialization** - clone a stream to get an independent
  copy; serialize to persist both data and cursor position.

A future PSR-7 adapter layer can bridge the two worlds when HTTP interop is
needed. See [doc/USAGE.md](doc/USAGE.md) for details and examples.

## Installation

```bash
composer require horde/stream
```

For the string-backed stream (`StringStream`), also install the stream wrapper:

```bash
composer require horde/stream_wrapper
```

## Quick start

```php
use Horde\Stream\Temp;

$stream = new Temp();
$stream->add("From: alice@example.com\r\nSubject: Hello\r\n\r\nBody text.");
$stream->rewind();

// Read header lines
while (!$stream->eof()) {
    $line = $stream->getToChar("\r\n", all: false);
    if ($line === '') {
        break; // blank line = end of headers
    }
    echo $line, "\n";
}

// Read remaining body
echo $stream->substring(), "\n";
```

## Stream implementations

| Class | Backend | Use case |
|-------|---------|----------|
| `Temp` | `php://temp` | General-purpose buffering. Optional `maxMemory` parameter controls spill-to-disk threshold. |
| `Existing` | Caller-provided resource | Wrap a socket, file handle or any open PHP stream resource. |
| `StringStream` | `horde/stream_wrapper` | String-backed stream with full seek support. Requires `horde/stream_wrapper`. |
| `TempString` | String, spills to `php://temp` | Starts in fast string backend, transparently switches to temp stream when data exceeds `maxMemory`. |

## Interface hierarchy

```
Readable    Writable    Seekable    Stringable
    \           |           /           /
     \          |          /           /
      StreamInterface ----------------
            |
       AbstractStream
      /    |     \       \
   Temp  Existing  StringStream  TempString
```

`StreamInterface` composes `Readable`, `Writable`, `Seekable` and
`Stringable`. Consumer code can type-hint on the narrow interface when only
a subset of capabilities is needed.

## Documentation

- [doc/USAGE.md](doc/USAGE.md) - API reference, patterns and protocol
  examples.
- [doc/UPGRADING.md](doc/UPGRADING.md) - Migration guide from `Horde_Stream`
  (lib/) to `Horde\Stream` (src/).

## Testing

```bash
# Unit tests (default suite)
phpunit

# Integration tests (requires horde/stream_wrapper)
phpunit --testsuite integration
```

## License

LGPL 2.1. See [LICENSE](LICENSE) for details.
