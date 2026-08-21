# ADR 0008 — Browser tests travel with the plugin; the assembled application runs them

**Status:** Accepted — 2026-08-21

## Context

The project has already ruled that each plugin carries its own test suite: core keeps the engine tests plus a thin composition smoke suite, and the central build runs core's suites alongside those of every plugin in the distribution. For code-level tests — the ones that load a class, call it, and assert on what comes back — that ruling is implemented. Each extracted package ships its tests inside the package, and core's test configuration registers every one of them from its installed path.

The browser suite never followed. Nothing ever said where a browser test should live once its vertical left the repository, so all of them stayed in core by default. Seven verticals were extracted over roughly a month; the browser suite sat unchanged in the core repository throughout, including tests asserting behaviour core no longer ships. Neither the plugin contract nor the extraction runbook mentions browser tests at all, so each extraction quietly widened the gap rather than closing it.

Browser tests differ from code-level tests in one way that decides this question. A code-level test needs only the code, so a package can run its own. A browser test drives a *running site*: a composed set of containers, a migrated and seeded database, a signed-in administrator session, compiled front-end assets, a real address to open. A package on its own has no site to point a browser at. So the thing that is a single choice for code tests — who owns them — splits into two for browser tests: who **stores** them, and who **runs** them.

Three arrangements were considered.

- **Leave them in core.** Nothing to build, and everything keeps running. Rejected: ownership becomes a fiction. A core maintainer is left holding assertions about code they cannot see, and the drift re-forms at every extraction, because there is still no rule saying otherwise.
- **Give every plugin repository its own browser stack.** True independence — each plugin proves itself before release. Rejected on cost and on coverage: it means maintaining a full container stack in each plugin repository, seven separate ten-minute jobs, and each of them exercising the same core-plus-plugin integration in isolation. The expense is certain; the additional coverage is not.
- **Store in the plugin, run from the assembled application.** Chosen. It is the arrangement the code-level suites already use, so it adds a rule rather than a second philosophy.

The audit that surfaced the drift also showed that ownership is subtler than a file's name suggests. A test filed under the donations importer turned out to assert that a third-party dropdown control initialises lazily and exposes a search box in the importer's column-mapping step; the donations spreadsheet was only the fixture it happened to feed in. That assertion belongs to core's importer interface and would need to keep passing with the donations plugin uninstalled. Ownership therefore has to follow what a test actually constrains, not the entity it mentions.

## Decision

**A plugin owns the browser tests for the behaviour it ships. The assembled application is the only thing that runs them.**

- **Storage follows the code.** A plugin's browser tests live in the plugin repository beside its code-level suite, and travel with it when it is extracted — the same rule the code-level tests already follow.
- **Execution stays central.** Only the core repository composes a running site, so only core's continuous integration runs browser tests. The browser-test runner collects tests from the installed plugin packages as well as from core's own test directory, and reports which sets it collected — a run that silently skipped a plugin's tests must not read as a full pass.
- **Ownership is decided by what would break the assertion.** If a change inside the plugin would break the test, the test is the plugin's. If the test would still have to pass with that plugin uninstalled — because what it really constrains is the page engine, the site chrome, the theme, permissions, or a shared administrative control — it is core's, whatever entity it borrows as a fixture. A test that could be broken from *either* side is an integration test and stays in core, where the composition lives.
- **Shared test helpers are a published surface, not private scaffolding.** Signing in, resetting the database, driving the multi-step import wizard, the sample data files: these stay core-owned, and a plugin's browser tests import them. That makes them part of what core publishes to plugins, and changing one changes every plugin that uses it. They are added to the plugin contract on the same footing as every other published surface.
- **The call is made during extraction, not afterwards.** The extraction procedure gains a step that sorts the vertical's browser tests by the ownership rule above, mirroring the standing principle that extraction doubles as the audit moment — reduce and de-duplicate while pulling apart.

The collection mechanism this requires is not built yet; this record fixes the decision, and the contract and the runbook carry the current state of the implementation.

## Consequences

- **One rule now covers both suites.** A contributor asking "where does this test go?" gets the same answer whether the test drives a browser or calls a class, and the answer no longer depends on which extraction happened to be in flight.
- **The drift stops re-forming.** Because the sorting happens inside the extraction procedure, the next vertical to leave cannot repeat this one's mistake by omission.
- **Core's browser suite shrinks to what core owns.** The remaining tests describe the engine, the chrome and the composition — which is what a browser test is uniquely good for.

Costs:

- **Shared helpers become a versioned surface, and this is the largest cost.** Today the sign-in helper is a private file that can be edited freely. Once plugins import it, editing it can break every plugin's browser tests at once, and the breakage only appears when core's build runs the installed packages' tests — after the change has landed, not during review.
- **A failing plugin browser test cannot be fixed in the change that broke it.** The fix is a plugin release: tag, push, bump the pin, commit in core. The code-level suites already pay this, but browser runs are ten minutes and deliberately non-blocking, so the loop is slower and the failure is easier to walk past.
- **A plugin author cannot run their own browser tests before releasing.** The plugin repository can store them and cannot execute them. That makes the standing rule — never commit a browser test you have not watched pass — impossible to honour from inside a plugin repository, and that rule exists precisely because tests committed without ever being run is the failure this project has had to correct more than once. Until a plugin repository can borrow an assembled site, a plugin author writes browser tests blind and the core build is the first thing that ever runs them.
- **A green run means less than it used to.** The set of tests that ran now depends on which plugins the distribution installed. A distribution that omits a plugin omits its tests without failing, so the run has to say what it collected for the result to be worth reading.
- **Reversal is cheap but the interim is not.** Pulling browser tests back into core is a file move; what does not come back easily is the helper surface, once seven repositories depend on it.

## References

- `sessions/plugin-architecture-plan.md` — the question-disposition list carries the owner ruling that each plugin holds its own suite, that core keeps the engine tests plus a composition smoke suite, and that extraction is the moment to reduce and de-duplicate. This record applies that ruling to the browser suite, which it had not reached.
- `docs/plugin-contract.md` — the authority on what core currently publishes to plugins, and where the shared browser-test helpers are described once they become a published surface.
- `docs/plugin-extraction-runbook.md` — the extraction procedure, which gains the step that sorts a vertical's browser tests by the ownership rule.
- `docs/testing/playwright-discipline.md` — the standing rule for what belongs in a browser test at all, and the local-first authoring rule this decision puts out of reach for plugin authors.
- ADR 0004 — one repository per plugin, which established that a plugin's tests travel with it and that core runs them from the installed package. This record answers the case that one left open: tests that cannot run inside the package that owns them.
