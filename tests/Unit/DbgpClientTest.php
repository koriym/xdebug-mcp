<?php

declare(strict_types=1);

namespace Koriym\XdebugMcp\Tests\Unit;

use Amp\Socket\Socket;
use Koriym\XdebugMcp\DbgpClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function strlen;
use function substr;

class DbgpClientTest extends TestCase
{
    public function testSendCommandAppendsDataAfterDoubleDash(): void
    {
        $written = '';
        $socket = $this->socketWithCannedResponse(
            '<?xml version="1.0"?><response command="eval" status="break"/>',
            $written,
        );

        $client = new DbgpClient($socket, 0.0, null, 1);
        $client->sendCommand('eval', [], 'YWJj');

        $this->assertSame("eval -i 1 -- YWJj\0", $written);
    }

    public function testSendCommandRendersOptionsWithSingleDash(): void
    {
        $written = '';
        $socket = $this->socketWithCannedResponse(
            '<?xml version="1.0"?><response/>',
            $written,
        );

        $client = new DbgpClient($socket, 0.0, null, 5);
        $client->sendCommand('property_get', ['n' => '$foo']);

        $this->assertSame("property_get -i 5 -n \$foo\0", $written);
    }

    public function testSendCommandCombinesOptionsAndData(): void
    {
        $written = '';
        $socket = $this->socketWithCannedResponse(
            '<?xml version="1.0"?><response/>',
            $written,
        );

        $client = new DbgpClient($socket, 0.0, null, 7);
        $client->sendCommand('eval', ['d' => 2], 'YWJj');

        $this->assertSame("eval -i 7 -d 2 -- YWJj\0", $written);
    }

    private function socketWithCannedResponse(string $payload, string &$written): Socket&MockObject
    {
        $socket = $this->createMock(Socket::class);
        $socket->method('isClosed')->willReturn(false);
        $socket->method('isWritable')->willReturn(true);
        $socket->method('isReadable')->willReturn(true);
        $socket->method('write')->willReturnCallback(
            static function (string $data) use (&$written): void {
                $written .= $data;
            },
        );

        $frame = strlen($payload) . "\0" . $payload . "\0";
        $offset = 0;
        $socket->method('read')->willReturnCallback(
            static function ($cancellation = null, int|null $limit = null) use ($frame, &$offset): string|null {
                if ($offset >= strlen($frame)) {
                    return null;
                }

                $take = $limit ?? 1;
                $chunk = substr($frame, $offset, $take);
                $offset += strlen($chunk);

                return $chunk;
            },
        );

        return $socket;
    }
}
