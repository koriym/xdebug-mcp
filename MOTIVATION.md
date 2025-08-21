# Why I Built xdebug-mcp

## The Moment of Clarity

When Claude Code was announced, I was thrilled. Finally, an AI assistant that could help with real development work! But my excitement quickly turned to disbelief when I watched it debug PHP code:

I saw the AI literally type `var_dump($variable)` into my code, run the PHPUnit tests to see the output, analyze the results, then dutifully remove the var_dump and add another one elsewhere. Over and over.

It struck me: this sophisticated AI, capable of understanding complex architectural patterns, was debugging like it was 1995.

## The Real Problem

But then I realized—this wasn't the AI's fault. What else could it do?

I was giving Claude static code and expecting dynamic insights. It was like asking a detective to solve a crime using only photographs of the crime scene, never letting them examine the actual evidence.

This revealed a fundamental flaw in how we think about AI collaboration. We treat AI as a magical oracle: throw in some code, get perfect answers. But we're starving it of the data it needs to truly help us.

## My Personal Journey

I've spent years building PHP applications, watching talented developers burn out tracking elusive bugs with primitive methods. I've seen how debugging challenges compound as codebases grow. And I've always wondered: why do we accept this as normal?

When AI coding assistants emerged, I saw an opportunity. Not to replace human debugging skills, but to democratize access to professional debugging tools through AI guidance.

## A New Paradigm: Feed the Beast

Here's my core insight: **AI is always hungry for data, and we've been giving it scraps.**

We show AI a map and ask for directions. What it really needs is to take the journey with us—to see every turn, every obstacle, every decision point along the way.

This means sharing what actually happens when code runs:
- The exact sequence of function calls
- Variable states at each step  
- Memory snapshots and performance metrics
- The complete runtime behavior, not just static analysis

Imagine the difference:

**Before (Static Analysis):**
```
AI examines code structure → Makes educated guesses → Suggests print debugging
```

**After (Runtime Intelligence):**
```
AI analyzes execution traces → Identifies exact failure point → Provides precise fix
```

## The Bridge: xdebug-mcp

Xdebug is incredibly powerful but has a steep learning curve. Most developers use maybe 10% of its capabilities. Meanwhile, AI assistants suggest `var_dump()` because they lack access to better tools.

xdebug-mcp bridges this gap through the Model Context Protocol, giving AI direct access to:

1. **Execution traces** - See exactly what happened, when, and why
2. **Variable inspection** - Examine state at any point in execution
3. **Performance profiles** - Identify real bottlenecks, not guessed ones
4. **Code coverage** - Know which paths are actually tested
5. **Live debugging** - Set breakpoints and step through execution

No more modifying source code. No more forgotten debug statements in production. Just clean, professional debugging.

## Beyond Debugging

This isn't just about fixing bugs faster. It's about transforming how humans and AI collaborate on software development.

When AI has access to trace data, something magical happens. While humans get overwhelmed by thousands of function calls, AI can instantly identify patterns in massive trace files. We're finally giving AI the kind of rich, structured data it excels at processing.

The implications extend far beyond PHP:
- Every language could have similar bridges
- AI could become fluent in all debugging tools
- Development practices could standardize at the highest level

## The Vision

I envision a world where:
- Debugging with print statements feels as archaic as using punch cards
- Every developer has access to professional debugging workflows
- AI assistants routinely use execution traces to solve problems
- Debugging becomes systematic and data-driven, not trial-and-error

## The Bigger Picture

We're at an inflection point. We can continue treating AI as a sophisticated search engine, or we can build true partnership through radical transparency—sharing not just our code, but our entire development context.

xdebug-mcp is my contribution to this future. It's proof that when we stop asking AI to guess and start giving it real data, we unlock capabilities we never imagined.

Every time an AI uses `xdebug_start_trace()` instead of suggesting `var_dump()`, we're not just solving a bug—we're elevating the entire profession.

---

*Let's stop showing AI maps. Let's take the journey together.*

— Akihito Koriyama
