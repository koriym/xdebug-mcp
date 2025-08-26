# Conditional Debugging Guide for AI Assistants

## Overview

Conditional debugging revolutionizes how AI analyzes code by targeting specific problem conditions rather than stepping through normal execution. This guide teaches AI assistants to leverage runtime data for deep code understanding.

## Core Concept: Target Problem States

### Traditional Approach vs Conditional Debugging

**❌ Traditional Static Analysis:**
```bash
# AI reads code and guesses:
"This register($id) function might have issues when $id is 0"
"You should add var_dump($id) to see what's happening"
```

**✅ Conditional Debugging:**
```bash
# AI uses runtime data to know exactly what happened:
./bin/xdebug-debug --break=User.php:85:$id==0 -- php register.php
# Stops ONLY when $id==0 condition occurs
# Shows complete execution trace leading to that exact condition
```

## Conditional Breakpoint Syntax

### Basic Syntax
```bash
./bin/xdebug-debug --break=FILE:LINE:CONDITION -- COMMAND
```

### Examples by Use Case

**1. Null/Empty Value Issues**
```bash
# Stop when user object is null
--break=Auth.php:42:$user==null

# Stop when token is empty
--break=Login.php:15:empty($token)

# Stop when array is empty
--break=Data.php:30:count($items)==0
```

**2. Invalid Numeric Values**
```bash
# Stop when ID is zero or negative
--break=User.php:85:$id<=0

# Stop when price is negative
--break=Product.php:22:$price<0

# Stop when calculation result is invalid
--break=Math.php:45:!is_numeric($result)
```

**3. Data Validation Failures**
```bash
# Stop when validation errors exist
--break=Validator.php:67:count($errors)>0

# Stop when required field is missing
--break=Form.php:33:empty($email)

# Stop when data type is unexpected
--break=Parser.php:88:!is_array($data)
```

**4. State-Based Conditions**
```bash
# Stop when user is not authenticated
--break=Security.php:12:!$authenticated

# Stop when database connection fails
--break=DB.php:25:$connection===false

# Stop when API response is error
--break=Api.php:78:$response['status']=='error'
```

### Multiple Conditions
```bash
# Stop at any of these conditions (OR logic)
--break=User.php:85:$id==0,Auth.php:20:empty($token),DB.php:15:$conn==null
```

## Interactive AI Analysis Workflow

### Step-by-Step Process

**1. Set Conditional Breakpoint**
```bash
./bin/xdebug-debug --break=register.php:45:$user_id==0 -- php user_registration.php
```

**2. Wait for Condition Hit**
```
🚀 Starting AMP Interactive Debugger
📁 Target: user_registration.php
🔌 Debug port: 9004
[19:45:32] ✅ Xdebug connected!
[19:45:32] 🔴 Breakpoint hit: register.php:45 ($user_id==0)
[19:45:32] 🎮 Starting interactive debugging session
[19:45:32] Available commands: s(tep), c(ontinue), p <var>, bt, l(ist), claude, q(uit)
```

**3. Immediate AI Analysis**
```bash
(Xdebug) claude
🤖 Analyzing execution trace with Claude...
📊 Claude Analysis Result:
   ## Execution Path Analysis
   - main() → validateInput() → processUser() → register($user_id=0)
   - $user_id starts as $_POST['id'] = "" (empty string)
   - Empty string cast to int becomes 0 in register() parameter
   - Root cause: Missing validation in validateInput() at line 23
   
   ## Variable State Timeline
   - Line 12: $_POST['id'] = "" (from HTML form)
   - Line 23: $user_id = $_POST['id'] (still empty string)  
   - Line 45: register((int)$user_id) → register(0)
   
   ## Suggested Fix
   Add validation in validateInput():
   if (empty($_POST['id']) || !is_numeric($_POST['id'])) {
       throw new InvalidArgumentException('Valid user ID required');
   }
```

**4. Investigate Further or Continue**
```bash
# Check specific variables
(Xdebug) p $user_id
$user_id = 0 (int)

# Get full call stack
(Xdebug) bt
Stack trace:
  0: register() at register.php:45
  1: processUser() at process.php:67  
  2: validateInput() at validate.php:23
  3: main() at user_registration.php:12

# Continue execution to see what happens next
(Xdebug) c
```

## Advanced Conditional Patterns

