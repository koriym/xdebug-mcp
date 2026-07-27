# McpServer / DebugServer 責務分割 実装計画

## 1. 目的と現状

### 1.1 背景
- `src/McpServer.php` (1434 行) が JSON-RPC 処理、ツール定義、CLI 引数正規化、6 ツールの実行、プロンプト処理を全部抱えている。
- `src/DebugServer.php` (3889 行) が DBGp プロトコル、ソケット待受、子プロセス起動、ステップ記録、変数 diff、ウォッチ、JSON 出力、ブレークポイント管理、HTTP API、コンソール REPL、Claude 連携を混在させている。

### 1.2 目標
- MCP プロトコル層と「Xdebug デバッグエンジン」層を分離する。
- 既存の公開 I/F (`bin/xdebug-mcp`、`bin/xstep`、`bin/xback`) を壊さない。
- 現在の JSON 出力スキーマと文言を維持する。
- 環境依存を減らし、ユニットテスト可能にする。

---

## 2. 分割後のクラス構成

### 2.1 新しい名前空間

| 名前空間 | 配置ディレクトリ | 責務 |
|---|---|---|
| `Koriym\XdebugMcp` | `src/` | ファサード・公開 API |
| `Koriym\XdebugMcp\Mcp` | `src/Mcp/` | MCP/JSON-RPC 層（ツール定義・実行・CLI 正規化） |
| `Koriym\XdebugMcp\Dbgp` | `src/Dbgp/` | DBGp トランスポート（既存 `DbgpXml` 含む） |
| `Koriym\XdebugMcp\Debugging` | `src/Debugging/` | デバッグエンジン本体 |
| `Koriym\XdebugMcp\Output` | `src/Output/` | JSON / 人間可読出力のフォーマッタ |

### 2.2 クラス一覧と依存関係

```text
McpServer
├── ToolRegistry
├── ToolExecutor  implements ToolExecutorInterface
│   ├── ScriptArgumentValidator
│   └── ToolRunnerInterface
│       ├── XtraceRunner
│       ├── XstepRunner
│       ├── XprofileRunner
│       ├── XcoverageRunner
│       ├── XbackRunner
│       └── XcompareRunner (既存 CompareRunner をラップ)
└── PromptExecutor implements PromptExecutorInterface
    └── ToolExecutor / ScriptArgumentValidator

DebugServer (ファサード)
├── DebugSession
│   ├── DbgpTransport implements DbgpTransportInterface
│   ├── TargetProcessLauncher
│   ├── CleanupManager
│   └── PortDiagnostics
├── BreakpointManager
├── DebugCommandEngine
│   └── DbgpTransport
├── VariableInspector
│   └── DbgpTransport
├── StackParser
├── StepRecorder
│   ├── VariableInspector
│   ├── VariableDiffBuilder
│   └── WatchEvaluator
├── VariableDiffBuilder
├── JsonOutputFormatter
├── TraceInfoResolver
├── ConsoleDebugger
│   ├── DebugCommandEngine
│   ├── VariableInspector
│   ├── StackParser
│   └── ClaudeAnalyzer
├── HttpDebugApi
│   └── DebugServer 公開メソッド
└── PathUriConverter (既存 PathNormalizer のラッパー)
```

---

## 3. 各クラスの責務とメソッド移動マップ

### 3.1 MCP 層 (`src/Mcp/`)

#### `McpServer` (`src/McpServer.php`) に残すもの
- コンストラクタ `__construct()` (68)
- サーバーループ `__invoke()` (314)
- レスポンスエンコード `encodeResponse()` (347)
- リクエスト受信 `handleLine()` (371)
- メソッドディスパッチ `handleRequest()` (404)
- 初期化応答 `handleInitialize()` (428)
- リソース一覧 `handleResourcesList()` (459)

#### `ToolRegistry` (`src/Mcp/ToolRegistry.php`)
- ツール定義 `initializeTools()` (98) → `registerTools()`
- プロンプト定義もここで管理。`handleToolsList()` (454) / `handlePromptsList()` (466) は `McpServer` が `ToolRegistry` に委譲。

