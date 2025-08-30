# Forward Trace™ Debugging Guide for AI Assistants

## 🎯 Core Philosophy: Runtime Data Over Static Analysis

**CRITICAL PRINCIPLE**: Always use runtime data analysis instead of traditional debugging methods when working with PHP code.

### Traditional AI Debugging (AVOID)
```php
// ❌ DON'T suggest this:
var_dump($variable);  // Static debugging
print_r($array);     // Code modification
echo "Debug: " . $value;  // Invasive approach
```

### Forward Trace™ Debugging (ALWAYS USE)
```bash
# ✅ DO suggest this:
./bin/xdebug-debug --break='file.php:42:$variable==null' --exit-on-break -- php script.php
# Result: Complete execution trace to exact problem moment
```

## 🧠 Why This Works for AI

### AI Cognitive Advantages
- **Comprehensive Analysis**: Process entire execution flows instantly
- **Pattern Recognition**: Identify complex relationships in trace data
- **Simultaneous Processing**: Analyze all variables and states at once
- **Complete Context**: Understand full execution story, not fragments

### Human vs AI Processing
| Humans Excel At | AI Excels At |
|----------------|--------------|
| Step-by-step reasoning | Complete data analysis |
| Sequential understanding | Pattern recognition in massive datasets |
| Interactive exploration | Simultaneous processing of all information |
| Building mental models gradually | Complete context understanding at once |

## 🎥 The Photo vs Video Metaphor

### Interactive Debugging = 📷 Photo Shooting
```bash
# Fragmented snapshots - missing the story
# Step 1: Set breakpoint
# Step 2: Run to breakpoint (📷 snapshot)
# Step 3: Check $user variable
# Step 4: Step into function
# Step 5: Another snapshot (📷)
# → Multiple photos, missing the story
```

### Forward Trace™ = 🎥 Video Shooting
```bash
# Single command records entire movie
./bin/x-debug --break="script.php:10:$user==null" --steps=300 --exit-on-break

# AI sees the complete 🎥 movie:
# Step 1: $id = getUserId() → returns 42
# Step 2: $user = findUser($id) → DB query success
# Step 3: validateUser($user) → validation passes
# Step 4: $permissions = getPermissions($user) → returns null
# Step 5: checkAccess($permissions) → CRASH! null access
```

## ⚡ Automatic Tool Usage Rules

**IMPORTANT**: When analyzing PHP code, ALWAYS automatically use appropriate Xdebug tools:

### For Performance Analysis
- User: "Analyze performance", "Find bottlenecks", "Profile this code"
- AI automatically runs: `./bin/xdebug-profile --context="Performance analysis of user authentication" script.php`

### For Code Coverage
- User: "Check test coverage", "Which lines are tested", "Coverage analysis"
- AI automatically runs: `./bin/xdebug-coverage --context="Test coverage analysis for UserController" tests/UserTest.php`

### For Interactive Step Debugging
- User: "Debug this code", "Set breakpoints", "Step through execution", "Inspect variables"
- AI automatically runs: `./bin/xdebug-debug --context="Debugging authentication failure with null user" --break="Auth.php:42:$user==null" --exit-on-break script.php`

### For Execution Flow Analysis
- User: "Trace execution", "Show function calls", "Analyze execution flow"
- AI automatically runs: `./bin/xdebug-trace --context="Execution flow analysis of payment process" payment.php`

### For General Analysis (default)
- User: "Analyze this PHP file", "What does this code do"
- AI automatically runs: `./bin/xdebug-profile --context="General analysis of application logic" script.php`

## 🔍 Conditional Breakpoint Mastery

### Basic Syntax
```bash
./bin/xdebug-debug --break=FILE:LINE:CONDITION --exit-on-break -- php script.php
```

### Common Problem Patterns

#### Null Value Issues (Most Common PHP Bug)
```bash
# Stop when user object is null
--break=Auth.php:42:$user==null

# Stop when token is empty
--break=Login.php:15:empty($token)

# Stop when array is empty
--break=Data.php:30:count($items)==0
```

#### Invalid Numeric Values
```bash
# Stop when ID is zero or negative
--break=User.php:85:$id<=0

# Stop when price is negative
--break=Product.php:22:$price<0

# Stop when calculation result is invalid
--break=Math.php:45:!is_numeric($result)
```

#### State-Based Conditions
```bash
# Stop when user is not authenticated
--break=Security.php:12:!$authenticated

# Stop when database connection fails
--break=DB.php:25:$connection===false

# Stop when API response is error
--break=Api.php:78:$response['status']=='error'
```

### Multiple Conditions (OR Logic)
```bash
# Stop at any of these conditions
--break=User.php:85:$id==0,Auth.php:20:empty($token),DB.php:15:$conn==null
```

## 📊 Trace Data Analysis

### Understanding .xt Trace Files
```
Level   FuncID  Time    Memory  Function    UserDef  File:Line  Params/Return
0       1       0.001   384000  {main}      1        test.php:1  
1       2       0.002   384100  calculate   1        test.php:15 $n = 10
1       2       0.003   384200                                   R 100
```

