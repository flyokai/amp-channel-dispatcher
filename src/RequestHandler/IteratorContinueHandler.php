<?php

namespace Flyokai\AmpChannelDispatcher\RequestHandler;

use Flyokai\AmpChannelDispatcher\ErrorResponse;
use Flyokai\AmpChannelDispatcher\Request;
use Flyokai\AmpChannelDispatcher\RequestHandler;
use Flyokai\AmpChannelDispatcher\Response;
use Flyokai\AmpChannelDispatcher\Dispatcher;

class IteratorContinueHandler implements RequestHandler
{
    /**
     * @param Request\IteratorContinue $request
     * @return Response
     */
    public function handleRequest(Request $request): Response
    {
        /** @var Dispatcher\Context $context */
        $context = $request->getAttribute('context');
        $iterator = $context->getLocalIterator($request->iteratorId);
        if ($iterator === null) {
            $response = $request->createErrorResponse('Iterator not found');
        } else {
            $continue = $iterator->continue();
            $response = new Response\IteratorContinue(
                continue: $continue,
                position: $continue ? $iterator->getPosition() : null,
                value: $continue ? $iterator->getValue() : null,
                requestId: $request->id()
            );
        }
        return $response;
    }
}
