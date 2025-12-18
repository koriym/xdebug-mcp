# Expected Tool Usage

Review the results from `instruction.md` tasks and verify against the expected tool usage below.

## Task 1: Bug Discovery

**Expected Tool:** `xstep` (with `--break` and `--steps` options)

**Why:** Step debugging with breakpoints is ideal for finding bugs by tracking variable states at specific points.

**Alternative:** `xtrace` is acceptable for getting an overview of execution flow first.

**Check:**
- [ ] Was `xstep` or `xtrace` used?
- [ ] Were breakpoints set appropriately?
- [ ] Was the bug identified?

---

## Task 2: Execution Flow Analysis

**Expected Tool:** `xtrace`

**Why:** Forward trace records the complete execution flow from start to finish, showing all function calls in order.

**Check:**
- [ ] Was `xtrace` used?
- [ ] Was the execution flow clearly visible in the output?
- [ ] Were function call orders identified?

---

## Task 3: Performance Analysis

**Expected Tool:** `xprofile`

**Why:** Profiling analyzes execution time and memory usage to identify bottlenecks.

**Check:**
- [ ] Was `xprofile` used?
- [ ] Were performance metrics (time, memory) reported?
- [ ] Were bottlenecks identified (if any)?

---

## Task 4: Test Coverage

**Expected Tool:** `xcoverage`

**Why:** Coverage analysis shows which lines of code are executed during tests.

**Check:**
- [ ] Was `xcoverage` used?
- [ ] Was coverage data reported?
- [ ] Were uncovered lines identified?

---

## Task 5: Call Stack at Specific Point

**Expected Tool:** `xback`

**Why:** Backtrace captures the call stack at a specific breakpoint, showing how execution reached that point.

**Check:**
- [ ] Was `xback` used?
- [ ] Was the breakpoint set at line 22?
- [ ] Was the call stack captured?

---

## Analysis Summary

For each task where the expected tool was NOT used:

1. **What tool was used instead?**
2. **Why might the AI have chosen differently?**
3. **What could be improved in SKILL.md to guide better tool selection?**

---

## Phase 3: Record Evaluation Results

Create a file at `tests/ai/evaluation-results/YYYY-MM-DD-evaluation.md` with the following format:

```markdown
# Xdebug Tools Evaluation Report

Date: YYYY-MM-DD

## Task Results

| Task | Expected Tool | Actual Tool Used | Success |
|------|---------------|------------------|---------|
| 1. Bug Discovery | xstep | | Yes/No |
| 2. Execution Flow | xtrace | | Yes/No |
| 3. Performance | xprofile | | Yes/No |
| 4. Coverage | xcoverage | | Yes/No |
| 5. Call Stack | xback | | Yes/No |

## Tool Discovery

How did you discover which tools to use?
- [ ] SKILL.md was automatically suggested
- [ ] Used --help on tools
- [ ] Read README.md
- [ ] Trial and error
- [ ] Other: ___

## Issues Encountered

List any problems or confusion:

1.
2.
3.

## Suggestions for SKILL.md Improvement

What would have helped you choose the right tool faster?

1.
2.
3.

## Overall Rating

Tool Discoverability: _/5
Documentation Clarity: _/5
Output Usefulness: _/5

## Personal Impressions

Share your honest experience using these tools:

1. What did you like most?

2. What was frustrating or confusing?

3. Would you use these tools again? Why or why not?

4. Any other thoughts?
```

Save this file and report the path when complete.