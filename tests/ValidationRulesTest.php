<?php

declare(strict_types=1);

/**
 * Tests for extended validation rules in Action::validate().
 *
 * We use a concrete stub that overrides getParam() to avoid HTTP request setup.
 */
class ValidationRulesTest extends \PHPUnit\Framework\TestCase
{
    // ---------------------------------------------------------------------------
    // Helper: build a concrete Action with given params and rules
    // ---------------------------------------------------------------------------

    private function makeAction(array $params, array $rules): \Plume\Libs\Action
    {
        return new class ($params, $rules) extends \Plume\Libs\Action {
            private array $testParams;
            public function __construct(array $testParams, array $rules)
            {
                $this->testParams = $testParams;
                $this->rules = $rules;
                $this->csrfValidate = false;
            }
            public function getParam($name, $default = null)
            {
                return $this->testParams[$name] ?? $default;
            }
            public function execute() {}
        };
    }

    // ---------------------------------------------------------------------------
    // Existing rules (regression)
    // ---------------------------------------------------------------------------

    public function testRequiredFailsOnEmpty(): void
    {
        $action = $this->makeAction(['field' => ''], ['field' => 'required']);
        $errors = $action->validate();
        $this->assertArrayHasKey('field', $errors);
    }

    public function testRequiredPassesOnValue(): void
    {
        $action = $this->makeAction(['field' => 'hello'], ['field' => 'required']);
        $this->assertEmpty($action->validate());
    }

    public function testIntRule(): void
    {
        $action = $this->makeAction(['age' => 'abc'], ['age' => 'int']);
        $errors = $action->validate();
        $this->assertArrayHasKey('age', $errors);
    }

    public function testIntPassesForInteger(): void
    {
        $action = $this->makeAction(['age' => '25'], ['age' => 'int']);
        $this->assertEmpty($action->validate());
    }

    public function testEmailRule(): void
    {
        $action = $this->makeAction(['email' => 'not-an-email'], ['email' => 'required|email']);
        $errors = $action->validate();
        $this->assertArrayHasKey('email', $errors);
    }

    public function testMinLenRule(): void
    {
        $action = $this->makeAction(['name' => 'ab'], ['name' => 'required|minLen:5']);
        $errors = $action->validate();
        $this->assertArrayHasKey('name', $errors);
    }

    public function testMaxRule(): void
    {
        $action = $this->makeAction(['age' => '200'], ['age' => 'int|max:120']);
        $errors = $action->validate();
        $this->assertArrayHasKey('age', $errors);
    }

    // ---------------------------------------------------------------------------
    // New rule: regex
    // ---------------------------------------------------------------------------

    public function testRegexRulePass(): void
    {
        $action = $this->makeAction(
            ['phone' => '13812345678'],
            ['phone' => 'required|regex:/^1[3-9]\d{9}$/']
        );
        $this->assertEmpty($action->validate());
    }

    public function testRegexRuleFail(): void
    {
        $action = $this->makeAction(
            ['phone' => '028-12345678'],
            ['phone' => 'required|regex:/^1[3-9]\d{9}$/']
        );
        $errors = $action->validate();
        $this->assertArrayHasKey('phone', $errors);
    }

    public function testRegexSkipsEmpty(): void
    {
        $action = $this->makeAction(
            ['phone' => null],
            ['phone' => 'regex:/^1[3-9]\d{9}$/']  // not required, so null is ok
        );
        $this->assertEmpty($action->validate());
    }

    // ---------------------------------------------------------------------------
    // New rule: in
    // ---------------------------------------------------------------------------

    public function testInRulePass(): void
    {
        $action = $this->makeAction(
            ['status' => 'active'],
            ['status' => 'required|in:active,inactive,pending']
        );
        $this->assertEmpty($action->validate());
    }

    public function testInRuleFail(): void
    {
        $action = $this->makeAction(
            ['status' => 'deleted'],
            ['status' => 'required|in:active,inactive,pending']
        );
        $errors = $action->validate();
        $this->assertArrayHasKey('status', $errors);
    }

    public function testInRuleSkipsEmpty(): void
    {
        $action = $this->makeAction(
            ['status' => ''],
            ['status' => 'in:active,inactive']
        );
        $this->assertEmpty($action->validate());
    }

    // ---------------------------------------------------------------------------
    // New rule: confirmed
    // ---------------------------------------------------------------------------

