<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Koriym\XdebugMcp\ContainerHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContainerHelper::class)]
final class ContainerHelperTest extends TestCase
{
    #[Test]
    public function isContainerCommandDetectsDocker(): void
    {
        $this->assertTrue(ContainerHelper::isContainerCommand(['docker', 'run', 'php']));
    }

    #[Test]
    public function isContainerCommandDetectsPodman(): void
    {
        $this->assertTrue(ContainerHelper::isContainerCommand(['podman', 'run', 'php']));
    }

    #[Test]
    public function isContainerCommandDetectsKubectl(): void
    {
        $this->assertTrue(ContainerHelper::isContainerCommand(['kubectl', 'exec', 'pod']));
    }

    #[Test]
    public function isContainerCommandReturnsFalseForNonContainer(): void
    {
        $this->assertFalse(ContainerHelper::isContainerCommand(['php', 'script.php']));
    }

    #[Test]
    public function findPhpCommandIndexReturnsCorrectIndex(): void
    {
        $this->assertSame(2, ContainerHelper::findPhpCommandIndex(['docker', 'run', 'php', 'script.php']));
    }

    #[Test]
    public function findPhpCommandIndexReturnsLastOccurrence(): void
    {
        $this->assertSame(4, ContainerHelper::findPhpCommandIndex(['docker', 'run', 'php', 'image', 'php', 'script.php']));
    }

    #[Test]
    public function findPhpCommandIndexDetectsVersionedPhp(): void
    {
        $this->assertSame(2, ContainerHelper::findPhpCommandIndex(['docker', 'run', 'php8.4', 'script.php']));
    }

    #[Test]
    public function findPhpCommandIndexReturnsFalseWhenNotFound(): void
    {
        $this->assertFalse(ContainerHelper::findPhpCommandIndex(['docker', 'run', 'node', 'script.js']));
    }

    #[Test]
    public function getContainerClientHostReturnsDockerHost(): void
    {
        $this->assertSame('host.docker.internal', ContainerHelper::getContainerClientHost(['docker', 'run']));
    }

    #[Test]
    public function getContainerClientHostReturnsPodmanHost(): void
    {
        $this->assertSame('host.containers.internal', ContainerHelper::getContainerClientHost(['podman', 'run']));
    }

    #[Test]
    public function getContainerClientHostReturnsKubectlHost(): void
    {
        $this->assertSame('host.docker.internal', ContainerHelper::getContainerClientHost(['kubectl', 'exec']));
    }

    #[Test]
    public function getContainerClientHostReturnsDefaultForUnknown(): void
    {
        $this->assertSame('host.docker.internal', ContainerHelper::getContainerClientHost(['unknown', 'command']));
    }

    #[Test]
    public function findDockerEnvInsertIndexFindsRunCommand(): void
    {
        $this->assertSame(2, ContainerHelper::findDockerEnvInsertIndex(['docker', 'run', '--rm', 'php']));
    }

    #[Test]
    public function findDockerEnvInsertIndexFindsExecCommand(): void
    {
        $this->assertSame(2, ContainerHelper::findDockerEnvInsertIndex(['docker', 'exec', 'container', 'php']));
    }

    #[Test]
    public function findDockerEnvInsertIndexReturnsFalseWhenNotFound(): void
    {
        $this->assertFalse(ContainerHelper::findDockerEnvInsertIndex(['docker', 'ps']));
    }

    #[Test]
    public function findScriptIndexFindsScriptAfterPhp(): void
    {
        $this->assertSame(3, ContainerHelper::findScriptIndex(['docker', 'run', 'php', 'script.php'], 2));
    }

    #[Test]
    public function findScriptIndexSkipsPhpOptions(): void
    {
        $this->assertSame(4, ContainerHelper::findScriptIndex(['docker', 'run', 'php', '-d', 'error_reporting=E_ALL', 'script.php'], 2));
    }

    #[Test]
    public function findScriptIndexReturnsFalseWhenNoScript(): void
    {
        $this->assertFalse(ContainerHelper::findScriptIndex(['docker', 'run', 'php', '-v'], 2));
    }
}
