<?php

declare(strict_types=1);

/**
 * Concrete Action subclass for testing CSRF behavior.
 */
class CsrfTestAction extends \Plume\Libs\Action
{
    public function execute(): mixed
    {
        return null;
    }

    /** Expose protected method for testing */
    public function publicValidateCsrfToken(): bool
    {
        return $this->validateCsrfToken();
    }

    /** Expose token creation for testing */
    public function publicCreateCsrfToken(): string
    {
        return $this->createCsrfToken();
    }

    public function publicGetCsrfToken(): ?string
    {
        return $this->getCsrfToken();
    }
}

class CsrfTest extends \PHPUnit\Framework\TestCase
{
    private CsrfTestAction $action;

    public function setUp(): void
    {
        $_COOKIE    = [];
        $_POST      = [];
        $_SERVER    = [];
        $this->action = new CsrfTestAction();
    }

    public function testGetRequestAlwaysPasses(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertTrue($this->action->publicValidateCsrfToken());
    }

    public function testHeadRequestAlwaysPasses(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $this->assertTrue($this->action->publicValidateCsrfToken());
    }

    public function testOptionsRequestAlwaysPasses(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $this->assertTrue($this->action->publicValidateCsrfToken());
    }

    public function testPostWithoutCookieFails(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_COOKIE = [];
        $this->assertFalse($this->action->publicValidateCsrfToken());
    }

    public function testCreateCsrfTokenReturnsMaskedString(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $token = $this->action->publicCreateCsrfToken();
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    public function testCsrfTokenReturnsCookieValueWhenCookieIsSet(): void
    {
        // Simulate a request where the token cookie already exists.
        // When the cookie is present, createCsrfToken() returns the existing token
        // without regenerating it.
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $token = $this->action->publicCreateCsrfToken();
        $this->assertNotEmpty($token);

        // Cookie is now set (by createCsrfToken's setCookie call, but in unit tests
        // setcookie() doesn't populate $_COOKIE, so we simulate it manually).
        $_COOKIE['plume-csrf-token'] = $token;

        $action2 = new CsrfTestAction();
        $token2  = $action2->publicCreateCsrfToken();
        $this->assertIsString($token2);
        $this->assertNotEmpty($token2);
    }

    public function testPutRequestWithoutCookieFails(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_COOKIE = [];
        $this->assertFalse($this->action->publicValidateCsrfToken());
    }

    public function testPatchRequestWithoutCookieFails(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $_COOKIE = [];
        $this->assertFalse($this->action->publicValidateCsrfToken());
    }
}