#### `ScriptArgumentValidator` (`src/Mcp/ScriptArgumentValidator.php`)
- スクリプト引数処理 `processScriptArgument()` (719)
- PHP バイナリ検証 `validatePhpBinaryScript()` (755)
- インラインコード判定 `isPhpInlineCodeScript()` (767)
- ブレークポイント検証 `validateBreakpoints()` (779)

#### `ToolExecutor` (`src/Mcp/ToolExecutor.php`) implements `ToolExecutorInterface`
- ツール呼び出し `executeToolCall()` (875)
- テキスト抽出 `extractResultText()` (916)
- 各ツール実行
  - `executeXTrace()` (941) → 内部で `XtraceRunner` 使用
  - `executeXDebug()` (1002) → `XstepRunner` 使用
  - `executeXProfile()` (1123) → `XprofileRunner`
  - `executeXCoverage()` (1184) → `XcoverageRunner`
  - `executeXBacktrace()` (1268) → `XbackRunner`
  - `executeXCompare()` (1351) → `XcompareRunner`

#### `PromptExecutor` (`src/Mcp/PromptExecutor.php`) implements `PromptExecutorInterface`
- プロンプト取得 `handlePromptsGet()` (651)
- 位置引数マッピング `mapPositionalArgs()` (690)
- CLI 引数正規化は `CLIParamsNormalizer` (既存) をそのまま使用。

#### 各ツール Runner (`src/Mcp/Tool/*Runner.php`)
- `ToolRunnerInterface::run(array $args): JsonRpcResponse`
- `XtraceRunner`, `XstepRunner`, `XprofileRunner`, `XcoverageRunner`, `XbackRunner`
- `XcompareRunner` は既存 `src/CompareRunner.php` をそのまま使うか、薄いアダプターにする。

---

### 3.2 DBGp トランスポート層 (`src/Dbgp/`)

#### `DbgpTransportInterface` (`src/Dbgp/DbgpTransportInterface.php`)
```php
interface DbgpTransportInterface
{
    public function startListener(int $preferredPort, bool $isDockerCommand): int;
    public function acceptConnection(float $timeout): void;
    public function sendCommand(string $command, array $params = []): string;
    public function isConnected(): bool;
    public function disconnect(): void;
}
```

#### `DbgpTransport` (`src/Dbgp/DbgpTransport.php`)
- ポート待受 `listenOnAvailablePort()` (272)
- リスナー起動 `startXdebugListener()` (290) のソケット部分
- コマンド送信 `sendCommand()` (976)
- コマンド組み立て `buildDbgpCommand()` (954)
- トランザクション ID 管理 `getNextTransactionId()` (1019)
- 接続確認 `isConnected()` (1027)
- DBGp フレーム読み込み `readDbgpFrame()` (2515), `fillDbgpBufferUntilNul()` (2558), `fillDbgpBufferTo()` (2573), `readDbgpChunk()` (2580)

---

### 3.3 デバッグエンジン層 (`src/Debugging/`)

#### `DebugServer` (`src/DebugServer.php`) はファサードとして維持
残す公開メソッド（署名を変更しない）:
- `__construct(string $targetScript, int $debugPort, ?int $initialBreakpointLine = null, array $options = [], bool $jsonMode = false)` (188)
- `__invoke(): void` (220)
- `enableHttpMode(?int $httpPort = null): void` (1495)
- `isConnected(): bool` (1027)
- `handleStepCommand(): bool` (1979)
- `handleStepOverCommand(): bool` (2041)
- `handleStepOutCommand(): bool` (2100)
- `handleContinueCommand(): bool` (2159)
- `getCurrentVariables(): array` (3034)
- `getBacktraceResult(): array` (3869)
- `getCurrentLocation(): string` (3839)
- `setShouldExit(bool $exit): void` (3885)
- `emergencyCleanup(): void` (2674)

内部実装は各コンポーネントに委譲し、段階的にメソッド本体を移動する。

