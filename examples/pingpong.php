<?php
/**
 * amp-channel-dispatcher example — request/response over an AMPHP channel,
 * using a parent-child process pair connected by a process channel.
 *
 * Run from project root:
 *   php vendor/flyokai/amp-channel-dispatcher/examples/pingpong.php
 */

require __DIR__ . '/../../../../vendor/autoload.php';

use Amp\Parallel\Context\ProcessContextFactory;
use Flyokai\AmpChannelDispatcher\DefaultDispatcherChannel;
use Flyokai\AmpChannelDispatcher\Dispatcher;
use Flyokai\AmpChannelDispatcher\Error\DefaultErrorHandler;
use Flyokai\AmpChannelDispatcher\Request;
use Flyokai\AmpChannelDispatcher\RequestHandler;
use Flyokai\AmpChannelDispatcher\RequestTrait;
use Flyokai\AmpChannelDispatcher\Response;
use Flyokai\AmpChannelDispatcher\ResponseTrait;
use function Flyokai\AmpChannelDispatcher\stackMiddleware;

if (($argv[1] ?? '') === 'child') {
    childMain();
    return;
}

parentMain();

// ---------- Messages ----------

final class PingRequest implements Request {
    use RequestTrait;
    public function __construct(public readonly string $note = '') {}
}

final class PongResponse implements Response {
    use ResponseTrait;
    public function __construct(public readonly string $reply, public readonly ?int $requestId = null) {}
}

// ---------- Server side (child) ----------

final class PingHandler implements RequestHandler
{
    public function handleRequest(Request $request): Response
    {
        // $request is a PingRequest here — handler is registered for that class.
        assert($request instanceof PingRequest);
        return new PongResponse(
            reply:     "pong: {$request->note}",
            requestId: $request->id(),
        );
    }
}

function childMain(): void
{
    // The child receives a Channel via amphp/parallel's bootstrap.
    $channel = require __DIR__ . '/../../../../vendor/amphp/parallel/lib/Context/Internal/process-runner.php';
    // Real applications use Amp\Parallel\Context\ProcessContext::start() and the framework
    // gives the child a channel automatically; this example shorthands.

    $dispatcher = new Dispatcher(
        new DefaultDispatcherChannel($channel),
        stackMiddleware(new PingHandler()),
        new DefaultErrorHandler(),
    );
    $dispatcher->run();
    $channel->awaitTermination();
}

// ---------- Client side (parent) ----------

function parentMain(): void
{
    $factory = new ProcessContextFactory();
    $context = $factory->start([__FILE__, 'child']);

    $dispatcher = new Dispatcher(
        new DefaultDispatcherChannel($context),
        // No request handler on the client — we only send.
        stackMiddleware(new class implements RequestHandler {
            public function handleRequest(Request $request): Response {
                throw new \RuntimeException('client should not receive requests');
            }
        }),
        new DefaultErrorHandler(),
    );
    $dispatcher->run();

    /** @var PongResponse $response */
    $response = $dispatcher->sendRequest(new PingRequest(note: 'hello'))->await();
    echo "Got: {$response->reply} (requestId={$response->requestId})\n";

    $dispatcher->stop();
    $context->join();
}
