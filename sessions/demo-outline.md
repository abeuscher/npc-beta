# Demo Strategy Outline

## Core Premise

The product's competitive advantage over Neon, Wild Apricot, and similar nonprofit SaaS platforms is the speed and honesty of the path to value. The demo exists to demonstrate that advantage unattended, without a sales rep, without collecting user data prematurely, and without lying about anything.

The target audience is organizations that have already been burned by a vendor — long onboarding cycles, double-billing during transitions, smoke-and-mirrors sales demos. The demo should feel like the opposite of that experience from the first click.

---

## User Path

**Goal: Value demonstrated in 2-3 clicks, no email required until the user decides they want a real instance.**

1. Landing page — no gate, no form, one click to enter demo
2. Demo instance — pre-loaded with realistic fake data, fully explorable
3. Conversion point — surfaces naturally, not as an interruption

---

## The Import Demo

The centerpiece of the demo is a download-then-upload import flow using a pre-generated CSV in a format the user will recognize from their current system (Neon or Wild Apricot export format).

### Flow

1. Present a CSV file populated with ~10 rows of clearly fake but realistic member data
2. Allow the user to open it externally (Google Sheets link or download) to verify the format matches what they know
3. User downloads the file
4. User uploads it to the demo importer themselves
5. Importer processes it and shows the result

The act of uploading themselves matters. They pushed the button. That's more convincing than watching a recording.

### Stretch Goal: Custom Fields

A second CSV variant — same format, but with a couple of custom fields added (e.g. t-shirt size, volunteer hours). The importer surfaces these and prompts the user to map them to an existing field, create a new custom field, or skip. This is a real decision every migrating nonprofit faces and most tools handle badly or not at all.

---

## Demo Instance Security

The demo environment must not accept arbitrary input. It is not a flexible importer — it knows exactly what it is going to receive because it generated the source.

### Validation Rules (Demo Mode Only)

- **Token signing**: Each generated CSV is signed at generation time with a hash or UUID embedded in the file (metadata field or comment row). The importer validates this token before processing anything.
- **Schema validation**: Exact columns, exact order, exact row count are validated against what was generated. Anything that deviates is rejected entirely — no partial imports, no "we'll try anyway."
- **Altered files are treated as attack vectors, not edge cases.** A modified CSV, even a plausible one, is rejected outright.
- **Real PII protection**: Strict validation also prevents users from accidentally uploading their actual member data into the shared demo environment, which would violate the product's own data handling principles.

Two pre-known code paths (standard CSV, custom fields CSV). The demo does not need to be flexible. It needs to be convincing.

---

## Data & Liability Framing

Nonprofits don't innately care about data storage. They care about liability. The demo should make the product's data posture explicit and visible — not just in marketing copy but *inside the product itself*, ideally on a settings or about page within the demo instance.

**The pitch**: We don't hold your members' payment data — Stripe does. We don't hold your email list — Mailchimp does. We don't hold your books — QuickBooks does. We are the coordination layer, not the vault.

A simple diagram showing the data flow and where each type of data actually lives would be more effective than prose.

---

## Conversion Point

When the user has seen enough, a persistent and unobtrusive panel or page (not a modal) surfaces the next step:

- Number of currently active live demo slots
- Option to sign up for a live demo
- Waitlist if slots are full

**No email is collected before this point.** The user reaches the conversion point, decides they want more, and only then are they asked for contact information.

The waitlist itself is a feature, not an apology. Explicitly: "I'm a single developer. I provision real instances and cap trials so I can actually support each one." That's reassuring to an org that just got burned by a company that oversold and underdelivered.

---

## Anti-Patterns to Avoid

- **No email gate on demo entry.** Not a surveillance marketing site.
- **No ambient onboarding agent** interrupting the user as they explore. Agent assistance (if present) is pull-based — available when asked, silent otherwise.
- **No gap between demo and product.** What they see is what they get, with fake data instead of theirs. The Neon problem was exactly that gap.
- **No modal conversion prompts.** The conversion point is present and findable, not inserted into the user's flow uninvited.

---

## The One-Sentence Pitch

*Neon took 2.5 months and $500/month in double-billing to get your data in. You'll have your data in this system, in production, in under an hour — and you don't pay until you're sure.*

That's not marketing copy. It's just true.