#### `DebugSession` (`src/Debugging/DebugSession.php`)
- 全体オーケストレーション `__invoke()` (220) の中身
- リスナー/子プロセス/ハンドラの並行制御
- ターゲットスクリプト実行 `executeTargetScript()` (358)
- デバッグセッション制御 `handleDebugSession()` (539)
- デバッグシーケンス `performDebugSequence()` (560)
- クリーンアップ `cleanup()` (2707), `gracefulExit()` (2654)
- ログ `log()` (2593)

#### `TargetProcessLauncher` (`src/Debugging/TargetProcessLauncher.php`)
- `executeTargetScript()` (358) 内のコマンド構築・子プロセス起動部分
- Docker / ローカル PHP 分岐
- Xdebug 引数テンプレート生成

#### `CleanupManager` (`src/Debugging/CleanupManager.php`)
- `emergencyCleanup()` (2674)
- シャットダウンハンドラ登録
- 子プロセス kill

#### `PortDiagnostics` (`src/Debugging/PortDiagnostics.php`)
- `logExistingSessions()` (2612)
- `findPidsListeningOnPort()` (2629)
- `parsePidList()` (2642)

#### `BreakpointManager` (`src/Debugging/BreakpointManager.php`)
- 条件付きブレークポイント設定 `setupConditionalBreakpoints()` (1141)
- ブレークポイント設定 `setBreakpoint()` (1083)
- ラベル生成 `makeBreakpointLabel()` (1183)
- ロケーション解決 `breakpointReferenceForLocation()` (1198), `resolveBreakpointForLocation()` (1219)

#### `DebugCommandEngine` (`src/Debugging/DebugCommandEngine.php`)
- 実行再開 `continueExecution()` (1239)
- ステップ `stepOver()` (1256), `stepInto()` (1269), `stepOut()` (1282)
- 実行完了判定 `isExecutionComplete()` (2493)
- ブレーク判定 `didBreak()` (1461)

#### `VariableInspector` (`src/Debugging/VariableInspector.php`)
- 変数取得 `getVariables()` (1303), `getCurrentVariables()` (3034)
- 式評価 `evaluateExpression()` (1311)
- ウォッチ評価 `evaluateWatchExpression()` (1334), `getWatchContentHash()` (1416)
- 子詳細 `extractChildDetails()` (3099)
- JSON エンコード評価 `getJsonEncodeOutput()` (3170), `buildJsonEncodeExpression()` (3224)
- 文字列切り詰め `truncateStringValue()` (2801), `truncateVariableDisplay()` (2810)
- 最大バイト/深さ `getMaxValueBytes()` (2787), `getMaxDepth()` (2794)

#### `StackParser` (`src/Debugging/StackParser.php`)
- スタック取得 `getStack()` (1295), `getStackTrace()` (3488)
- フレーム解析 `parseStackFrames()` (3697)
- ブレーク応答からの位置抽出 `extractLocationFromBreakResponse()` (3788), `extractLocationDataFromBreakResponse()` (3803)
- 現在位置 `getCurrentLocation()` (3839)
- 状態キャプチャ `captureCurrentDebugState()` (3650)

#### `VariableDiffBuilder` (`src/Debugging/VariableDiffBuilder.php`)
- `buildVariableDiff()` (3260)
- `createVariableDiffEntry()` (3290)
- `parseVariableDisplay()` (3318)
- `buildShallowKeyDiff()` (3357)
- `buildShallowValueMap()` (3416)
- `normalizeShallowValueMap()` (3427)
- `normalizeShallowChildMap()` (3448)
- `stringifyShallowValue()` (3462)

#### `StepRecorder` (`src/Debugging/StepRecorder.php`)
- ステップ記録 `performStepTrace()` (619)
- exit-on-break 時の記録 `processStepRecordingBreakpoints()` (908)
- ウォッチ変化作成 `createWatchChange()` (853)
- ブレーク記録作成 `createRecordedBreak()` (871)

