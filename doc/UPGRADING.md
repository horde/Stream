# Upgrading from Horde_Stream to Horde\Stream

This guide covers the migration from the legacy `Horde_Stream` classes
(`lib/` PSR-0) to the modern `Horde\Stream` namespace (`src/` PSR-4).

Both namespaces coexist in the same package. No code is removed. You can
migrate at your own pace.

## Class mapping

| Legacy (lib/) | Modern (src/) |
|----------------|---------------|
| `Horde_Stream` | `Horde\Stream\AbstractStream` (not instantiated directly) |
| `Horde_Stream_Temp` | `Horde\Stream\Temp` |
| `Horde_Stream_Existing` | `Horde\Stream\Existing` |
| `Horde_Stream_TempString` | `Horde\Stream\TempString` |
| `Horde_Stream_Wrapper_String` | `Horde\Stream\StringStream` (wraps the wrapper internally) |
| `Horde_Stream_Exception` | `Horde\Stream\StreamException` |
| *(none)* | `Horde\Stream\StreamInterface` |
| *(none)* | `Horde\Stream\Readable` |
| *(none)* | `Horde\Stream\Writable` |
| *(none)* | `Horde\Stream\Seekable` |

## Constructor changes

Legacy classes accepted an `$opts` array. Modern classes use typed named
parameters.

### Horde_Stream_Temp

```php
// Legacy
$stream = new Horde_Stream_Temp(['max_memory' => 2097152]);

// Modern
$stream = new Horde\Stream\Temp(maxMemory: 2097152);
```

### Horde_Stream_Existing

```php
// Legacy
$stream = new Horde_Stream_Existing(['stream' => $resource]);

// Modern
$stream = new Horde\Stream\Existing($resource);
```

### Horde_Stream_TempString

```php
// Legacy
$stream = new Horde_Stream_TempString(['max_memory' => 1048576]);

// Modern
$stream = new Horde\Stream\TempString(maxMemory: 1048576);
```

## Property changes

### utf8_char -> utf8Char

The magic property `utf8_char` is now a typed public property.

```php
// Legacy
$stream->utf8_char = true;

// Modern
$stream->utf8Char = true;
```

### stream -> getResource()

The public `$stream` property is replaced by a method.

```php
// Legacy
$resource = $stream->stream;

// Modern
$resource = $stream->getResource();
```

## Method signature changes

### Return types

Methods that returned `bool` for success/failure now return `void` and throw
`StreamException` on failure.

| Method | Legacy return | Modern return |
|--------|-------------|---------------|
| `rewind()` | `bool` | `void` (throws on failure) |
| `seek()` | `bool` | `void` (throws on failure) |
| `end()` | `bool` | `void` (throws on failure) |
| `pos()` | `int\|false` | `int` (throws on failure) |

### seek()

The second parameter changed from positional `$curr` to named `$fromCurrent`.
Behavior is identical.

```php
// Legacy - seek to absolute position 5
$stream->seek(5, false);

// Modern - identical call, but the parameter is named
$stream->seek(5, fromCurrent: false);
```

The optional third parameter for UTF-8 character-mode seeking changed from
positional to named:

```php
// Legacy
$stream->seek(2, false, true);

// Modern
$stream->seek(2, fromCurrent: false, char: true);
```

### add()

The second parameter changed from positional `$reset` to named. The `$data`
parameter now accepts `StreamInterface` in addition to `Horde_Stream`.

```php
// Legacy
$stream->add($data, true);

// Modern
$stream->add($data, reset: true);
```

### getToChar()

The second parameter changed from positional `$all` to named:

```php
// Legacy - don't strip repeated delimiters
$stream->getToChar("\n", false);

// Modern
$stream->getToChar("\n", all: false);
```

### substring()

The third parameter for UTF-8 character mode changed from positional to named:

```php
// Legacy
$stream->substring(0, 3, true);

// Modern
$stream->substring(0, 3, char: true);
```

### length()

The parameter for UTF-8 character counting changed from positional to named:

```php
// Legacy
$stream->length(true);

// Modern
$stream->length(utf8: true);
```

### search()

Parameters changed from positional to named:

```php
// Legacy
$stream->search('X', false, false);

// Modern
$stream->search('X', reverse: false, reset: false);
```

## Type-hinting

Legacy code that type-hints on `Horde_Stream` should be updated to use
`Horde\Stream\StreamInterface` or the narrower capability interfaces when
appropriate:

```php
// Legacy
function parse(Horde_Stream $stream): void { ... }

// Modern - full stream
function parse(StreamInterface $stream): void { ... }

// Modern - only needs read + position
function parse(Readable&Seekable $stream): void { ... }
```

The `add()` method on modern streams accepts both `StreamInterface` and
`Horde_Stream`, so you can pass legacy stream objects to modern code during
migration.

## Serialization

Legacy streams implemented `Serializable`. Modern streams use
`__serialize()` / `__unserialize()`. Both serialize data content and cursor
position. The serialized formats are not cross-compatible - you cannot
unserialize a legacy stream into a modern one or vice versa.

## Exceptions

Modern code throws `Horde\Stream\StreamException` (extends `RuntimeException`)
instead of `Horde_Stream_Exception`. Expected exhaustion (`getChar()` at EOF)
returns `false`, not an exception. Only actual failures throw:

- Failed seek, rewind or position query
- Invalid or truncated UTF-8 byte sequence
- Failed resource creation in constructors

## Removed features

- **Magic `__get` / `__set`**: The `utf8_char` magic property is replaced by
  `public bool $utf8Char`. No other magic properties exist.
- **`Serializable` interface**: Replaced by `__serialize()` /
  `__unserialize()`.
- **Array constructor options**: Replaced by typed named parameters.
