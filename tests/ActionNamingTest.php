<?php

declare(strict_types=1);

class ActionNamingTest extends \PHPUnit\Framework\TestCase
{
    public function testToLegacySimple(): void
    {
        $this->assertSame('web_home_action', ActionNaming::toLegacy('web', 'home'));
    }

    public function testToLegacyNestedPath(): void
    {
        $sep = DIRECTORY_SEPARATOR;
        $this->assertSame(
            'web_user_profile_action',
            ActionNaming::toLegacy('web', 'user' . $sep . 'profile')
        );
    }

    public function testToPsr4Simple(): void
    {
        $this->assertSame('App\\Web\\Actions\\HomeAction', ActionNaming::toPsr4('web', 'home'));
    }

    public function testToPsr4NestedPath(): void
    {
        $sep = DIRECTORY_SEPARATOR;
        $this->assertSame(
            'App\\Web\\Actions\\User\\ProfileAction',
            ActionNaming::toPsr4('web', 'user' . $sep . 'profile')
        );
    }

    public function testResolveReturnsLegacyWhenLegacyClassExists(): void
    {
        // Define a legacy-named class for this test
        if (!class_exists('web_testlegacy_action')) {
            eval('class web_testlegacy_action {}');
        }
        $result = ActionNaming::resolve('web', 'testlegacy');
        $this->assertSame('web_testlegacy_action', $result);
    }

    public function testResolveReturnsPsr4WhenPsr4ClassExists(): void
    {
        if (!class_exists('App\\Web\\Actions\\TestPsr4Action')) {
            eval('namespace App\\Web\\Actions; class TestPsr4Action {}');
        }
        $result = ActionNaming::resolve('web', 'testPsr4');
        $this->assertSame('App\\Web\\Actions\\TestPsr4Action', $result);
    }

    public function testResolveFallsBackToLegacyWhenNeitherExists(): void
    {
        $result = ActionNaming::resolve('web', 'nonexistent_xyz');
        $this->assertSame('web_nonexistent_xyz_action', $result);
    }

    public function testModuleNameIsPreservedCaseSensitively(): void
    {
        $this->assertSame('Admin_dashboard_action', ActionNaming::toLegacy('Admin', 'dashboard'));
        $this->assertSame('App\\Admin\\Actions\\DashboardAction', ActionNaming::toPsr4('Admin', 'dashboard'));
    }
}
