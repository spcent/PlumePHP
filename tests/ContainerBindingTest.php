<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'PlumePHP.php';

interface ContainerTestLogger {
    public function log(string $msg): void;
}

class ContainerTestLoggerImpl implements ContainerTestLogger {
    public array $messages = [];
    public function log(string $msg): void { $this->messages[] = $msg; }
}

class ContainerBindingTest extends \PHPUnit\Framework\TestCase
{
    private PlumeContainer $container;

    public function setUp(): void
    {
        $loader = new PlumeLoader();
        $loader->register('concreteLogger', ContainerTestLoggerImpl::class);
        $this->container = new PlumeContainer($loader);
    }

    public function testHasReturnsTrueForRegisteredService(): void
    {
        $this->assertTrue($this->container->has('concreteLogger'));
    }

    public function testHasReturnsFalseForUnknown(): void
    {
        $this->assertFalse($this->container->has('unknown'));
    }

    public function testGetRegisteredService(): void
    {
        $obj = $this->container->get('concreteLogger');
        $this->assertInstanceOf(ContainerTestLoggerImpl::class, $obj);
    }

    public function testGetThrowsNotFoundForUnknown(): void
    {
        $this->expectException(PlumeNotFoundException::class);
        $this->container->get('unknown');
    }

    public function testBindInterfaceToConcrete(): void
    {
        $this->container->bind(ContainerTestLogger::class, ContainerTestLoggerImpl::class);
        $this->assertTrue($this->container->has(ContainerTestLogger::class));
        $obj = $this->container->get(ContainerTestLogger::class);
        $this->assertInstanceOf(ContainerTestLogger::class, $obj);
    }

    public function testBindFactory(): void
    {
        $this->container->bindFactory('custom', function (PlumeContainer $c) {
            $impl = new ContainerTestLoggerImpl();
            $impl->log('from-factory');
            return $impl;
        });

        $this->assertTrue($this->container->has('custom'));
        $obj = $this->container->get('custom');
        $this->assertInstanceOf(ContainerTestLoggerImpl::class, $obj);
        $this->assertSame(['from-factory'], $obj->messages);
    }

    public function testCircularDependencyDetected(): void
    {
        $this->container->bindFactory('circular', function (PlumeContainer $c) {
            return $c->get('circular');
        });

        $this->expectException(PlumeContainerException::class);
        $this->expectExceptionMessageMatches('/Circular dependency/');
        $this->container->get('circular');
    }

    public function testFactoryReceivesContainer(): void
    {
        $received = null;
        $this->container->bindFactory('probe', function (PlumeContainer $c) use (&$received) {
            $received = $c;
            return new \stdClass();
        });
        $this->container->get('probe');
        $this->assertSame($this->container, $received);
    }

    public function testBindingAbstractClassThrows(): void
    {
        $this->container->bind('abstractTest', ContainerTestLogger::class);
        $this->expectException(PlumeContainerException::class);
        $this->expectExceptionMessageMatches('/Cannot instantiate abstract class or interface/');
        $this->container->get('abstractTest');
    }
}