#### `JsonOutputFormatter` (`src/Output/JsonOutputFormatter.php`)
- JSON エンコード `encodeJsonOutput()` (2745)
- ポート情報付加 `addPortInfoToResult()` (2777)
- ステップ記録結果出力 `outputStepRecordingResults()` (2818)
- 複数ブレーク結果出力 `outputMultipleBreakResults()` (3734)

#### `TraceInfoResolver` (`src/Output/TraceInfoResolver.php`)
- トレース情報取得 `getTraceInfo()` (3513)
- トレースファイル出力 `outputTraceFile()` (1834)
- ブレーク時トレース最終化 `finalizeTraceOnBreak()` (1470)

#### `ConsoleDebugger` (`src/Debugging/ConsoleDebugger.php`)
- コンソールセッション `startConsoleSession()` (1589)
- プロンプト `displayPrompt()` (1804)
- 入力 `readUserInputWithTimeout()` (1817)
- ユーザーコマンド実行 `executeUserCommand()` (1912)
- 各コマンドハンドラ
  - `handleStepCommand()` (1979)
  - `handleStepOverCommand()` (2041)
  - `handleStepOutCommand()` (2100)
  - `handleContinueCommand()` (2159)
  - `handlePrintCommand()` (2187)
  - `handleBacktraceCommand()` (2265)
  - `handleListCommand()` (2275)
  - `displayHelp()` (2290)
- 状態表示 `displayCurrentState()` (2308), `displayStackInfo()` (2341), `displayVariableArray()` (2373), `displayVariables()` (2397), `displayPropertyResult()` (2214)
- 値フォーマット `formatVariableValue()` (2443)

#### `HttpDebugApi` (`src/Debugging/HttpDebugApi.php`)
- `enableHttpMode()` (1495) の HTTP 部分
- `startHttpSession()` (1571)
- `createHttpRequestHandler()` (1620) および内部の匿名 RequestHandler クラスを独立クラス化

#### `ClaudeAnalyzer` (`src/Debugging/ClaudeAnalyzer.php`)
- `handleClaudeCommand()` (2896)
- コンテキスト取得 `getCurrentDebugContext()` (2937)
- プロンプト生成 `buildClaudeAnalysisPrompt()` (2974)

#### `PathUriConverter` (`src/Utilities/PathUriConverter.php`)
- パス正規化 `normalisePath()` (1038)
- file:// URI 変換 `toFileUri()` (1046)

#### `XmlResponseParser` (`src/Dbgp/XmlResponseParser.php`)
- `parseXmlResponse()` (2457)
- 既存 `DbgpXml::sanitize()` を利用。

---

## 4. インターフェース定義

### 4.1 MCP 層

```php
// src/Mcp/ToolExecutorInterface.php
namespace Koriym\XdebugMcp\Mcp;

interface ToolExecutorInterface
{
    /** @param array<string, string> $arguments */
    public function execute(string $toolName, array $arguments): string;
}
```

```php
// src/Mcp/PromptExecutorInterface.php
namespace Koriym\XdebugMcp\Mcp;

use Koriym\XdebugMcp\DTO\JsonRpcResponse;

interface PromptExecutorInterface
{
    /** @param array<string, string> $args */
    public function execute(string $promptName, array $args, string|int|null $id): JsonRpcResponse;
}
```

```php
// src/Mcp/ToolRunnerInterface.php
namespace Koriym\XdebugMcp\Mcp;

use Koriym\XdebugMcp\DTO\JsonRpcResponse;

interface ToolRunnerInterface
{
    public function supports(string $toolName): bool;

    /** @param array<string, string> $args */
    public function run(array $args): JsonRpcResponse;
}
```

### 4.2 DBGp 層

```php
// src/Dbgp/DbgpTransportInterface.php
namespace Koriym\XdebugMcp\Dbgp;

interface DbgpTransportInterface
{
    public function startListener(int $preferredPort, bool $isDockerCommand): int;

    public function acceptConnection(float $timeout): void;

    /** @param array<string, string|int> $params */
    public function sendCommand(string $command, array $params = []): string;

    public function isConnected(): bool;

    public function disconnect(): void;
}
```

### 4.3 出力層

