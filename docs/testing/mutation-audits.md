# Mutation Audits

External signal for which tests carry weight, distinct from "Pest is green." Run periodically against a bounded slice — never the whole suite at once.

---

## Why mutation testing

Pest passing proves the assertions you wrote pass against the code you wrote. Mutation testing probes whether the assertions actually constrain the code: Infection mutates production code (flips operators, alters return values, inverts conditionals) and reports which mutants the suite catches. A test that catches **zero** mutants is not testing anything. Together the two signals answer "are the tests doing useful work."

---

## Slice-first discipline

Do not run Infection across the whole suite — it is slow and the report is unreadable. Pick a cohesive slice (one module, one service, one well-bounded layer) and audit it. Good slice properties:

- **Cohesive** — the source files belong to one architectural concept; the tests for them are easy to enumerate.
- **Deterministic** — pure-PHP logic, no DB writes, no HTTP, no async. Infection runs each mutant in its own process; non-determinism dilutes the signal.
- **Recently consolidated** — test surface is curated, not session-old drift. Recently-touched code where the test shape was just decided is a strong candidate.
- **Not importer-heavy** — importer modules have edge-case-heavy branches that dominate the report. Save them for later passes once the workflow is well-understood.

The first slice (session 241) was `app/WidgetPrimitive/{ContractResolver,DataContract,Source}.php` plus the eleven `tests/Feature/*ContractRetrofitTest.php` files. Future slices listed at the bottom of this doc.

---

## Toolchain

Infection lives in `require-dev` and PCOV is installed in the Dockerfile but **not** auto-loaded — the default Pest profile runs at the speed it always did. Loading PCOV is opt-in per Infection invocation.

### Pest 2 adapter shim

Pest 2 + Infection 0.32 don't fit cleanly together. The shim at `bin/pest-for-infection.php` papers over two specific bugs. Infection's PHPUnit adapter spawns the configured `phpUnit.customPath` as `php <path>`, which is why the wrapper is PHP rather than bash. `infection.json5` already points `phpUnit.customPath` at it. Nothing to do on a fresh checkout — the file ships with the repo.

What it fixes:

1. **Per-mutant config `<file>` path rewrite.** Infection generates a PHPUnit config under `/tmp/infection/` for each mutant with relative test paths (`tests/Feature/X.php`). PHPUnit/Pest resolve those relative to the config file's location, producing `/tmp/infection/tests/Feature/X.php` — which doesn't exist — and Pest exits non-zero with "Test file not found". Infection then records the mutant as **killed** (it interprets the non-zero exit as a successful kill), producing a 100% MSI report where no test ever actually ran. The shim rewrites every `<file>` path in any `/tmp/infection/` config to absolute project paths before Pest sees it. Without this, every Phase 2 number is a false positive.

2. **Junit log namespace rewrite.** PCOV's coverage-xml emits Pest's mangled namespace (`P\Tests\Feature\…`). PHPUnit's junit log emits the user-facing namespace (`Tests\Feature\…`). Infection cross-references the two by class name and the prefix mismatch makes the lookup fail with `TestNotFound`. The shim rewrites the junit log so both sides agree on the `P\` form.

Optional knob: setting the env var `PEST_FOR_INFECTION_NO_STOP_ON_FAILURE=1` rewrites the per-mutant configs' `stopOnFailure="true"` to `"false"`. Default Infection runs with stopOnFailure=true, so Pest stops at the first failing test per mutant — meaning the report only sees the *first* test that caught each mutant. Setting the env var produces a complete catcher set per mutant (every test that would have failed runs to completion). 4–5× slower per mutant; only worth it when the question is "is test X uniquely catching anything, or are siblings catching the same things?" — i.e., the redundancy/dead-test analysis.

If a future Pest major version (Pest 3+) lands or an upstream Pest-Infection adapter is released that handles these compatibility issues natively, retire the shim.

### Fresh checkout

```bash
docker compose exec app composer install
```

If `composer install` reports the `infection/extension-installer` plugin is blocked, the allowlist entry is missing from `composer.json` (`config.allow-plugins.infection/extension-installer: true`) — fix and retry. (Already in place as of session 241.)

### PCOV opt-in flags

Two surfaces need the flags: the outer Infection process and the inner test sub-process Infection forks. Outer flags go on the `php` command; inner flags go via `--initial-tests-php-options` so they reach the Pest run that gathers coverage.

```bash
docker compose exec app php \
    -d memory_limit=4G \
    -d extension=pcov \
    -d pcov.enabled=1 \
    vendor/bin/infection \
    --threads=4 \
    --min-msi=0 \
    --min-covered-msi=0 \
    --no-progress \
    --initial-tests-php-options="-d memory_limit=4G -d extension=pcov -d pcov.enabled=1"
```

`--threads=4` parallelizes mutant runs. `--min-msi=0` and `--min-covered-msi=0` disable the failure-on-low-score gates — exploratory runs, not CI gates. The 4G memory limit is needed for the PCOV-instrumented initial test run; plain Pest is fine on 2G.

### Smoke test (verify install on a fresh checkout)

Cheap end-to-end probe. Pick a small file in the configured `source.directories` and run with `--filter`:

