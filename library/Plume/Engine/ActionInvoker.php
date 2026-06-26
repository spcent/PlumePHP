<?php

declare(strict_types=1);

/**
 * Instantiates an action class and executes it.
 *
 * Separates object lifecycle (new + run()) from the class-name resolution and
 * file-location concerns handled by ActionNaming and ActionLocator.
 */
class ActionInvoker
{
    /**
     * Instantiate $className and call run().
     *
     * @param string $className  Fully-resolved class name
     * @param string $requestUri Used only in 404 messages
     *
     * @return mixed Whatever run() returns
     */
    public static function invoke(string $className, string $requestUri): mixed
    {
        if (!class_exists($className)) {
            throw new ActionException(404,
                "!!! 404 !!! uri={$requestUri} class not exist: {$className}");
        }

        $instance = new $className();

        if (!method_exists($instance, 'run')) {
            throw new ActionException(404,
                "!!! 404 !!! uri={$requestUri} no run method: {$className}");
        }

        return $instance->run();
    }

    /**
     * Filter sensitive fields out of a request array before logging.
     *
     * @param array<string, mixed> $request Typically $_REQUEST
     *
     * @return array<string, mixed> Sanitised copy
     */
    public static function sanitizeForLog(array $request): array
    {
        static $sensitive = ['password', 'passwd', 'pass', 'token', 'secret', 'card_no', 'cvv'];
        return array_diff_key($request, array_flip($sensitive));
    }
}
