<?php

namespace XdebugMcp\Tests\Fake;

class FakeXdebugSession
{
    private array $callStack = [];
    private array $variables = [];
    private array $breakpoints = [];
    private int $currentLine = 1;
    private string $currentFile = '';
    private int $breakpointIdCounter = 1;
    private string $status = 'starting';

    public function __construct()
    {
        $this->initializeSession();
    }

    private function initializeSession(): void
    {
        $this->currentFile = __DIR__ . '/sample.php';
        $this->currentLine = 1;
        $this->status = 'break';
        
        $this->callStack = [
            [
                'level' => 0,
                'type' => 'file',
                'filename' => $this->currentFile,
                'lineno' => $this->currentLine,
                'where' => 'main'
            ]
        ];

        $this->variables = [
            'local' => [
                '$numbers' => [
                    'type' => 'array',
                    'size' => 5,
                    'value' => [1, 2, 3, 4, 5]
                ],
                '$sum' => [
                    'type' => 'int', 
                    'value' => 0
                ],
                '$i' => [
                    'type' => 'int',
                    'value' => 0
                ]
            ],
            'global' => [
                '$_GET' => ['type' => 'array', 'size' => 0, 'value' => []],
                '$_POST' => ['type' => 'array', 'size' => 0, 'value' => []]
            ]
        ];
    }

    public function getInitResponse(): string
    {
        return '<?xml version="1.0" encoding="iso-8859-1"?>
<init xmlns="urn:debugger_protocol_v1" 
      xmlns:xdebug="https://xdebug.org/dbgp/xdebug" 
      fileuri="file://' . $this->currentFile . '" 
      language="PHP" 
      xdebug:language_version="8.4.11" 
      protocol_version="1.0" 
      appid="123">
</init>';
    }

    public function setBreakpoint(string $filename, int $line, string $condition = ''): array
    {
        $id = (string)$this->breakpointIdCounter++;
        $this->breakpoints[$id] = [
            'filename' => $filename,
            'line' => $line,
            'condition' => $condition,
            'enabled' => true
        ];

        return [
            'command' => 'breakpoint_set',
            'transaction_id' => '1',
            'id' => $id
        ];
    }

    public function removeBreakpoint(string $id): array
    {
        unset($this->breakpoints[$id]);
        return [
            'command' => 'breakpoint_remove',
            'transaction_id' => '2'
        ];
    }

    public function stepInto(): array
    {
        $this->currentLine++;
        $this->updateExecutionState();
        
        return [
            'command' => 'step_into',
            'transaction_id' => '3',
            'status' => $this->status,
            'reason' => 'ok'
        ];
    }

    public function stepOver(): array
    {
        $this->currentLine++;
        $this->updateExecutionState();
        
        return [
            'command' => 'step_over', 
            'transaction_id' => '4',
            'status' => $this->status,
            'reason' => 'ok'
        ];
    }

    public function stepOut(): array
    {
        if (count($this->callStack) > 1) {
            array_shift($this->callStack);
            $this->currentLine = $this->callStack[0]['lineno'] ?? $this->currentLine + 1;
        } else {
            $this->currentLine++;
        }
        $this->updateExecutionState();
        
        return [
            'command' => 'step_out',
            'transaction_id' => '5', 
            'status' => $this->status,
            'reason' => 'ok'
        ];
    }

    public function continue(): array
    {
        foreach ($this->breakpoints as $bp) {
            if ($bp['enabled'] && $bp['filename'] === $this->currentFile) {
                $this->currentLine = $bp['line'];
                $this->status = 'break';
                break;
            }
        }
        
        if ($this->status !== 'break') {
            $this->currentLine += 5;
            $this->status = 'stopping';
        }
        
        $this->updateExecutionState();
        
        return [
            'command' => 'run',
            'transaction_id' => '6',
            'status' => $this->status,
            'reason' => $this->status === 'break' ? 'breakpoint' : 'ok'
        ];
    }

    public function getStack(): array
    {
        return [
            'command' => 'stack_get',
            'transaction_id' => '7',
            'stack' => $this->callStack
        ];
    }

    public function getVariables(int $context = 0): array
    {
        $contextName = $context === 0 ? 'local' : 'global'; 
        $vars = $this->variables[$contextName] ?? [];
        
        $properties = [];
        foreach ($vars as $name => $data) {
            $properties[] = [
                'name' => $name,
                'fullname' => $name,
                'type' => $data['type'],
                'size' => $data['size'] ?? strlen((string)$data['value']),
                'value' => $this->formatValue($data['value'])
            ];
        }
        
        return [
            'command' => 'context_get',
            'transaction_id' => '8',
            'context' => $context,
            'properties' => $properties
        ];
    }

    public function eval(string $expression): array
    {
        $result = '';
        $type = 'string';
        
        switch ($expression) {
            case '$sum':
                $result = (string)$this->variables['local']['$sum']['value'];
                $type = 'int';
                break;
            case '$numbers':
                $result = json_encode($this->variables['local']['$numbers']['value']);
                $type = 'array';
                break;
            case '2 + 3':
                $result = '5';
                $type = 'int';
                break;
            case 'count($numbers)':
                $result = '5';
                $type = 'int';
                break;
            default:
                $result = "Unknown expression: $expression";
        }
        
        return [
            'command' => 'eval',
            'transaction_id' => '9',
            'success' => '1',
            'result' => [
                'type' => $type,
                'value' => $result
            ]
        ];
    }

    private function updateExecutionState(): void
    {
        $this->callStack[0]['lineno'] = $this->currentLine;
        
        switch ($this->currentLine) {
            case 3:
                $this->variables['local']['$sum']['value'] = 15;
                break;
            case 5:
                $this->callStack[] = [
                    'level' => 1,
                    'type' => 'call',
                    'filename' => $this->currentFile,
                    'lineno' => $this->currentLine,
                    'where' => 'fibonacci'
                ];
                $this->variables['local']['$n'] = ['type' => 'int', 'value' => 6];
                break;
            case 8:
                $this->variables['local']['$user_data'] = [
                    'type' => 'array',
                    'size' => 3,
                    'value' => [
                        'name' => 'John',
                        'age' => 30,
                        'city' => 'Tokyo'
                    ]
                ];
                break;
        }

        if ($this->currentLine > 10) {
            $this->status = 'stopping';
        }
    }

    private function formatValue($value): string
    {
        if (is_array($value)) {
            return json_encode($value);
        }
        return (string)$value;
    }

    public function getCurrentState(): array
    {
        return [
            'file' => $this->currentFile,
            'line' => $this->currentLine,
            'status' => $this->status,
            'breakpoints' => count($this->breakpoints),
            'stack_depth' => count($this->callStack)
        ];
    }
}