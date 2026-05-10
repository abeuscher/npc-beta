# Template: Session Log

Copy this file to `NNN. Session Title — Log.md` when closing a session. Fill in each section. Use this file as the format reference every time — do not base the structure on a previous session's log.

---

# Session NNN — Session Title — Log

**Date:** YYYY-MM-DD
**Branch:** session-NNN/1 … /N

---

## What was built

### Phase 1 — ...

- Item

### Phase 2 — ...

- Item

---

## Security considerations

A short prose statement of what was reviewed on the security axis this session — what the change touched, what was checked, what's intentionally unchanged. Write "None beyond standard convention." for sessions with no security surface. Avoid mechanical box-checking; surface active concerns only.

---

## Schema changes

Describe any migrations or schema.md updates. Write "None." if no schema changes.

---

## Production deployment notes

List any manual steps needed after deploying (seeders, etc.). Write "None beyond standard `migrate`." if nothing special is needed.

---

## Deferred decisions

- Item deferred (reason / next session)

---

## Test result

N passed, 0 failed.
