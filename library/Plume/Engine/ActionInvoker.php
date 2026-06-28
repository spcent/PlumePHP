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
        static $sensitive = [
            // Credentials
            'password', 'passwd', 'pass', 'pwd', 'new_password', 'old_password', 'confirm_password',
            // Tokens & keys
            'token', 'secret', 'api_key', 'apikey', 'access_token', 'refresh_token',
            'auth_token', 'session_token', 'csrf_token', 'plume_csrf',
            // Payment
            'card_no', 'card_number', 'cvv', 'cvc', 'expiry', 'bank_account', 'bank_card',
            // PII
            'id_card', 'id_number', 'ssn', 'social_security', 'passport', 'license_number',
            'phone', 'mobile', 'email', 'birthday', 'date_of_birth',
            // Private keys
            'private_key', 'encryption_key', 'signing_key',
        ];
        $result = array_diff_key($request, array_flip($sensitive));
        // Also redact any key containing these substrings (case-insensitive)
        $patterns = ['pass', 'secret', 'token', 'key', 'card', 'cvv', 'ssn', 'id_card'];
        foreach ($result as $k => $v) {
            $lower = strtolower((string) $k);
            foreach ($patterns as $pattern) {
                if (str_contains($lower, $pattern)) {
                    $result[$k] = '***REDACTED***';
                    break;
                }
            }
        }
        return $result;
    }
}