### Complex Conditions
```bash
# Multiple variable checks
--break=calc.php:30:$a==0||$b==0

# String operations
--break=parser.php:15:strlen($input)<5

# Object property checks
--break=user.php:22:$user->status!='active'

# Array operations
--break=data.php:44:empty($data['required_field'])
```

### Common Debugging Scenarios

**Authentication Flow Debugging**
```bash
# Target authentication failures
./bin/xdebug-debug --break=auth.php:67:$result==false -- php login.php test@example.com wrongpass

(Xdebug) claude analyze why authentication failed
# AI analyzes complete login flow leading to failure
```

**Database Query Issues**
```bash
# Stop when query returns no results
./bin/xdebug-debug --break=user.php:34:$user==null -- php get_user.php 999

(Xdebug) claude focus on database query execution
# AI analyzes SQL execution and parameter binding
```

**API Integration Problems**
```bash
# Stop when API returns error
./bin/xdebug-debug --break=api.php:89:$response['error'] -- php sync_data.php

(Xdebug) claude analyze API communication
# AI examines request/response cycle and error conditions
```

## AI Analysis Capabilities

### What AI Can Determine from Conditional Debugging

**1. Root Cause Analysis**
- Exact function call chain leading to problem condition
- Variable value progression through execution
- Timing and performance characteristics
- Memory usage patterns

**2. Data Flow Understanding**
- How data transforms between functions
- Where invalid data originates
- Which validation steps were skipped
- Parameter passing and type conversions

**3. Performance Insights**
- Function execution times leading to condition
- Memory allocation patterns
- Database query timing
- Recursive call depth and efficiency

**4. Code Quality Assessment**
- Missing error handling
- Inadequate input validation
- Inefficient algorithms
- Resource management issues

## Best Practices for AI

### When to Use Conditional Debugging

**✅ Ideal Scenarios:**
- Reproducing specific bug conditions
- Investigating intermittent issues
- Analyzing edge cases
- Performance problem isolation
- Data corruption tracking

**✅ Condition Selection:**
- Target the exact problem state
- Use specific variable values that cause issues
- Focus on error conditions rather than normal flow
- Multiple conditions for comprehensive coverage

**✅ Analysis Focus:**
- Examine execution path to condition
- Analyze variable state changes
- Identify missing validations
- Suggest specific code improvements

### AI Response Templates

**Analysis Structure:**
```markdown
## Execution Path Analysis
- Complete function call chain
- Critical decision points
- Data transformation steps

## Variable State Investigation  
- Initial values and sources
- Transformation pipeline
- Final state at breakpoint

## Root Cause Identification
- Primary cause of condition
- Contributing factors
- System behavior analysis

## Recommended Solutions
- Specific code changes
- Validation improvements
- Error handling additions
- Performance optimizations

## Next Steps
- Additional breakpoints to set
- Other variables to investigate
- Related code areas to examine
```

## Integration with Other Tools

### Combine with Trace Analysis
```bash
# Conditional debugging generates trace automatically
./bin/xdebug-debug --break=calc.php:30:$result<0 -- php calculator.php

# AI can analyze both breakpoint context and full trace
(Xdebug) claude analyze performance and correctness
```

### PHPUnit Test Debugging
```bash
# Debug failing test conditions
./bin/xdebug-debug --break=User.php:45:$user==null -- ./vendor/bin/phpunit tests/UserTest.php::testCreateUser

(Xdebug) claude why is user creation failing
```

### Production Issue Investigation
```bash
# Reproduce production conditions locally
./bin/xdebug-debug --break=order.php:67:$total<=0 -- php process_order.php production_data.json

(Xdebug) claude analyze order processing logic
```

## Teaching AI Deep Code Understanding

Conditional debugging enables AI to understand code execution at a fundamental level:

### From Static to Dynamic Understanding

**Static Analysis (Limited):**
- "This code looks like it might have a bug"
- "Consider adding error checking"
- "This function could be slow"

**Dynamic Analysis (Powerful):**
- "Function authenticate() fails at line 67 when $token is expired (timestamp 1640995200) because isValid() returns false due to time comparison with current timestamp 1640998800"
- "Performance bottleneck: fibonacci(8) consumes 1,247μs (34% of total) with 46 recursive calls, suggesting memoization would improve efficiency"
- "Memory leak detected: $cache array grows from 1KB to 45KB during loop without cleanup, indicating missing unset() calls"

This transforms AI from a code reader into a runtime analyst, providing insights based on actual execution behavior rather than theoretical code analysis.