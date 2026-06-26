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
 * Supports interface-to-concrete binding, factory closures, and circular dependency detection.
 */
class PlumeContainer implements \Psr\Container\ContainerInterface
{
    /** @var array<string, string> abstract id → concrete class name */
    private array $bindings = [];

    /** @var array<string, callable> id → factory(ContainerInterface): mixed */
    private array $factories = [];

    /** @var array<string, bool> ids currently being resolved (circular dependency guard) */
    private array $resolving = [];

    public function __construct(private readonly PlumeLoader $loader) {}

    /**
     * Bind an interface or abstract name to a concrete class.
     * The concrete class is resolved through PlumeLoader on first access.
     */
    public function bind(string $abstract, string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * Register a factory closure for an id.
     * The closure receives this container and must return the service instance.
     */
    public function bindFactory(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function get(string $id): mixed
    {
        if (isset($this->resolving[$id])) {
            throw new PlumeContainerException("Circular dependency detected while resolving '{$id}'.");
        }

        if (isset($this->factories[$id])) {
            $this->resolving[$id] = true;
            try {
                return ($this->factories[$id])($this);
            } finally {
                unset($this->resolving[$id]);
            }
        }

        if (isset($this->bindings[$id])) {
            $concrete = $this->bindings[$id];
            $this->resolving[$id] = true;
            try {
                $instance = $this->loader->load($concrete);
                if ($instance !== null) {
                    return $instance;
                }
                if (!class_exists($concrete) && !interface_exists($concrete) && !trait_exists($concrete)) {
                    throw new PlumeContainerException("Class '{$concrete}' does not exist.");
                }
                $ref = new \ReflectionClass($concrete);
                if ($ref->isAbstract() || $ref->isInterface()) {
                    throw new PlumeContainerException("Cannot instantiate abstract class or interface '{$concrete}'.");
                }
                return new $concrete();
            } finally {
                unset($this->resolving[$id]);
            }
        }

        if (!$this->loader->get($id)) {
            throw new PlumeNotFoundException("Service '{$id}' not found in the container.");
        }
        return $this->loader->load($id);
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id])
            || isset($this->factories[$id])
            || $this->loader->get($id) !== null;
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
    /** @var PlumeMiddlewareInterface[] */
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
