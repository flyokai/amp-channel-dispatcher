# flyokai/amp-channel-dispatcher

Async bidirectional message dispatching over AMPHP channels with request-response correlation, middleware pipeline, remote iterators, and error handling.

## Core Architecture

### Message System

**Message** (interface) — base for all messages:
- `id(): int` — unique auto-generated ID
- `getAttribute(name)` / `setAttribute(name, value)` — arbitrary context
- `cloneWith(...$args)` — immutable modification

**Request** (interface, extends Message) — marker for requests. Must receive a response unless it implements `MeekRequest` (fire-and-forget).

**Response** (interface, extends Message) — includes `requestId(): ?int` for correlation.

**Built-in responses:**
- `SuccessResponse` — generic success
- `ErrorResponse` — recoverable error with message/code
- `AcceptedResponse` — acknowledged
- `FatalErrorResponse` (extends ErrorResponse) — terminates dispatcher, `requestId=null`
- `Response\IteratorContinue` — remote iterator pagination (continue, position, value)

### Dispatcher

Central orchestrator with dual async loops:

- **Read loop** — receives messages from channel, classifies as Request or Response
- **Write loop** — sends queued messages from internal `Queue`

**Request flow:**
1. `sendRequest(Request)` → queues message, returns `Future` (or null for MeekRequest)
2. Remote handler processes request, sends Response with matching `requestId`
3. `handleResponse()` resolves the pending `DeferredFuture`

**Key methods:**
- `run()` — starts both read/write loops via `EventLoop::defer()`
- `sendRequest(Request): ?Future` — queue request, track response future
- `stop()` — graceful shutdown: complete queue, cancel loops, close channel
- `onStop(Closure)` — register shutdown callback

**Context injection:** Dispatcher attaches a `Context` attribute to every incoming request, giving handlers access to `dispatcher()`, `sendRequest()`, and iterator storage.

### Request Handler Pipeline

**RequestHandler** (interface): `handleRequest(Request): Response`

**Middleware** (interface): `handleRequest(Request, RequestHandler $next): Response`

Chain of responsibility via `stackMiddleware($handler, $m1, $m2, ...)` — creates `m1 → m2 → ... → handler`.

### Remote Iterators

Enables streaming large result sets across channel boundaries without full buffering.

- **Local side:** Register iterator via `Context::addLocalIterator(ConcurrentIterator)`. Handler responds to `Request\IteratorContinue` with next item.
- **Remote side:** `RemoteIterator` implements `ConcurrentIterator`. Each `continue()` call is a blocking RPC round-trip.

### Error Handling

**ErrorHandler** (interface):
- `handleError(msg, code, ?request): Response`
- `handleException(Throwable, ?request): Response`

**DefaultErrorHandler:** Returns `ErrorResponse` if request exists, `FatalErrorResponse` if not (terminates dispatcher).

## Usage

```php
$channel = /* AMPHP Channel */;
$dispatcher = new Dispatcher(
    new DefaultDispatcherChannel($channel),
    stackMiddleware(new MyRequestHandler(), new LoggingMiddleware()),
    new DefaultErrorHandler()
);
$dispatcher->run();

// Sending requests
$future = $dispatcher->sendRequest(new MyRequest(...));
$response = $future->await();
```

## Gotchas

- **WeakReference in Context**: If dispatcher is GC'd, `context->dispatcher()` returns null. Handlers must check.
- **RemoteIterator round-trips**: Each `continue()` blocks until remote responds. No batching — slow for large iterators.
- **FiberLocal in RemoteIterator**: State is fiber-specific. Calling `continue()` from different fibers causes errors.
- **MeekRequest returns null**: `sendRequest()` returns null for fire-and-forget — no delivery confirmation.
- **Pending responses on stop**: All unresolved futures error with `DispatcherException('Dispatcher terminated')`.
- **FatalErrorResponse terminates**: Response without requestId halts the entire dispatcher.
- **Messages must be serializable**: AMPHP channel uses `serialize()`/`unserialize()`. Custom objects must support this.
- **WeakClosure usage**: All dispatcher callbacks are weakly referenced to prevent circular references and enable GC.
