# flyokai/amp-channel-dispatcher

> User docs → [`README.md`](README.md) · Agent quick-ref → [`CLAUDE.md`](CLAUDE.md) · Agent deep dive → [`AGENTS.md`](AGENTS.md)

Async bidirectional message dispatching over AMPHP channels with request-response correlation and middleware.

See [AGENTS.md](AGENTS.md) for detailed documentation.

## Quick Reference

- **Dispatcher**: Dual-loop (read/write) message hub over AMPHP Channel
- **Messages**: Request (expects Response) / MeekRequest (fire-and-forget) / Response (with requestId correlation)
- **Pipeline**: `stackMiddleware(handler, m1, m2)` — chain of responsibility
- **Remote iterators**: `RemoteIterator` (client proxy) ↔ `IteratorStorage` (server side)
- **Error handling**: `DefaultErrorHandler` — ErrorResponse (recoverable) / FatalErrorResponse (terminates)
- **Context**: Injected into requests — access to dispatcher, sendRequest, iterator storage
