<?php

declare(strict_types=1);

/**
 * PSR-11 not-found exception.
 */
class PlumeNotFoundException extends \RuntimeException implements \Psr\Container\NotFoundExceptionInterface {}
/**
 * PSR-11 container exception.
 */
class PlumeContainerException extends \RuntimeException implements \Psr\Container\ContainerExceptionInterface {}
/**
 * PSR-11 ContainerInterface wrapping PlumeLoader's service registry.
 */
class PlumeContainer implements \Psr\Container\ContainerInterface
{
    public function __construct(private readonly PlumeLoader $loader) {}

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new PlumeNotFoundException("Service '{$id}' not found in the container.");
        }
        return $this->loader->load($id);
    }

    public function has(string $id): bool
    {
        return $this->loader->get($id) !== null;
    }
}
/**
 * PSR-15-style request handler interface (uses PlumePHP's own Request/Response).
 */
interface PlumeRequestHandlerInterface
{
    public function handle(PlumeRequest $request): PlumeResponse;
}
/**
 * PSR-15-style middleware interface.
 */
interface PlumeMiddlewareInterface
{
    public function process(PlumeRequest $request, PlumeRequestHandlerInterface $handler): PlumeResponse;
}
/**
 * Chains PlumeMiddlewareInterface instances around a final PlumeRequestHandlerInterface.
 */
class PlumeMiddlewarePipeline implements PlumeRequestHandlerInterface
{
    private array $middlewares = [];
    private ?PlumeRequestHandlerInterface $finalHandler = null;

    public function pipe(PlumeMiddlewareInterface $middleware): static
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function setFinalHandler(PlumeRequestHandlerInterface $handler): void
    {
        $this->finalHandler = $handler;
    }

    public function handle(PlumeRequest $request): PlumeResponse
    {
        $final = $this->finalHandler ?? new class implements PlumeRequestHandlerInterface {
            public function handle(PlumeRequest $request): PlumeResponse { return new PlumeResponse(); }
        };

        $handler = array_reduce(
            array_reverse($this->middlewares),
            static fn(PlumeRequestHandlerInterface $carry, PlumeMiddlewareInterface $mw): PlumeRequestHandlerInterface =>
                new class($mw, $carry) implements PlumeRequestHandlerInterface {
                    public function __construct(
                        private readonly PlumeMiddlewareInterface $mw,
                        private readonly PlumeRequestHandlerInterface $next
                    ) {}
                    public function handle(PlumeRequest $request): PlumeResponse
                    {
                        return $this->mw->process($request, $this->next);
                    }
                },
            $final
        );

        return $handler->handle($request);
    }
}
