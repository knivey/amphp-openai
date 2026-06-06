# AGENTS.md

## Project overview

**NEVER** use `git add -f` to force-add files that are in `.gitignore`. The `.gitignore` exists for a reason (secret keys, environment configs, build artifacts). If `git add` refuses to track a file, respect that — do not override it.
**NEVER skip code reviews.** Code reviews via the `requesting-code-review` skill are MANDATORY after completing any task — no exceptions. "It's simple", "it's too small", "I already tested it", or "the tests pass" are NOT valid reasons to skip review. Every completed task MUST be reviewed before moving on or declaring work done. If you are tempted to skip a review, stop and do it anyway.
**NEVER move or recreate git tags.** Packagist rejects tag updates. If a tag needs fixing, create a **new** version tag (e.g. `v1.1.1` instead of moving `v1.1.0`). Deleted and recreated tags will cause Packagist to ignore the package until manually resubmitted.

## Review Process
- When implementing multi-task plans, perform **both spec compliance review AND code quality review** after every task, no matter how trivial. Never skip either review.
- Spec review verifies the code matches the task requirements (nothing missing, nothing extra).
- Quality review verifies the code is well-built (correct patterns, no bugs, no race conditions, clean style).
- Fix all issues found in reviews before moving to the next task.