    public function testConfirmedRulePassDefault(): void
    {
        $action = $this->makeAction(
            ['password' => 'secret123', 'password_confirm' => 'secret123'],
            ['password' => 'required|confirmed']
        );
        $this->assertEmpty($action->validate());
    }

    public function testConfirmedRuleFailDefault(): void
    {
        $action = $this->makeAction(
            ['password' => 'secret123', 'password_confirm' => 'different'],
            ['password' => 'required|confirmed']
        );
        $errors = $action->validate();
        $this->assertArrayHasKey('password', $errors);
    }

    public function testConfirmedRuleExplicitOtherField(): void
    {
        $action = $this->makeAction(
            ['pass' => 'abc', 'pass2' => 'abc'],
            ['pass' => 'confirmed:pass2']
        );
        $this->assertEmpty($action->validate());
    }

    // ---------------------------------------------------------------------------
    // New rule: array
    // ---------------------------------------------------------------------------

    public function testArrayRulePass(): void
    {
        $action = $this->makeAction(
            ['tags' => ['php', 'go']],
            ['tags' => 'array']
        );
        $this->assertEmpty($action->validate());
    }

    public function testArrayRuleFail(): void
    {
        $action = $this->makeAction(
            ['tags' => 'php,go'],
            ['tags' => 'array']
        );
        $errors = $action->validate();
        $this->assertArrayHasKey('tags', $errors);
    }

    // ---------------------------------------------------------------------------
    // New rule: each
    // ---------------------------------------------------------------------------

    public function testEachRulePassString(): void
    {
        $action = $this->makeAction(
            ['tags' => ['php', 'go', 'rust']],
            ['tags' => 'array|each:string']
        );
        $this->assertEmpty($action->validate());
    }

    public function testEachRuleFailInt(): void
    {
        $action = $this->makeAction(
            ['ids' => ['1', 'abc', '3']],
            ['ids' => 'array|each:int']
        );
        $errors = $action->validate();
        $this->assertArrayHasKey('ids', $errors);
    }

    // ---------------------------------------------------------------------------
    // New rule: string
    // ---------------------------------------------------------------------------

    public function testStringRulePassOnString(): void
    {
        $action = $this->makeAction(['title' => 'hello'], ['title' => 'string']);
        $this->assertEmpty($action->validate());
    }

    public function testStringRuleFailOnArray(): void
    {
        $action = $this->makeAction(['title' => ['a', 'b']], ['title' => 'string']);
        $errors = $action->validate();
        $this->assertArrayHasKey('title', $errors);
    }

    // ---------------------------------------------------------------------------
    // Custom rule registration
    // ---------------------------------------------------------------------------

    public function testCustomRulePass(): void
    {
        \Plume\Libs\Action::addRule('is_upper', fn($v) => $v === strtoupper((string) $v));
        $action = $this->makeAction(['code' => 'ABC'], ['code' => 'required|is_upper']);
        $this->assertEmpty($action->validate());
    }

    public function testCustomRuleFail(): void
    {
        \Plume\Libs\Action::addRule('is_upper', fn($v) => $v === strtoupper((string) $v));
        $action = $this->makeAction(['code' => 'abc'], ['code' => 'required|is_upper']);
        $errors = $action->validate();
        $this->assertArrayHasKey('code', $errors);
    }

    public function testCustomRuleOverridePreviousDefinition(): void
    {
        // Second addRule with same name overwrites the first
        \Plume\Libs\Action::addRule('always_fail', fn($v) => true);
        \Plume\Libs\Action::addRule('always_fail', fn($v) => false);
        $action = $this->makeAction(['x' => 'anything'], ['x' => 'always_fail']);
        $errors = $action->validate();
        $this->assertArrayHasKey('x', $errors);
    }

    // ---------------------------------------------------------------------------
    // Regression: first error stops further checks on the same field
    // ---------------------------------------------------------------------------

    public function testFirstErrorStopsField(): void
    {
        $action = $this->makeAction(
            ['age' => ''],
            ['age' => 'required|int|min:18']
        );
        $errors = $action->validate();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('不能为空', $errors['age']);
    }

    // ---------------------------------------------------------------------------
    // Multiple fields: independent errors
    // ---------------------------------------------------------------------------

    public function testMultipleFieldErrors(): void
    {
        $action = $this->makeAction(
            ['name' => '', 'email' => 'bad'],
            ['name' => 'required', 'email' => 'required|email']
        );
        $errors = $action->validate();
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
    }
}