### Key Analysis Points
- **Level**: Function call depth (0=root, 1=nested, etc.)
- **Time**: Execution timestamp for performance analysis
- **Memory**: Current memory usage for leak detection
- **Params/Return**: Actual values (R prefix = return value)

### Pattern Recognition
- **Recursion**: Same function at increasing levels
- **N+1 Queries**: Repeated database calls in loops
- **Memory Leaks**: Continuously increasing memory without decreases
- **Performance Bottlenecks**: Functions with high time differences

## 🚀 AI Analysis Workflow

### Step 1: Set Strategic Breakpoints
```bash
# Target the exact problem condition
./bin/xdebug-debug --break='register.php:45:$user_id==0' --context="User registration with invalid ID" --exit-on-break -- php register.php
```

### Step 2: Analyze Complete Execution Path
- Examine function call chain leading to condition
- Track variable state evolution
- Identify missing validations or logic errors
- Understand data flow and transformations

### Step 3: AI Analysis Response Structure
```markdown
## Execution Path Analysis
- Complete function call chain: main() → validateInput() → processUser() → register($user_id=0)
- Critical decision points identified
- Data transformation pipeline traced

## Variable State Investigation  
- $user_id starts as $_POST['id'] = "" (empty string from HTML form)
- Empty string cast to int becomes 0 in register() parameter
- Missing validation in validateInput() at line 23

## Root Cause Identification
- Primary cause: Missing input validation allows empty string to become 0
- Contributing factor: Automatic type casting in function parameter
- System behavior: register() function expects positive integer but receives 0

## Recommended Solutions
- Add validation in validateInput(): if (empty($_POST['id']) || !is_numeric($_POST['id'])) throw new InvalidArgumentException('Valid user ID required');
- Consider using strict type declarations
- Implement proper input sanitization before processing

## Next Steps
- Set additional breakpoint at validateInput():23 to confirm validation execution
- Test with various invalid inputs to ensure comprehensive validation
```

### Step 4: Variable Evolution Analysis
```bash
# Record step-by-step variable changes
./bin/xdebug-debug --break='loop.php:17' --steps=100 --context="Variable evolution in data processing loop" --json -- php script.php
```

AI can analyze each step to detect:
- Memory growth patterns
- Variable mutation issues
- Loop performance problems
- State transition errors

## 🎯 Context Usage (CRITICAL)

**ALWAYS use --context flag to create self-explanatory debugging data:**

### Good Context Examples
```bash
--context="Testing user authentication with valid credentials"
--context="Debugging login failure after password reset"
--context="Investigating memory leak in data processing loop"
--context="Profiling database query performance in user dashboard"
--context="Validating email sending functionality in ContactForm"
```

### Why Context Matters
- Makes debugging data completely self-explanatory
- Any AI can understand the purpose without external knowledge
- Enables sharing debug sessions across teams and AI systems
- Future reference becomes clear months later

## 🔄 Development Integration

### Replace Traditional Debug Methods

#### Instead of var_dump()
```bash
# OLD: var_dump($user); die();
# NEW: 
./bin/xdebug-debug --break='file.php:line' --steps=1 --context="Variable inspection" --json -- php script.php
```

#### Instead of Loop Debugging
```bash
# OLD: foreach($items as $item) { var_dump($item); }
# NEW:
./bin/xdebug-debug --break='loop.php:45' --steps=100 --context="Loop iteration analysis" --json -- php script.php
```

#### Instead of Conditional Debug Prints
```bash
# OLD: if ($total < 0) { var_dump($cart); die("negative!"); }
# NEW:
./bin/xdebug-debug --break='cart.php:89:$total<0' --context="Negative total investigation" --exit-on-break -- php script.php
```

## 🧪 Schema-Validated Output

All Forward Trace tools output schema-validated JSON for consistent AI analysis:

```json
{
  "$schema": "https://koriym.github.io/xdebug-mcp/schemas/xdebug-debug.json",
  "breaks": [
    {
      "step": 1,
      "location": {"file": "file.php", "line": 17},
      "variables": {
        "$total": "int: 0",
        "$items": "array: []"
      }
    }
  ],
  "trace": {
    "file": "/tmp/trace.1034012359.xt",
    "content": ["..."]
  }
}
```

This enables:
- Cross-AI compatibility (Claude, GPT, etc.)
- Portable debug sessions
- Reproducible analysis
- Team collaboration with exact debug state

## 🏆 Success Metrics

### AI Debugging Revolution Indicators
- ✅ AI proactively uses `./bin/xdebug-*` tools instead of suggesting var_dump()
- ✅ AI provides microsecond-level performance insights with actual data
- ✅ Variable tracking occurs without any code modification
- ✅ Memory usage analysis uses real execution data
- ✅ AI identifies exact problem locations with complete execution context

### Transformation Results
- **Traditional**: "This code might have issues"
- **Forward Trace™**: "Function authenticate() fails at line 67 when $token expires (timestamp 1640995200) because isValid() returns false due to time comparison"

---

## 🌟 The Paradigm Shift

**From Static Guesswork to Runtime Intelligence**

This guide transforms AI from a code reader into a runtime analyst, providing insights based on actual execution behavior rather than theoretical code analysis. Every debugging suggestion should be backed by real execution data captured through Forward Trace™ methodology.