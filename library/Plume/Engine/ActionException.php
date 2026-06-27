<?php

declare(strict_types=1);

/**
 * Thrown by ActionLocator and ActionInvoker when an action cannot be resolved.
 *
 * Engine::runAction() catches this and forwards to _halt(), keeping the two
 * utility classes free of any dependency on the PlumePHP facade.
 */
class ActionException extends \RuntimeException
{
    public function __construct(int $httpCode, string $message)
    {
        parent::__construct($message, $httpCode);
    }

    public function getHttpCode(): int
    {
        return $this->getCode();
    }
}
