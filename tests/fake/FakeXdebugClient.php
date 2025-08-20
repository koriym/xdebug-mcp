<?php

namespace Koriym\XdebugMcp\Tests\Fake;

use Koriym\XdebugMcp\XdebugClient;
use Koriym\XdebugMcp\Exceptions\XdebugConnectionException;

class FakeXdebugClient extends XdebugClient
{
    private FakeXdebugSession $session;
    private bool $isConnected = false;

    public function __construct(string $host = '127.0.0.1', int $port = 9003)
    {
        // 親クラスのコンストラクタを呼ばない（ソケット初期化を避ける）
        $this->session = new FakeXdebugSession();
    }

    public function connect(): array
    {
        $this->isConnected = true;
        $initXml = $this->session->getInitResponse();
        return $this->parseXmlString($initXml);
    }

    public function disconnect(): void
    {
        $this->isConnected = false;
    }

    public function setBreakpoint(string $filename, int $line, string $condition = ''): string
    {
        $this->ensureConnected();
        $result = $this->session->setBreakpoint($filename, $line, $condition);
        return $result['id'];
    }

    public function removeBreakpoint(string $breakpointId): void
    {
        $this->ensureConnected();
        $this->session->removeBreakpoint($breakpointId);
    }

    public function stepInto(): array
    {
        $this->ensureConnected();
        return $this->session->stepInto();
    }

    public function stepOver(): array
    {
        $this->ensureConnected();
        return $this->session->stepOver();
    }

    public function stepOut(): array
    {
        $this->ensureConnected();
        return $this->session->stepOut();
    }

    public function continue(): array
    {
        $this->ensureConnected();
        return $this->session->continue();
    }

    public function getStack(): array
    {
        $this->ensureConnected();
        return $this->session->getStack();
    }

    public function getVariables(int $context = 0): array
    {
        $this->ensureConnected();
        return $this->session->getVariables($context);
    }

    public function eval(string $expression): array
    {
        $this->ensureConnected();
        return $this->session->eval($expression);
    }

    public function getStatus(): array
    {
        $this->ensureConnected();
        $state = $this->session->getCurrentState();
        return [
            'command' => 'status',
            'transaction_id' => '10',
            'status' => $state['status'],
            'reason' => 'ok'
        ];
    }

    public function getFeatures(): array
    {
        return [
            'language_supports_threads' => '0',
            'language_name' => 'PHP',
            'language_version' => '8.4.11',
            'encoding' => 'iso-8859-1',
            'protocol_version' => '1.0',
            'supports_async' => '0',
            'data_encoding' => 'base64',
            'breakpoint_languages' => 'PHP',
            'breakpoint_types' => 'line call return exception conditional watch',
            'multiple_sessions' => '0',
            'max_children' => '32',
            'max_data' => '1024',
            'max_depth' => '1'
        ];
    }

    private function ensureConnected(): void
    {
        if (!$this->isConnected) {
            throw new XdebugConnectionException('Not connected to Xdebug session');
        }
    }

    private function parseXmlString(string $xml): array
    {
        $previousUseErrors = libxml_use_internal_errors(true);
        $xmlDoc = simplexml_load_string($xml);
        
        if ($xmlDoc === false) {
            $errors = libxml_get_errors();
            libxml_use_internal_errors($previousUseErrors);
            throw new XdebugConnectionException('Failed to parse XML response');
        }
        
        libxml_use_internal_errors($previousUseErrors);
        return $this->xmlToArray($xmlDoc);
    }

    private function xmlToArray(\SimpleXMLElement $xml): array
    {
        $result = [];
        
        foreach ($xml->attributes() as $key => $value) {
            $result['@attributes'][$key] = (string)$value;
        }

        if ($xml->count() > 0) {
            foreach ($xml->children() as $child) {
                $childArray = $this->xmlToArray($child);
                $childName = $child->getName();
                
                if (isset($result[$childName])) {
                    if (!is_array($result[$childName]) || !isset($result[$childName][0])) {
                        $result[$childName] = [$result[$childName]];
                    }
                    $result[$childName][] = $childArray;
                } else {
                    $result[$childName] = $childArray;
                }
            }
        } else {
            $content = trim((string)$xml);
            if (!empty($content)) {
                $result['#text'] = $content;
            }
        }

        return $result;
    }
}