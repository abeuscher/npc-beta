---
description: Draft a session's base prompt and session prompt, strictly from the templates
argument-hint: [session-number]
disable-model-invocation: true
---

Draft the prompts for session $ARGUMENTS — both the base prompt and the session prompt.

First, find what this session is. Look for session $ARGUMENTS in:
- `sessions/release-plan.md` — canonical for plan-backed sessions
- `sessions/session-outlines.md` — roadmap and active-tracks block
- `sessions/tracks/` — if it belongs to a track
- any arc/track planning doc outside `tracks/` — e.g. `sessions/demo website/demo-site-plan.md` for demo-site sessions

If it isn't pinned in any of those, use what we've settled in this conversation. For
one-off or looser sessions the scope comes from here, not from a doc.

Then read the three templates, in full:
- `sessions/template-base-prompt.md`
- `sessions/template-session-prompt.md`
- `sessions/template-session-log.md`

These are the canonical format. Read them now — do not infer the format from previous
sessions' files. They exist to stop format drift: every session has carried the same
structure since 001, and reconstructing it from memory or from an old log degrades it.

Before writing anything, read back a short confirmation that you have them:
- Name each template's required sections.
- For the base prompt specifically, confirm the two sections the template marks as
  required and easily stripped: the three-paragraph opening qualifier block
  (execution-order / track position; boundary-touching status + CRM contract version;
  session shape + one-line summary), and the "Starting state inherited from session
  (NNN-1)" handoff.
- State the scope you're about to build to.

Then write:
- `sessions/$ARGUMENTS. base-prompt.md` — copy `template-base-prompt.md`, fill every
  required section, set the session number and title, and tailor the reading list to
  this session.
- `sessions/$ARGUMENTS. {Session Title}.md` — draft from `template-session-prompt.md`,
  choosing the plan-backed or stub-driven shape as fits.

Match the existing file conventions exactly — see any current `sessions/NNN. *.md` set
for reference. Do not strip the required sections even where they feel redundant.