```bash
docker compose exec app php \
    -d memory_limit=4G \
    -d extension=pcov -d pcov.enabled=1 \
    vendor/bin/infection \
    --filter=ContractResolver.php \
    --threads=1 \
    --no-progress \
    --min-msi=0 --min-covered-msi=0 \
    --initial-tests-php-options="-d memory_limit=4G -d extension=pcov -d pcov.enabled=1"
```

Confirms (a) PCOV is loaded for the test sub-process, (b) the Pest+Infection adapter shim resolves the namespace mismatch, (c) at least one mutant is generated.

---

## Configuration

`infection.json5` at repo root names the slice via `source.directories`. To audit a different slice, edit that array — or pass `--filter` at runtime to scope a single run without changing the committed config.

`logs.json` — the machine-parseable artifact for Phase 2 analysis. `logs.html` — human spot-checks. `logs.summary` — terminal-friendly tally. All four log paths live under `.gitignore` — every run produces fresh artifacts; the **config** is the durable thing, not the report.

---

## Reading the report

Two artifacts matter:

- **`infection.json`** — every mutation, its location, its mutator name, its status (`killed` / `escaped` / `timed_out` / `not_covered`), and (when killed) the test that killed it. Parse this for the findings table.
- **`infection.html`** — same data, browsable. Useful for spot-checking specific mutants when deciding whether a surviving mutant is a real bug or a not-worth-testing branch.

The headline metric is the **MSI (Mutation Score Indicator)** — `killed / (killed + escaped + timed_out)`, expressed as a percentage. A high MSI means the suite is constraining the code. A low MSI means many mutations slip through — either because branches are not exercised or because tests assert weakly.

---

## The decision rule

Group every test in the slice into one of four buckets:

1. **Dead** — catches **zero** mutants. Deletion candidate. The test is not constraining anything; Pest's pass result is meaningless for it.
2. **Redundant** — every mutant it catches is also caught by ≥2 other tests. Consolidation candidate. The threshold (N≥2) is loose enough to keep belt-and-suspenders coverage on truly load-bearing logic; tighten or loosen during review if the signal is too noisy.
3. **Load-bearing** — catches at least one mutant **no other test catches**. Keep, no question.
4. **Surviving mutants** — code paths nothing in the slice catches. Note them as a forward queue. **Do not write new tests for them in the audit session itself** — that is a different discipline (the Test Suite Audit roadmap stub) and inflates audit scope.

For each row in **Dead** or **Redundant**, decide: delete, consolidate (delete + roll an assertion into a sibling), or override-keep.

---

## The override-keep convention

When a test is flagged dead or redundant by Infection but you decide to keep it — typically because it guards a known historical regression that mutation evidence cannot see — add a one-line searchable comment above the test body:

```php
// guards: importer mid-row null-check regression from session 187
it('skips rows where the contact_id resolution returns null', function () {
    // ...
});
```

The `// guards:` token is the marker. Find existing overrides:

```bash
grep -rn "// guards:" tests/
```

Future mutation passes can recognize these as already-justified holdouts and skip them in the redundant-bucket review.

---

## Cadence

Loose rule, not a hard schedule:

- **One slice per quarter** as a baseline rhythm — keeps the workflow muscle warm without burning sessions on it.
- **After any session that adds substantial test code** (>50 cases, or a new track-level surface) — the new tests are the most likely place dead/redundant tests have just been introduced.

Operationally, audits are surfaced as `/schedule` candidate offers at session close, not pre-committed onto the roadmap. The default expectation is "no scheduled mutation pass right now" — an audit gets queued only when there's an obvious target.

---

## Slice ideas for future passes

Queue, ordered by likely signal density. Pick whichever has the freshest test surface or the strongest "is this carrying weight?" question attached.

- **`AppearanceStyleComposer`** — pure-PHP composition logic, deterministic, well-bounded.
- **Importer `FieldMapper`** — heavy branching, lots of edge cases, importer test surface has accumulated organically. Will produce noise; valuable when the importer test shape is up for review anyway.
- **Page builder save flow** (`PageBuilderApiController` + the validators it calls) — recently consolidated; high-stakes.
- **Filament resource action visibility** — gate-heavy code where mutations on the gate predicate would directly translate to security regressions.
- **Fleet Manager controller** (`app/Http/Controllers/Api/Fleet/HealthController`) — substantial test surface accumulated across sessions 238 and 240; mutation evidence would tell us whether the contract assertions actually constrain the response shape.

---

## What this doc is not

- **Not a CI gate.** `--min-msi` is intentionally `0` in the documented invocation. CI gating is post-v1 of this workflow and only meaningful once multiple slices have run and a baseline MSI is well-understood.
- **Not a whole-suite tool.** Slice-first is the discipline. Running Infection across `app/` would take hours and emit a report no one reads.
- **Not a replacement for human judgment on test shape.** Mutation evidence is a strong signal, not a verdict. The override-keep convention exists precisely so a human can keep a test the report flags as redundant — and leave a note explaining why.
- **Not net-new coverage.** Surviving mutants surface as forward queue, not as new-tests homework inside the audit session itself.