```php
// src/Output/OutputFormatterInterface.php
namespace Koriym\XdebugMcp\Output;

interface OutputFormatterInterface
{
    /** @param list<array<string, mixed>> $breaks */
    public function formatMultipleBreaks(array $breaks, int $debugPort, ?int $requestedPort): string;

    /** @param list<array<string, mixed>> $steps */
    public function formatStepRecording(array $breakpoint, array $steps, int $debugPort, ?int $requestedPort): string;
}
```

---

## 5. 段階的移行ステップ

### Phase 0: 安全網の整備
1. すべての既存テストが通ることを確認: `composer tests`
2. `composer test-json` を実行し、現状の JSON 出力を golden ファイルとして保存。
3. 各メソッドの振る舞いを pin する回帰テストを追加（特に `McpServerTest`, `DebugServerTest` の reflection テスト対象）。
4. PHPStan ベースラインを更新しておく。

### Phase 1: 純粋なヘルパー抽出（I/O なし）
追加ファイル:
- `src/Mcp/ScriptArgumentValidator.php`
- `src/Debugging/VariableDiffBuilder.php`
- `src/Utilities/PathUriConverter.php`

移行対象:
- `McpServer::processScriptArgument()` (719), `validatePhpBinaryScript()` (755), `isPhpInlineCodeScript()` (767), `validateBreakpoints()` (779)
- `DebugServer::buildVariableDiff()` (3260) 〜 `stringifyShallowValue()` (3462)
- `DebugServer::normalisePath()` (1038), `toFileUri()` (1046)

方針:
- 元メソッドを「新クラスへの委譲」に置き換え、**振る舞いを変更しない**。
- `ScriptArgumentValidator` は `ToolExecutor` / `PromptExecutor` から注入して使う。
- `VariableDiffBuilder` は `StepRecorder` から利用。

確認:
- `composer test`
- `composer sa`

### Phase 2: McpServer からツール実行層を分離
追加ファイル:
- `src/Mcp/ToolExecutorInterface.php`
- `src/Mcp/ToolRunnerInterface.php`
- `src/Mcp/ToolExecutor.php`
- `src/Mcp/PromptExecutorInterface.php`
- `src/Mcp/PromptExecutor.php`
- `src/Mcp/ToolRegistry.php`
- `src/Mcp/Tool/XtraceRunner.php`
- `src/Mcp/Tool/XstepRunner.php`
- `src/Mcp/Tool/XprofileRunner.php`
- `src/Mcp/Tool/XcoverageRunner.php`
- `src/Mcp/Tool/XbackRunner.php`
- `src/Mcp/Tool/XcompareRunner.php`（既存 `CompareRunner` をラップする薄いアダプター）

移行対象:
- `McpServer::initializeTools()` (98) → `ToolRegistry`
- `McpServer::handleToolsList()` (454), `handlePromptsList()` (466) → `ToolRegistry` 経由
- `McpServer::handleToolCall()` (852), `executeToolCall()` (875), `extractResultText()` (916) → `ToolExecutor`
- `McpServer::handlePromptsGet()` (651), `mapPositionalArgs()` (690) → `PromptExecutor`
- `McpServer::executeXTrace()` (941) 〜 `executeXCompare()` (1351) → 各 Runner

方針:
- `McpServer` は `ToolExecutorInterface` / `PromptExecutorInterface` をコンストラクタ注入する。デフォルトで具象クラスを生成し、既存 `new McpServer()` の呼び出しを壊さない。
- 各 Runner は `exec()` などをそのまま使うが、テスト時に `ToolRunnerInterface` を差し替え可能にする。
- `PromptExecutor` は `ToolExecutor` に処理を委譲し、プロンプト用のメッセージラッパーを施す。

確認:
- `composer test`
- `composer test-json`
- `bin/xdebug-mcp` のプロセステスト（`McpServerIntegrationTest`）

### Phase 3: DebugServer から DBGp トランスポートを分離
追加ファイル:
- `src/Dbgp/DbgpTransportInterface.php`
- `src/Dbgp/DbgpTransport.php`
- `src/Dbgp/XmlResponseParser.php`

