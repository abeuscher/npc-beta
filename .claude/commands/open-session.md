---
description: Bootstrap a session by loading and executing its base prompt
argument-hint: [session-number]
disable-model-invocation: true
---

Open session $ARGUMENTS.

Read `sessions/$ARGUMENTS. base-prompt.md` — match the file even if the casing
differs slightly (e.g. "Base Prompt"). That base prompt is the authority for this
session: it carries the reading list, the template re-read, the Starting-state
handoff, and the Open Gate.

Execute it as written. Work the reading list in order, then stop at the Session
Open Gate and wait for confirmation. Do not pipeline past the gate into task work.

The base prompt already handles orientation and read-back at its Open Gate — don't
duplicate that here.

If no base prompt exists for session $ARGUMENTS, say so and stop. Do not improvise one.
