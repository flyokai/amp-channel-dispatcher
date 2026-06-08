# flyokai/amp-channel-dispatcher

> User docs → [`README.md`](README.md) · Agent quick-ref → [`CLAUDE.md`](CLAUDE.md) · Agent deep dive → [`AGENTS.md`](AGENTS.md)

> Async bidirectional message dispatching over AMPHP channels — request/response correlation, middleware pipeline, remote iterators, and configurable error handling.

This is the messaging plumbing that lets two PHP processes (or threads) talk to each other over an AMPHP `Channel`. You get request/response semantics with automatic id correlation, a middleware stack for cross-cutting concerns, and **remote iterators** for streaming large result sets without buffering.

## Features

- **Dual-loop dispatcher** — read & write loops share one channel
- **Request types** — `Request` (expects `Response`) and `MeekRequest` (fire-and-forget)
- **Middleware pipeline** — chain-of-responsibility via `stackMiddleware()`
- **Remote iterators** — stream results across the channel without full buffering
- **Pluggable error handling** — `ErrorResponse` (recoverable) vs `FatalErrorResponse` (terminates dispatcher)
- **Context injection** — handlers get a `Context` with reference back to the dispatcher

## Installation

```bash
composer require flyokai/amp-channel-dispatcher
```

## Quick start

```php
use Amp\ByteStream\StreamChannel;
use Flyokai\AmpChannelDispatcher\{Dispatcher, RequestHandler, Request, Response};
use Flyokai\AmpChannelDispatcher\DefaultDispatcherChannel;
use Flyokai\AmpChannelDispatcher\Error\DefaultErrorHandler;
use function Flyokai\AmpChannelDispatcher\stackMiddleware;

final class PingHandler implements RequestHandler
{
    public function handleRequest(Request $request): Response
    {
        return new Response\SuccessResponse(requestId: $request->id());
    }
}

$channel = /* an Amp\Sync\Channel */;
$dispatcher = new Dispatcher(
    new DefaultDispatcherChannel($channel),
    stackMiddleware(new PingHandler() /*, $middleware1, $middleware2*/),
    new DefaultErrorHandler(),
);
$dispatcher->run();

$response = $dispatcher->sendRequest(new Request\Ping())->await();
```

## Concepts

### Messages

Every message implements `Message`:

- `id(): int` — auto-generated unique ID
- `getAttribute(string $name)` / `setAttribute(string $name, mixed $value)`
- `cloneWith(...$args): static`

`Request` and `Response` both extend `Message`. A `Response` carries `requestId(): ?int` for correlation.

### Built-in responses

| Class | Meaning |
|-------|---------|
| `Response\SuccessResponse` | Generic success |
| `Response\ErrorResponse` | Recoverable error (message + code) |
| `Response\AcceptedResponse` | Acknowledgement |
| `Response\FatalErrorResponse` | **Terminates the dispatcher** (extends ErrorResponse, `requestId = null`) |
| `Response\IteratorContinue` | Remote-iterator pagination payload |

### Dispatcher lifecycle

```php
$dispatcher->run();             // starts read & write loops via EventLoop::defer()
$future = $dispatcher->sendRequest(new MyRequest(...));   // returns null for MeekRequest
$response = $future->await();
$dispatcher->onStop(fn() => /* cleanup */);
$dispatcher->stop();            // graceful shutdown
```

The dispatcher attaches a `Context` attribute to every incoming request with:

- `dispatcher()` — `?WeakReference` to the dispatcher
- `sendRequest()` — for nested calls
- iterator-storage handles (`addLocalIterator()`, …)

### Middleware

```php
final class LoggingMiddleware implements Middleware
{
    public function handleRequest(Request $request, RequestHandler $next): Response
    {
        // … pre …
        $response = $next->handleRequest($request);
        // … post …
        return $response;
    }
}

$pipeline = stackMiddleware($handler, $logging, $auth);
// composes: $logging → $auth → $handler
```

### Remote iterators

When a handler returns a `ConcurrentIterator`, the consumer side gets a `RemoteIterator` proxy. Each `continue()` is a blocking RPC round-trip:

```php
// Server side:
$context->addLocalIterator($iterator);

// Client side:
$remote = new RemoteIterator(/* … */);
foreach ($remote as $value) { /* … */ }
```

## API

| Class | Purpose |
|-------|---------|
| `Dispatcher` | Central read/write hub |
| `DefaultDispatcherChannel` | Channel adapter |
| `RequestHandler` (interface) | `handleRequest(Request): Response` |
| `Middleware` (interface) | `handleRequest(Request, RequestHandler $next): Response` |
| `stackMiddleware(...)` | Helper to compose handler + middlewares |
| `Error\ErrorHandler` (interface) | `handleError`, `handleException` |
| `Error\DefaultErrorHandler` | Recoverable vs fatal heuristic |
| `RemoteIterator`, `IteratorStorage` | Remote iteration |
| `Context` | Per-request context (dispatcher ref, sendRequest, iterator storage) |

## Gotchas

- **WeakReference inside `Context`** — if the dispatcher is GC'd, `context->dispatcher()` returns null. Handlers must check.
- **RemoteIterator round-trips** — every `continue()` blocks until the remote responds. There is no batching; very large iterators are slow.
- **`FiberLocal` in `RemoteIterator`** — state is per-fiber. Calling `continue()` from a different fiber raises an error.
- **MeekRequest** — `sendRequest()` returns `null`. There is no delivery confirmation.
- **`stop()` cancels pending futures** — every unresolved request errors with `DispatcherException('Dispatcher terminated')`.
- **`FatalErrorResponse` halts the dispatcher** — a response with a `null` requestId terminates the read loop.
- **Messages are serialized** — AMPHP channels use `serialize()`/`unserialize()`. Custom objects must support it.
- **WeakClosure callbacks** — all dispatcher callbacks are weakly referenced to avoid circular references and let GC do its job.

## See also

- [`flyokai/data-service`](../data-service/README.md) — socket-based service built on this dispatcher
- [`flyokai/data-service-message`](../data-service-message/README.md) — concrete request/response DTOs

## License

MIT
