<?php

namespace Kadanin\SymfonyPsr4CommandLoader;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\String\UnicodeString;

use function Symfony\Component\String\u;

class NamespaceCommandLoader implements CommandLoaderInterface
{
    private const NAMESPACE_SEPARATOR = '\\';
    private const COMPOSER_DIRECTORY_SEPARATOR = '/';
    private string $composerJson;
    private string $commandsNameSpace;
    /**
     * @var array<string, string>
     */
    private array $commandClassName = [];
    /**
     * @var array<string,Command>
     */
    private array $commands = [];
    private ?string $rootDir = null;

    public function __construct(string $composerJson, string $commandsNameSpace = 'App\Command')
    {
        $this->composerJson      = \realpath($composerJson);
        $this->commandsNameSpace = \trim($commandsNameSpace, '\\');
    }

    /**
     * @inheritDoc
     */
    public function get(string $name): Command
    {
        return $this->commands[$name] ?? ($this->commands[$name] = new ($this->commandClassName($name))());
    }

    /**
     * @inheritDoc
     */
    public function has(string $name): bool
    {
        if (isset($this->commands[$name])) {
            return true;
        }
        $commandClassName = $this->commandClassName($name);
        return \class_exists($commandClassName);
    }

    /**
     * @return string[]
     * @throws \Kadanin\SymfonyPsr4CommandLoader\LoadingError
     */
    public function getNames(): array
    {
        $commandsDirectory = $this->commandsDirectory();
        $len               = mb_strlen($commandsDirectory);
        $offset            = $len + 1;
        $iterator          = new \RegexIterator(
            new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($commandsDirectory, \RecursiveDirectoryIterator::KEY_AS_PATHNAME)),
            '/.*Command\.php$/',
            \RecursiveRegexIterator::GET_MATCH
        );
        $result            = [];
        foreach ($iterator as $matches) {
            $relative  = \mb_substr($matches[0], $offset);
            $noPostfix = \mb_substr($relative, 0, -11);
            $result[]  = \implode(
                ':',
                \array_map(static function (string $value) {
                    return u($value)->snake()->lower()->replace('_', '-');
                }, \explode('/', $noPostfix))
            );
        }
        return $result;
    }

    private function commandClassName(string $name): string
    {
        return $this->commandClassName[$name] ?? ($this->commandClassName[$name] = "{$this->commandsNameSpace}\\{$this->commandToClassRelated($name)}Command");
    }

    private function commandToClassRelated(string $name): string
    {
        return \implode(
            self::NAMESPACE_SEPARATOR,
            \array_map(static function (UnicodeString $value) {
                return $value->camel()->title();
            }, u($name)->split(':'))
        );
    }

    /**
     * @return string
     * @throws \Kadanin\SymfonyPsr4CommandLoader\LoadingError
     */
    private function commandsDirectory(): string
    {
        $commandsNameSpace = $this->commandsNameSpace;
        $psrMap            = $this->psrMap();
        foreach ($psrMap as $namespacePrefix => $psrSubDir) {
            $namespacePrefix = \rtrim($namespacePrefix, '\\');
            if (0 !== \mb_strpos($commandsNameSpace, $namespacePrefix)) {
                continue;
            }
            $psrSubDir    = \rtrim($psrSubDir, self::COMPOSER_DIRECTORY_SEPARATOR);
            $subDir       = $this->normalizeSeparators($psrSubDir, self::COMPOSER_DIRECTORY_SEPARATOR);
            $namespaceDir = $this->normalizeSeparators(\mb_substr($commandsNameSpace, \mb_strlen($namespacePrefix) + 1), self::NAMESPACE_SEPARATOR);
            $rootDir      = $this->rootDir();
            return $rootDir . \DIRECTORY_SEPARATOR . $subDir . \DIRECTORY_SEPARATOR . $namespaceDir;
        }
        throw new LoadingError("There is no PSR-4 autoload entry for {$commandsNameSpace} in composer.json");
    }

    /**
     * @return array
     * @throws \Kadanin\SymfonyPsr4CommandLoader\LoadingError
     */
    private function psrMap(): array
    {
        $json = $this->fileGetContents($this->composerJson);
        try {
            $data = \json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $jsonException) {
            throw new LoadingError("Error Loading {$this->composerJson}: {$jsonException->getMessage()}", $jsonException->getCode(), $jsonException);
        }
        $result = $data['autoload']['psr-4'] ?? null;
        if (null === $result) {
            throw new LoadingError('There is no PSR-4 autoload entry in composer.json');
        }

        return $result;
    }

    /**
     * @param string $composerJson
     *
     * @return string
     * @throws \Kadanin\SymfonyPsr4CommandLoader\LoadingError
     */
    private function fileGetContents(string $composerJson): string
    {
        try {
            $contents = \file_get_contents($composerJson);
        } catch (\Throwable $throwable) {
            throw new LoadingError("Error Loading {$composerJson}: {$throwable->getMessage()}", $throwable->getCode(), $throwable);
        }

        if (false === $contents) {
            throw new LoadingError("Error Loading {$composerJson}");
        }

        return $contents;
    }

    private function normalizeSeparators(string $path, string $currentStringSeparator)
    {
        return (\DIRECTORY_SEPARATOR === $currentStringSeparator)
            ? $path
            : \str_replace($currentStringSeparator, \DIRECTORY_SEPARATOR, $path);
    }

    private function rootDir(): string
    {
        return $this->rootDir ?? ($this->rootDir = \dirname($this->composerJson));
    }
}
