# Test-Runner Experiment — CRM-Side Implementation Brief

## Purpose of this doc

This is a hand-off brief from a planning conversation to an in-repo Claude Code session in the NonprofitCRM repo. The planning conversation surfaced a workflow experiment: at session close, the writer agent opens a PR and a separate runner agent (running in a different Claude Code window) executes the test suites against the PR and reports back. The goal is to move wall-clock test-execution time off the user's attention while introducing independent verification of writer claims.

This is a **test-basis experiment**, not a full pipeline build. The deliverable is the minimum infrastructure needed to run one manual round of the writer-handoff/runner-verification pattern, evaluate whether it produces signal worth automating, and decide whether to invest in the loop.

The deliverable is **three artifacts** plus **one updated process step**:

1. `docs/testing/known-behaviors.md` — the runner's reference doc for flake classification and false-pass patterns.
2. `sessions/test-handoff-format.md` — the format the writer agent produces at session close describing what should be tested and what coverage is claimed.
3. `sessions/runner-experiment-rationale.md` — the experiment-tracking artifact: what's being tried, what success looks like, abort criteria, lessons after each round.
4. **Update `sessions/template-base-prompt.md`** to add a single step at the end of Phase 1 (or Phase 2, depending on how the close gate currently reads after the template-audit session) that produces the test handoff document and opens the PR before the user takes the close action.

No code changes. No release-plan or session-outlines edits unless something stale must be fixed in the same pass (rationale doc captures the lift).

---

## Framework reference

This experiment's design is informed by the orchestrator/worker/validator framework presented at AI Engineer Europe 2026 by Luke Alvoeiro (Factory). The framework's load-bearing claims for this experiment:

- **Serial worker execution beats parallel by default.** Parallelism is reserved for genuinely conflict-free work (research, validation reviews, documentation reads). The runner role is in the parallel-safe category — validation against a frozen artifact has no conflict surface with the writer's work.
- **Validation is adversarial by design.** The validator has never seen the code and reports against the contract, not against the writer's reasoning. Fresh context is the feature, not a limitation.
- **Validation contracts are pre-implementation.** The orchestrator writes the contract before any code; the worker implements against it; the validator verifies against the same contract. Worker doesn't author its own success criterion.
- **Different models for different roles.** Planning wants slow careful reasoning; implementation wants code fluency; validation wants strict instruction-following, ideally from a different provider to avoid training-data bias. The model-diversity variant is a forward experiment.
- **Structured handoffs capture process compliance, not just output.** "What was implemented, what was left undone, commands run + exit codes, issues discovered, whether procedures were followed."

The runner experiment maps onto this framework as:

- **Orchestrator role = user + the release plan entry + session prompt.** The release plan's success criterion IS the validation contract. The session prompt is a delta against that contract. You (the user) sequence the runner and writer, adjudicate disagreements, and make the merge decision.
- **Worker role = the IDE Claude Code agent** writing implementation code, running its own tests during development, opening the PR at session close.
- **Validator role = the runner Claude Code agent** (sibling brief: `runner-experiment-runner-brief.md`) — separate context, reads the validation contract and the writer's handoff, verifies against the contract, reports back.

This section captures the framework provenance so future-self in session 320+ reading the rationale doc knows where the vocabulary came from.

---

## Project context