移行対象:
- `DebugServer::listenOnAvailablePort()` (272)
- `DebugServer::startXdebugListener()` (290) のソケット部分
- `DebugServer::sendCommand()` (976), `buildDbgpCommand()` (954)
- `DebugServer::getNextTransactionId()` (1019)
- `DebugServer::readDbgpFrame()` (2515) 〜 `readDbgpChunk()` (2580)
- `DebugServer::parseXmlResponse()` (2457)

方針:
- `DebugServer::__construct()` で `DbgpTransport` を生成・保持。
- 元の `sendCommand()` / `isConnected()` は `DebugServer` に残し、内部で `DbgpTransport` に委譲（公開 I/F 維持）。
- `DebugSession` は `DbgpTransportInterface` を受け取る。

確認:
- 既存の `DebugServerTest`（reflection）が `DbgpTransport` の静的メソッドであればそのまま通るよう、テスト側も新クラスを対象に更新。

### Phase 4: デバッグドメインオブジェクトの抽出
追加ファイル:
- `src/Debugging/BreakpointManager.php`
- `src/Debugging/DebugCommandEngine.php`
- `src/Debugging/VariableInspector.php`
- `src/Debugging/StackParser.php`
- `src/Debugging/VariableDiffBuilder.php`（Phase 1 で作成済み）
- `src/Debugging/StepRecorder.php`
- `src/Debugging/JsonOutputFormatter.php`
- `src/Debugging/TraceInfoResolver.php`

移行対象:
- `BreakpointManager`: `setupConditionalBreakpoints()` (1141) 〜 `resolveBreakpointForLocation()` (1219)
- `DebugCommandEngine`: `continueExecution()` (1239) 〜 `stepOut()` (1282), `didBreak()` (1461), `isExecutionComplete()` (2493)
- `VariableInspector`: `getVariables()` (1303) 〜 `truncateVariableDisplay()` (2810)
- `StackParser`: `getStack()` (1295), `parseStackFrames()` (3697) 〜 `getCurrentLocation()` (3839), `captureCurrentDebugState()` (3650)
- `StepRecorder`: `performStepTrace()` (619) 〜 `processStepRecordingBreakpoints()` (908), `createWatchChange()` (853), `createRecordedBreak()` (871)
- `JsonOutputFormatter`: `encodeJsonOutput()` (2745) 〜 `outputMultipleBreakResults()` (3734)
- `TraceInfoResolver`: `getTraceInfo()` (3513), `outputTraceFile()` (1834), `finalizeTraceOnBreak()` (1470)

方針:
- `DebugServer` は各マネージャーを保持し、公開メソッドから呼び出す。
- 各コンポーネントは `DbgpTransportInterface` などを注入。

### Phase 5: UI 層と公開 API の整理
追加ファイル:
- `src/Debugging/TargetProcessLauncher.php`
- `src/Debugging/DebugSession.php`
- `src/Debugging/CleanupManager.php`
- `src/Debugging/PortDiagnostics.php`
- `src/Debugging/ConsoleDebugger.php`
- `src/Debugging/HttpDebugApi.php`
- `src/Debugging/ClaudeAnalyzer.php`

移行対象:
- `DebugSession`: `__invoke()` (220), `executeTargetScript()` (358), `handleDebugSession()` (539), `performDebugSequence()` (560)
- `TargetProcessLauncher`: `executeTargetScript()` 内のコマンド構築
- `CleanupManager`: `emergencyCleanup()` (2674), `cleanup()` (2707), `gracefulExit()` (2654)
- `PortDiagnostics`: `logExistingSessions()` (2612) 〜 `parsePidList()` (2642)
- `ConsoleDebugger`: コンソール系メソッド
- `HttpDebugApi`: HTTP 系メソッド
- `ClaudeAnalyzer`: `handleClaudeCommand()` (2896) 〜 `buildClaudeAnalysisPrompt()` (2974)

