# Development Principles

These principles guide every decision in this codebase — architecture, code structure, UI, and documentation. When a choice is unclear, return here.

---

## The Standard

**The code should make a developer say "of course."**
Of course it is structured this way. Of course the cache is used here. Of course this belongs in this layer.

**The UI should make a user say "of course."**
Of course this button is here. Of course this menu works this way. Of course the system told me what it was doing.

If either of those reactions requires explanation, the work is not done.

---

## Code

**Simplicity takes more time than complexity. Spend the time.**
The first solution is usually the complicated one. Push past it.

**Readable over clever.**
A developer reading this code for the first time should be able to follow it without a guide. Name things as they are. Structure things as expected. No shortcuts that require context to decode.

**One right place for everything.**
If you have to think about where something lives, the structure is wrong. Models hold data logic. Resources hold presentation logic. Services hold external integrations. Nothing bleeds across those lines.

**Don't build for hypothetical futures.**
Build exactly what is needed now. The right abstraction reveals itself when there are two real examples, not one imagined one.

**If it requires a comment to explain what it does, rewrite it.**
Comments explain *why*, never *what*. The code explains what.

---

## User Interface

**Consistency is more important than cleverness.**
Primary actions are always in the same place. Destructive actions always require confirmation. Errors always appear in the same way. Users build muscle memory — honor it.

**The system always communicates its state.**
If the application is working, the user sees it. If it has failed, the user sees that too — in plain language, not an error code. Silence is never acceptable feedback.

**Busy means busy.**
Any operation that takes time shows a loading state. Any async operation broadcasts its progress. Users never wonder if something is happening.

**Errors are human.**
Error messages say what happened and what to do next. They do not expose stack traces, internal names, or jargon. A non-technical nonprofit administrator is the target reader of every error message.

**Respect the user's attention.**
Every element on screen should earn its place. No decorative complexity. No options that exist because they were easy to add.

---

## Documentation

**Write for the least technical person who will read it.**
The end-user documentation assumes no technical background. If an instruction requires technical knowledge to follow, the instruction is wrong.

**Document decisions, not just implementations.**
The `docs/decisions/` folder exists for a reason. When something non-obvious is chosen, record why. Future maintainers will make better decisions with context.

**Keep it current or delete it.**
Outdated documentation is worse than no documentation. If something changes, update the docs in the same session.

---

## When These Principles Conflict

Simplicity beats brevity. Clarity beats both.
A longer, clearer solution is always preferred over a shorter, obscure one.

If a principle conflict requires a judgment call, document the decision in `docs/decisions/`.