- **Session ~277+.** The template-audit session closed; new templates are in play. The drift rule and decision-threshold rule are now in the base prompt. The session-prompt template restructured around plan-entry pointers. The runner experiment is the next process-shape experiment after the template audit.
- **Test suites:** Pest fast suite ~15 minutes, Playwright ~12 minutes. Known flakes documented in session logs (FilePond cumulative-load polling, Choices.js `selectOption` patterns historically, the 252 CI cascade was a `migrate:fresh` test-isolation problem). Standing rule: tests run sequentially, not parallel.
- **Pest and Playwright map cleanly onto Alvoeiro's two validator categories.** Pest is the Scrutiny Validator (deterministic instruction-following — tests, typecheck, lint). Playwright is the User-Testing Validator (acts like QA engineer — launches the app, navigates, verifies flows end-to-end). The runner brief inherits this split.
- **Cycle 2 blind-spot list** (sessions 271–274) documented two real failure modes the writer's own tests didn't catch: `SanitisesRichTextCustomFields` FQCN-vs-short-name (trait's boundary test used FQCN, masking the bug) and `CollectionResource::getRelationManagers` (Filament 2 method name, dead code, compiled, broke no tests). Both are "the writer's mental model leaks into the test it writes" failures.
- **Widget Autonomy track** is in planning. The runner experiment is conceptually adjacent — both are "second agent whose job is to be skeptical of the first's claims" — but the runner is lower-risk (it doesn't write code, just verifies) and produces lessons useful for Widget Autonomy. Per Alvoeiro's serial-vs-parallel framing, the Widget Autonomy track's parallel-worker premise is the harder claim; the runner experiment is in the structurally-safe category and should sequence first.

The runner experiment leverages that the project already has:

- Strong branch discipline (`session-NNN/N`, never push to main, never force-push).
- PR-based review as the standard workflow.
- The release plan as canonical source of scope and success criteria. Release plan entries already function as validation contracts — they're written before implementation, carry explicit success criteria, and define what "done" means before any code is written.
- Track docs and session logs as a documented history of failure modes.

---

## What this experiment is testing

The hypothesis: a runner agent that reads the **validation contract** (release plan entry + session prompt) and the writer's **handoff document** and reports against both produces qualitatively different information from the writer running its own tests. Specifically, it surfaces:

- Tests that pass for the wrong reason (false-pass patterns).
- Coverage gaps relative to what the contract requires (not just what the writer claimed).
- Flakes pre-classified against known-behavior history.
- Cases where the validation contract itself is underspecified (the runner has standing to flag the contract as the problem, separate from flagging the writer's work).

The hypothesis is **not** that the runner saves wall-clock time. That's a secondary benefit; the load-bearing argument is independent verification with a remit to be skeptical of writer claims AND of contract specification.

This first round is manual: the user opens Claude Code in a second window to play the runner role. Building the loop comes only if the manual round demonstrates the role produces signal worth automating.

---

## The three artifacts

### 1. `docs/testing/known-behaviors.md`

The runner's reference doc. Captures the failure modes and flakes the runner needs to know about to produce useful signal.

Shape:

```markdown
# Known Test Behaviors

This doc is the runner agent's reference for classifying test results. Updated when a new flake is identified, a new false-pass pattern surfaces, or a coverage-gap pattern emerges that has happened before.

The runner reads this at session start and uses it to:
- Pre-classify failures as known-flake vs genuine-failure
- Spot false-pass patterns (tests that pass for the wrong reason)
- Identify coverage-gap shapes that have caught real bugs before

## Section 1 — Known flakes

### FilePond cumulative-load polling flake
- **Surface:** Playwright importer specs that exercise the FilePond upload component
- **Signature:** Spec passes in isolation; fails when run as part of a larger Playwright batch
- **First seen:** Cycle 2 (sessions 273–274)
- **Classification:** Retry once. If failure persists across both runs, treat as genuine.
- **Why it happens:** [brief technical reason if known]

### Choices.js `selectOption` pattern
- **Surface:** Playwright importer specs that interact with Choices.js-managed selects
- **Signature:** Standard `selectOption` doesn't trigger Choices.js' DOM swap; test sees stale select state
- **Status:** Fixed at session 256 in `donations-mapping-indicator.spec.ts` and parallel locations. The pattern shouldn't appear in current specs; if a new spec uses raw `selectOption` on a Choices.js select, flag it as a coverage-discipline issue, not a flake.

### [add others as identified]

## Section 2 — False-pass patterns

Tests that have passed for the wrong reason in this codebase. Runner watches for these shapes in writer-authored tests.

### Trait's own boundary test uses FQCN where production uses short-name
- **Origin:** Session 275 SanitisesRichTextCustomFields bug
- **Pattern:** A trait queries `CustomFieldDef::where('model_type', $key)` where $key derivation follows codebase convention (short names like 'contact'); the trait's boundary test happens to construct the model_type with FQCN (`'App\Models\Contact'`); test passes because trait reads its own assumption, but production paths fail silently.
- **Runner check:** When a new trait or service uses model_type lookups, check the test's setup pairs against the codebase's actual model_type generation. If the test sets up data differently from how production writes it, that's a false-pass risk.

### Filament resource method name drift (v2 → v3)
- **Origin:** Cycle 2 audit, `CollectionResource::getRelationManagers()`
- **Pattern:** A Resource subclass declares a method using a base-class signature from an older framework version; method is never called by the current framework; code compiles, tests pass, feature silently doesn't work.
- **Runner check:** For new or modified Filament Resources, check that overridden methods exist on the current `Resources\Resource` base class. The convention-drift Pest test (planned for Cycle 3) will eventually automate this; until then, runner audits manually when a Resource changes.

### [add others as identified]

## Section 3 — Coverage-gap shapes that have caught real bugs

Patterns where a missing test would have caught a bug that shipped. Runner watches for analogous gaps when reviewing the writer's claimed coverage.

### Importer-driven exercise of model-boundary traits
- **Origin:** Session 275 — the trait bug was caught by an importer-driven test that exercised the apply site, not by the trait's own boundary test
- **Pattern:** Boundary tests verify the unit in isolation; production paths exercise the unit through specific entry points. If only boundary tests exist, an entry-point misalignment ships silently.
- **Runner check:** When the writer claims coverage of a new trait/service/utility, look for tests that exercise it through a production entry point, not just direct unit tests.

### [add others as identified]

## Section 4 — Standing test-suite operational notes

- **Pest + Playwright run sequentially.** Parallel runs took down nginx/PHP-FPM at session 273/5. The standing rule lives at `~/.claude/projects/-home-al-nonprofitcrm/memory/feedback_test_runs_not_parallel.md`. Runner never runs both at once.
- **Memory limit at 1G.** `docker/php/local.ini` sets `memory_limit = 1G` (bumped from 256M at session 251). Below 1G, the full Pest suite OOMs in a single test late in the run and breaks `RefreshDatabase`'s transaction wrap, cascading 86 downstream failures with unique-constraint violations.
- **The bind-mount edit gotcha.** If a test run requires editing `docker/nginx/default.conf`, `docker/nginx/prod.conf`, or `docker/php/local.ini`, the running container's bind-mount detaches and `restart` fails with an OCI mount error. Recovery: `docker compose up -d --force-recreate <service>`. See `~/.claude/projects/-home-al-nonprofitcrm/memory/feedback_bind_mount_edit_breaks_container.md`.
- **Fast vs slow classification.** Tests >5 seconds belong in `->group('slow')`. The fast suite excludes slow group by default (`--exclude-group=slow`); runner runs slow group only if the session prompt or test handoff explicitly requests it.

## Section 5 — Things the runner cannot catch

Honest about limitations:

- **Tests that don't exist.** Runner verifies claimed coverage; it can't write the test that would catch a bug the writer didn't think to test for.
- **Visual / UX regressions.** Pest and Playwright catch behavioral regressions but not "this looks subtly wrong." That's the manual-testing pause's job.
- **Performance regressions below threshold.** A test that newly takes 4.5 seconds (under the slow-group threshold) won't be flagged; the fast-suite total drifts upward over time. Future test audits (D4 in the release plan) handle this.
- **Cross-session integration drift.** The runner sees one session's changes; it can't detect that session N+3 broke a contract session N established without an explicit regression test for that contract.
```

The doc starts with what's known today. Sections 2 and 3 will accumulate over time as the experiment runs and more false-pass and coverage-gap patterns get identified.

### 2. `sessions/test-handoff-format.md`

The format the writer agent produces at session close. The runner reads this and reports against it.

The handoff is **supplementary to** the validation contract (release plan entry + session prompt). The contract is what the runner verifies against; the handoff captures what the writer believes is covered, what was deviated from in convention, and what risks the writer foresees. Runner reads both.

Shape:

```markdown
# Test Handoff Format

When a session reaches "implementation complete," the writer agent produces a test handoff document as part of the PR description. The runner reads this document alongside the validation contract (the release plan entry and session prompt that opened the session) and verifies against both.

## The relationship between contract and handoff

- **Validation contract** (release plan entry + session prompt): written by the orchestrator before any code. Defines "done." Canonical for what the work must satisfy.
- **Handoff document** (this format): written by the writer at session close. Reports what was built, what was tested, what conventions were followed, what was deviated from, what the writer believes might fail.

Runner verifies the work against the contract; the handoff helps the runner know where to look.

## Required sections

### What changed

One-paragraph summary of the session's behavioral changes. Not the implementation detail (that's in the PR diff); the user-facing or system-facing change.

Example: "Added `HtmlSanitizer` utility with apply sites at 8 model boundaries. Inbound HTML on Note.body, Event.description, custom-field rich-text values, and 5 other surfaces now passes through allow-list sanitization before storage."

### New tests added

Per new test, one line:
- Location (`tests/Feature/HtmlSanitizerTest.php` etc.)
- What the test exercises (the path under test, not the assertion)
- What failure mode the test guards against

Example:
- `tests/Feature/HtmlSanitizerTest.php` — exercises 71 allow-list cases (round-trip-clean × strip-disallowed × XSS-payload-neutralisation). Guards against allow-list drift.
- `tests/Feature/Importer/RichTextCustomFieldSanitizationTest.php` — exercises the importer path that writes rich-text custom-field values. Guards against the trait being bypassed at the import seam.

### Existing tests expected to remain passing

List tests or test files that the writer believes should still pass and that are relevant to verify. Not "the whole suite" — specific tests that would surface a regression caused by this session's changes if they failed.

Example:
- `tests/Feature/CollectionTest.php` — rich-text round-trip on Collection.body
- `tests/Feature/ContentImporter/SanitizeWidgetConfigTest.php` — existing widget-config sanitization

### Coverage claims

For the writer's belief about what the new tests cover. The runner verifies these claims explicitly against both the tests and the validation contract.

Format:
- Claim: "The new HtmlSanitizer test exercises all 8 model-boundary apply sites."
- Evidence: list the test cases that exercise each site, or note "exercised indirectly via [importer path]" if no direct test exists.

The runner checks: do the cited tests actually exercise the cited paths? Are there apply sites not covered by any test (named or otherwise)? Does the contract's success criterion include verification at all 8 sites?

### Conventions followed and deviations

Captures process compliance per Alvoeiro's "whether procedures were followed." Two sub-sections:

**Conventions followed.** Patterns the writer adhered to without deviation. Often brief — "Standard Filament resource pattern; standard observer pattern; existing rich-text editor convention."

**Deviations from convention.** Where the writer chose to deviate from established patterns and why. Each deviation gets one line: what was deviated, what the convention is, why this case warranted the deviation.

Example:
- **Convention:** New traits typically register via `#[ObservedBy]` attribute (post-Cycle 2 unification).
- **Deviation:** `HtmlSanitizer` registers via `AppServiceProvider::boot()` instead.
- **Reasoning:** Sanitizer is a service, not an observer; the `#[ObservedBy]` pattern doesn't apply. Service-provider registration matches sibling services.

This section gives the runner explicit visibility into the writer's structural choices, which is where convention drift accumulates silently if not surfaced.

### Known risks

What the writer thinks might fail and why, even if its own tests pass. Pre-classifies anything the runner might see.

Example:
- "The Memos collection Trix→Quill migration runs at boot via `AppServiceProvider`. If the migration runs during test bootstrap, it may interact with `RefreshDatabase`. Watch for migration-shaped failures in `tests/Feature/Collection*`."
- "Build artifacts changed (`public/build/widgets/manifest.json`). Verify Playwright specs that read the manifest still pass."

### Slow-group requests

Which slow-group tests should run for this session, if any. Default is "none — fast suite only." If the session adds slow tests or relies on slow-group behavior, name the groups.

Example: "Run slow group 'importer-fixtures' — this session added 4 cases to that group."

### Out-of-scope verification

What the runner should NOT spend time on. Useful when a session's changes are narrow and the runner shouldn't audit unrelated surfaces.

Example: "Out of scope: any Stripe or QuickBooks integration paths. This session does not touch finance."

## Optional sections

### Open coverage questions

When the writer is unsure whether existing tests cover a path. The runner investigates.

Example: "I added a new validation rule at `Request::validate()` in `ContactImportController`. I think `ContactImportControllerTest` exercises it, but I'm not sure the test setup hits the new rule's failure branch. Runner, please verify or flag."

### Contract gaps the writer noticed

If the writer believes the validation contract (release plan entry / session prompt) is underspecified in some way — a success criterion that doesn't pin a behavior, a scope statement that's ambiguous — note it here. The runner has standing to confirm or deny; either way, the gap surfaces for the user's attention.

Example: "The release plan entry's success criterion says 'widget renders correctly across all viewport sizes' but doesn't specify what 'correctly' means at the mobile breakpoint where columns collapse. I implemented based on existing column-layout convention; runner may want to flag whether the contract should pin this explicitly for future sessions."

### Comparison baseline

If the writer expects test count changes (added/removed/renamed tests), state the expected count and the baseline.

Example: "Baseline at session open: fast suite 2277 passed / 0 failed (from session 275 close). This session adds 12 new tests and renames 3. Expected new baseline: 2289."
```

### 3. `sessions/runner-experiment-rationale.md`

The experiment-tracking artifact.

Shape:

```markdown
# Runner Experiment Rationale

## What's being tried

Manual round of writer-handoff/runner-verification pattern. At session close, writer agent opens a PR with a test-handoff document. User opens Claude Code in a second window pointed at the PR, gives it the runner brief (`sessions/runner-brief.md`), runner executes tests and reports back as PR comments. User reads, decides, merges or asks writer to iterate.

## Framework provenance

Design informed by the orchestrator/worker/validator framework presented at AI Engineer Europe 2026 (Luke Alvoeiro, Factory). Three roles in the experiment:

- **Orchestrator** = user + release plan entry + session prompt (the validation contract)
- **Worker** = IDE Claude Code agent (writes code, runs its own tests, opens PR with handoff)
- **Validator** = runner Claude Code agent (separate context, verifies against contract and handoff)

The framework's serial-by-default discipline applies: writer and runner do not run in parallel against shared code. The runner verifies a frozen artifact (the PR at a specific SHA). This is one of the framework's parallel-safe categories.

## Hypothesis

A runner agent reading the validation contract and the writer's handoff and reporting against both produces qualitatively different information from the writer running its own tests. Specifically: false-pass patterns get caught, coverage gaps relative to claims surface explicitly, flakes get pre-classified against known-behavior history, and underspecified contracts get flagged (runner has standing to critique the contract itself, not just the writer's work).

The hypothesis is NOT that the runner saves wall-clock time. Wall-clock is a secondary benefit; independent verification with a skeptical remit is the load-bearing claim.

## Success criteria for the experiment

The manual round demonstrates the runner role produces signal worth automating if:

- The runner identifies at least one coverage gap, false-pass pattern, flake-misclassification, or contract-specification gap that the writer's own run would have missed (or would have surfaced as ambiguous noise).
- The user reviews the runner's report in less time than the user would have spent reading raw test output and forming the same judgment.
- The runner's report contains at least one piece of information the user genuinely uses to decide merge or iterate.

Failure mode: the runner just reports "1234 passed, 0 failed" with no additional judgment. If that's what comes back, the runner role isn't earning its keep yet — the brief needs sharper instructions or the role doesn't apply at this project scale.

## Abort criteria

Echoing the Widget Autonomy track's discipline (this experiment is conceptually adjacent):

- **One calendar week from this session.** If a meaningful manual round hasn't happened, the experiment isn't winning.
- **Two consecutive rounds produce noise rather than signal.** Either the runner brief needs work or the role doesn't apply.
- **The user finds themselves verifying the runner's verifications.** Subjective but real. If the runner adds a step rather than absorbing one, abort.

## Forward experiments (not for round 1)

Captured here so future rounds know what variants to test once baseline signal is established.

### Different-model validator

Round 1 runs Claude-on-Claude. Same model family means shared training-data priors about what "correct code" looks like, what test patterns are common, what to assert against. Alvoeiro's framework names "different provider avoids training-data bias" as a load-bearing claim.

After 2–3 rounds of Claude-on-Claude produce baseline signal, run the same role on a different model family (GPT-5, Gemini) to test whether different-priors-validator catches **qualitatively different** findings than fresh-context-same-model validator.

To collect the data needed for this comparison, every round's findings should be classified by type: false-pass pattern, coverage gap, flake-misclassification, contract-specification gap, convention drift. When the model variant runs, the comparison is across these categories, not just "did it find more or fewer things."

### Validation cadence shift

Round 1 runs at once-per-session cadence. Alvoeiro's slide-7 data shows validator-per-2.3-worker-units in a 16.5-hour autonomous run.

The shift trigger: if the orchestrator role ever gets absorbed into an agent for multi-day autonomous runs (Mission Control shape), once-per-session validation cadence falls behind. The validator would need to run at milestone-frequency, with potentially multiple validation passes per writer session.

Not a current concern — the user is the orchestrator and sessions are bounded. Flag for revisit if/when the project's session structure changes.

## Lessons (accumulate per round)

### Round 1 — [date, session number]

- What worked
- What didn't
- What signal the runner produced that the writer's run wouldn't have caught (specific findings, classified by type)
- What changed in the runner brief or known-behaviors doc

### Round 2 — [date, session number]

- ...

## Decision point

After 2–3 manual rounds, decide:

1. **Build the loop.** The runner role earns its keep; invest in PR-watch automation, dedicated runner worktree, structured comment format.
2. **Try the model variant.** Baseline signal is good; test whether different-model-validator produces qualitatively different findings before committing to loop infrastructure.
3. **Keep manual.** The role works but doesn't earn loop infrastructure; user keeps doing it manually at session close.
4. **Abort.** The role doesn't produce useful signal; absorb the lessons into the writer's own test discipline and move on.

## Forward signal

Next recalibration trigger: when a manual round produces a clearly-decisive lesson (positive or negative), or at the 1-week calendar trigger, whichever comes first. Update this doc with the lesson and the decision-point read.

## Provenance

This experiment came out of a planning conversation at session 276–277, in parallel with the template-audit session. The conversation is also the source of the drift and decision-threshold rules now in the base prompt, and informs the Widget Autonomy track's design.

The conceptual framework (orchestrator/worker/validator, serial-by-default, adversarial validation, structured handoffs) is drawn from Luke Alvoeiro's "The Multi-Agent Architecture That Actually Ships" talk at AI Engineer Europe 2026 (Factory). Vocabulary captured into the briefs after the planning conversation surfaced convergence between the framework and the project's existing instincts.
```

### 4. Update `sessions/template-base-prompt.md`

Add one step at the appropriate point in the close gate (likely the end of the implementation/manual-testing phase, before the user-initiated close — exact placement depends on where the template-audit session landed the close-gate shape).

Proposed insertion text:

```markdown
- **Open the PR with test handoff.** Before announcing the session is ready for close, push the current branch to its same-named remote and open a PR against main. The PR description includes the test handoff document per `sessions/test-handoff-format.md`. This step happens regardless of whether the runner experiment is active for this session — the handoff itself is useful as a self-discipline artifact even when no runner is verifying. If the runner experiment is active, mention that in the PR description so the user knows to expect runner activity.
```

The PR-opening step might already be part of the close gate's Phase 2 (commit and push). If so, the addition is just the "include test handoff document in the PR description per `sessions/test-handoff-format.md`" clause.

---

## Audit discipline for this session

Each artifact gets evaluated against the same three questions used in the template audit:

1. Is this artifact load-bearing for the experiment, or is it ceremony?
2. What failure mode does it prevent or surface?
3. Can it be cut without losing the experiment's value?

The known-behaviors doc and the handoff format are load-bearing — without them the runner is just executing the suite, which is automation not verification. The rationale doc is load-bearing for capturing experiment outcomes so the lessons survive. The base-prompt update is load-bearing for making the writer agent actually produce the handoff at the right moment.

If the audit decides any of these can be simpler, the rationale doc captures what was considered and why the simpler version was chosen.

---

## What this session does NOT do

- **Does not build the loop infrastructure.** No PR-watching automation, no dedicated runner agent setup, no structured comment templates beyond what `test-handoff-format.md` specifies.
- **Does not modify the Widget Autonomy track doc.** That track has its own gate-agent design; cross-references can come later if the runner experiment's lessons apply.
- **Does not change the close gate's user-initiated discipline.** The runner reports; the user decides. No auto-merge, no auto-iterate, no "runner says green so close the session."
- **Does not exhaustively populate `known-behaviors.md`.** First version captures what's known today (the FilePond flake, the FQCN false-pass pattern, the Filament method drift, the standing operational notes). The doc grows as the experiment runs.
- **Does not run the model-diversity variant.** Round 1 is Claude-on-Claude. The variant is captured in the rationale doc as a forward experiment to try after baseline signal is established.

---

## Deliverable summary

When this session closes:

1. **`docs/testing/known-behaviors.md`** created with the five sections shaped above, populated with known content as of session ~277.
2. **`sessions/test-handoff-format.md`** created with the format spec including the Conventions/Deviations section and the optional Contract gaps section.
3. **`sessions/runner-experiment-rationale.md`** created with the experiment shape, hypothesis, success/abort criteria, the forward-experiments section naming the model-diversity variant and the validation-cadence trigger, and an empty lessons section.
4. **`sessions/template-base-prompt.md`** updated to produce the test handoff at PR-open time.

No code changes. No release-plan or session-outlines edits unless something stale must be fixed in the same pass (rationale doc captures the lift).

The next session that closes is the first manual round of the experiment. The runner agent's brief is the sibling artifact to this brief (`runner-experiment-runner-brief.md`).