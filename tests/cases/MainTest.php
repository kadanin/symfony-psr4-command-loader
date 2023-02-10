<?php

namespace tests\cases;

use Kadanin\SymfonyPsr4CommandLoader\NamespaceCommandLoader;
use PHPUnit\Framework\TestCase;

class MainTest extends TestCase
{
    /**
     * @throws \Kadanin\SymfonyPsr4CommandLoader\LoadingError
     */
    public function testMain(): void
    {
        $this->doTest(
            new NamespaceCommandLoader(__DIR__ . '/../../composer.json'),
            \App\Command\HelloCommand::class,
            \App\Command\App\GoodByeCommand::class
        );
    }

    public function testAnother(): void
    {
        $this->doTest(
            new NamespaceCommandLoader(__DIR__ . '/../project/composer.json', 'TestApp\Commands'),
            \TestApp\Commands\HelloCommand::class,
            \TestApp\Commands\App\GoodByeCommand::class
        );
    }

    /**
     * @throws \Kadanin\SymfonyPsr4CommandLoader\LoadingError
     */
    private function doTest(NamespaceCommandLoader $loader, string $helloClass, string $byeClass): void
    {
        self::assertTrue($loader->has('hello'));
        self::assertTrue($loader->has('app:good-bye'));
        self::assertInstanceOf($helloClass, $loader->get('hello'));
        self::assertInstanceOf($byeClass, $loader->get('app:good-bye'));
        static::assertEquals(['hello', 'app:good-bye'], $loader->getNames());
    }
}