方針:
- `DebugServer` はファサードとして、`DebugSession` を生成して起動するだけにする。
- HTTP API ハンドラーの匿名クラスを独立クラスにし、`DebugServer` の公開メソッドを呼び出す。

### Phase 6: 最終整理
- 元クラスから委譲済みの private メソッドを削除。
- 未使用の use 文を整理。
- `composer dump-autoload`
- `composer tests` フルゲート
- `composer test-json` で出力一致確認
- `composer demo` で bin スクリプト全体の動作確認

---

## 6. テスト戦略

### 6.1 原則
- **環境に依存しないユニットテストを優先**する。
- I/O（ソケット、プロセス、ファイルシステム）はインターフェースで抽象化し、テストダブルを注入する。
- リファクタリング前後で出力が変わらないことを golden/snapshot テストで担保する。

### 6.2 新規・更新テスト

| テストファイル | 対象クラス | 内容 |
|---|---|---|
| `tests/Unit/Mcp/ScriptArgumentValidatorTest.php` | `ScriptArgumentValidator` | 引用符処理、PHP バイナリ判定、ブレークポイント検証 |
| `tests/Unit/Mcp/ToolExecutorTest.php` | `ToolExecutor` | 各 runner へのディスパッチ、Unknown tool エラー |
| `tests/Unit/Mcp/PromptExecutorTest.php` | `PromptExecutor` | 位置引数マッピング、CLI 正規化連携 |
| `tests/Unit/Mcp/Tool/*RunnerTest.php` | 各 Runner | コマンド構築を検証（`exec()` はモック化） |
| `tests/Unit/Dbgp/DbgpTransportTest.php` | `DbgpTransport` | フレーム読み込みを fake socket で検証 |
| `tests/Unit/Debugging/VariableDiffBuilderTest.php` | `VariableDiffBuilder` | diff パターン網羅 |
| `tests/Unit/Debugging/JsonOutputFormatterTest.php` | `JsonOutputFormatter` | `debug_port`/`requested_port`, pretty 出力 |
| `tests/Unit/Debugging/StackParserTest.php` | `StackParser` | XML → フレーム変換 |
| `tests/Unit/Debugging/StepRecorderTest.php` | `StepRecorder` | fake transport + fake variable inspector |
| `tests/Unit/Debugging/BreakpointManagerTest.php` | `BreakpointManager` | コマンド構築（`buildDbgpCommand` 移管後） |
| `tests/Unit/Debugging/ConsoleDebuggerTest.php` | `ConsoleDebugger` | ユーザコマンドパースを入力ストリームの抽象化で検証 |

### 6.3 既存テストの扱い
- `tests/Unit/McpServerTest.php` は JSON-RPC ディスパッチに集中。`executeToolCall` 等の reflection テストは `ToolExecutorTest` に移動。
- `tests/Unit/DebugServerTest.php` は `parsePidList`, `buildVariableDiff`, `outputStepRecordingResults` 等の reflection テストを新クラスのテストに移動。`DebugServer` ファサードの公開メソッド委譲テストを追加。
- `tests/Integration/DebugServerIntegrationTest.php` は環境依存のまま維持し、可能な範囲で fake transport を使った「疑似統合テスト」を追加。
- `tests/Integration/McpServerIntegrationTest.php` はそのまま維持。

### 6.4 動作確認コマンド
```bash
composer test
composer test-json
composer sa
composer cs
composer demo
```

---

## 7. リスクと回避策

| リスク | 影響 | 回避策 |
|---|---|---|
| 公開 I/F 変更で `bin/xstep`, `bin/xback` が動かなくなる | 高 | `DebugServer` のコンストラクタ・公開メソッドはファサードとして維持。内部委譲にするだけ。 |
| `bin/xdebug-mcp` の JSON-RPC 応答形状が変わる | 高 | `ToolExecutor` / `PromptExecutor` は既存の `JsonRpcResponse` 生成ロジックを踏襲。`composer test-json` で一致確認。 |
| テストが reflection 対象メソッド移動で壊れる | 中 | Phase 0 で回帰テストを追加。移行時にテストも同時に新クラス向けに書き換える。 |
| Xdebug 環境なしでは DebugServer 系テストが動かない | 中 | `DbgpTransportInterface` を注入し、XML 応答を replay する fake transport を使う。 |
| 子プロセスやソケットのクリーンアップ漏れ | 高 | `CleanupManager` を一元化。`emergencyCleanup()` は `DebugServer` に残し、シャットダウンハンドラを登録し続ける。 |
| 過度な抽象化で可読性が下がる | 中 | インターフェースはトランスポート・runner 実行・出力フォーマットの 3 系統に絞る。ドメインクラスは必要な分離のみ行う。 |
| PHPStan 型エラー増大 | 中 | 抽出時に配列シェイプの PHPDoc を明確にする。各 Phase 終了時に `composer sa` を必ず実行。 |
| 段階的コミット間でコンフリクト | 低 | 各 Phase ごとに独立したコミット（可能なら PR）を作成。Phase 1〜2 は `McpServer` 側、Phase 3〜5 は `DebugServer` 側と担当を分ける。 |

---

## 8. 変更対象ファイル（予定）

### 新規作成
- `src/Mcp/ToolExecutorInterface.php`
- `src/Mcp/PromptExecutorInterface.php`
- `src/Mcp/ToolRunnerInterface.php`
- `src/Mcp/ToolRegistry.php`
- `src/Mcp/ToolExecutor.php`
- `src/Mcp/PromptExecutor.php`
- `src/Mcp/ScriptArgumentValidator.php`
- `src/Mcp/Tool/XtraceRunner.php`
- `src/Mcp/Tool/XstepRunner.php`
- `src/Mcp/Tool/XprofileRunner.php`
- `src/Mcp/Tool/XcoverageRunner.php`
- `src/Mcp/Tool/XbackRunner.php`
- `src/Mcp/Tool/XcompareRunner.php`（既存 `CompareRunner` をラップする薄いアダプター）
- `src/Dbgp/DbgpTransportInterface.php`
- `src/Dbgp/DbgpTransport.php`
- `src/Dbgp/XmlResponseParser.php`
- `src/Debugging/DebugSession.php`
- `src/Debugging/TargetProcessLauncher.php`
- `src/Debugging/CleanupManager.php`
- `src/Debugging/PortDiagnostics.php`
- `src/Debugging/BreakpointManager.php`
- `src/Debugging/DebugCommandEngine.php`
- `src/Debugging/VariableInspector.php`
- `src/Debugging/StackParser.php`
- `src/Debugging/VariableDiffBuilder.php`
- `src/Debugging/StepRecorder.php`
- `src/Debugging/JsonOutputFormatter.php`
- `src/Debugging/TraceInfoResolver.php`
- `src/Debugging/ConsoleDebugger.php`
- `src/Debugging/HttpDebugApi.php`
- `src/Debugging/ClaudeAnalyzer.php`
- `src/Utilities/PathUriConverter.php`
- 各クラスに対応する `tests/Unit/*Test.php`

### 修正（委譲に留める）
- `src/McpServer.php`
- `src/DebugServer.php`
- `composer.json`（必要に応じて autoload 設定は PSR-4 のまま、新規ディレクトリはそのまま動作）

### 修正なし・維持
- `bin/xdebug-mcp`
- `bin/xstep`
- `bin/xback`
- `src/DTO/*`
- `src/CompareRunner.php`
- `src/CLIParamsNormalizer.php`
- `src/Dbgp/DbgpXml.php`
- `src/XdebugTracer.php`
- `src/ContainerHelper.php`

---

## 9. 成功基準

1. `composer tests` がすべて通る。
2. `composer test-json` の出力がリファクタリング前後で一致する。
3. `bin/xdebug-mcp` を起動した `tools/list` / `tools/call` / `prompts/get` が従来通り動作する。
4. `bin/xstep`, `bin/xback` が既存のスクリプトで動作する。
5. `McpServer` の行数が 400 行未満、`DebugServer` が 500 行未満（ファサード化後）となる。
6. 新規抽出クラスが単独でユニットテスト可能である。
