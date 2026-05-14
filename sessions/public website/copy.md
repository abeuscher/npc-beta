# Marketing Copy — All Pages

The voice across all pages: plain-spoken, signed-by-a-person, terse, deliberately anti-SaaS. Short sentences. No marketing adjectives. Do not paraphrase or "improve" this copy.

---

## Home (`/`, slug: `home`)

Status: filled in already. Copy below is for verification against the existing export. If the existing export differs, the existing export wins unless flagged as a gap.

### Hero band

**Headline:** A combination CMS, CRM, and a few other tools to help nonprofits manage their business.

**Sub:** Built and supported by one developer. Open source. Flat pricing.

**CTAs:** [Try the Demo] (primary, → `/demo`) · [See the Code] (secondary, → `https://github.com/abeuscher/npc-beta/`)

### Small Data band

**Heading:** Small Data

**Body:** Three principles:

1. Privacy is important.
2. People are important.
3. People and privacy are both more important than money.

This product reflects those values. So does the business behind it.

### What it does band

**Heading:** What it does

Four parallel columns (suggested layout: 4-column grid, or two 2-column rows if 4-up reads cramped):

**Content & Web**
- CMS with structured content and a page builder
- Custom widgets, headers, and footers
- Member portal
- Daily backups

**Constituents**
- CRM
- User groups
- Mailing list manager (integrates with Mailchimp)

**Commerce**
- Online store: products, ticket sales, donations
- Stripe integration for payments
- QuickBooks integration for bookkeeping
- Event handling
- Donation tracking

**For developers**
- Open source. Public repo. Full docs.
- Tailwind native. Blade, JS, and SCSS widget system.
- Data sovereignty.

**CTA below grid:** [Try the demo →] (primary, → `/demo`)

### This is not a SaaS company band (dark)

**Heading:** This is not a SaaS company.

**Body:** My name is Al. I am a developer and I built a piece of software. I have been using CMS's and CRM's for 30 years, and building websites and web applications that whole time.

I tried to do it right. And I tried to do it with every client in mind, not just one.

What I didn't do:

- I don't super-serve a single customer.
- I don't take feature requests from sales and marketing.
- I don't report to a C Suite.

**CTA:** [More about how this works →] (text-only or secondary, → `/about`)

### Pricing band

**Heading:** Pricing

**Body:** $150/month, flat.

Hosting, daily backups, in-system help, ongoing updates. Data import, design, and custom development available à la carte.

**CTA:** [Full pricing →] (text-only or secondary, → `/pricing`)

### What it doesn't do band

**Heading:** What it doesn't do

**Body:** In the interest of saving you time:

- The CMS is field-based, not WYSIWYG. It's structured content, not a drag-and-drop editor.
- No built-in analytics of any kind. You can install any package you like on your site — analytics just isn't what we do here.
- No tracking cookies. This product doesn't use cookies for any non-functional purpose, and neither does this website.
- No in-app refunds. Refunds are recorded but issued through Stripe.

### Final CTA band

**Heading:** Try it.

**Body:** First month is $50 until I get some customers.

**CTAs:** [Start the demo] (primary, → `/demo`) · [Email me] (secondary, → `mailto:` — agent should leave the mailto address as a placeholder `mailto:al@example.com` and flag for user to provide real address)

---

## About (`/about`, slug: `about`)

Status: filled in already in the system. Agent should export from admin into this folder as `existing-about-page-export.json` and treat that as ground truth. No new copy is provided here; the existing About page is canonical.

If the existing About does not include links to the two in-product demo LPs (`/my-nonprofit` and `/my-nonprofit-workshop` or whatever slugs they live at inside the demo nonprofit's CMS), surface that as a gap. The intent is for About to point to those LPs as feature demonstrations.

---

## Pricing (`/pricing`, slug: `pricing`)

Status: partial in system. Agent should export from admin into this folder as `existing-pricing-page-export.json` and complete it against the structure below.

### Hero band

**Heading:** Pricing

**Body:** One plan. Flat. Includes what you need.

### What's included band

**Heading:** What $150/month gets you

Two-column or three-column grid of plain-language line items:

- Hosting on a private instance
- Daily backups
- In-system help and documentation
- Ongoing software updates
- All features. No tiers. No upsells.
- Source code access (it's open source)
- Email support from one developer

### Founder pricing band (tinted)

**Heading:** Founder pricing

**Body:** First month is $50 until I get some customers. That's not a trial. It's a discount on month one, because the product is new and you'd be doing me a favor.

**CTA:** [Start the demo] (primary, → `/demo`)

### À la carte band

**Heading:** À la carte

**Body:** Some things are not part of the monthly fee because not everyone needs them, and bundling them would mean charging you for something you don't use.

Plain text or 2-column grid:

- **Data import** — bringing your existing constituent data into the system, cleaned and matched. Hourly.
- **Design** — custom theme work, branded templates, page design. Hourly.
- **Custom development** — new widgets, integrations, automations. Hourly.

If you need any of these, write me and we'll talk about scope.

**CTA:** [Email me] (secondary, → `mailto:` placeholder)

### What's not included band

**Heading:** What's not included

**Body:** A short list, because being clear about this up front saves both of us time:

- Multi-tenant hosting. You get your own instance, not a shared one.
- Annual contracts. Pay monthly. Cancel whenever.
- An account manager. You get a developer who answers email.

### Final CTA band

**Heading:** Try it before you commit.

**Body:** The demo is the full product. Spend an hour with it.

**CTA:** [Start the demo] (primary, → `/demo`)

---

## Contact (`/contact`, slug: `contact`)

Status: new build. Keep deliberately simple — the demo page is the conversion path; this is for everything else.

### Hero band

**Heading:** Get in touch.

**Body:** Email is the best way to reach me. I'm one person and I answer everything myself, usually within a day.

For demo access, use the demo form — it gets you into a working copy of the product faster.

### Contact band

**Heading:** Email

**Body:** al@example.com *(placeholder — agent should flag for user to provide real address)*

**CTA:** [Email me] (primary, → `mailto:` placeholder)

### What I respond to fastest band (tinted, optional)

**Heading:** What I respond to fastest

- Purchase inquiries
- Demo follow-ups
- Bug reports
- Questions from people considering switching from another platform

Sales pitches, partnership emails, and SEO outreach get archived. Not personal.

---

## Demo (`/demo`, slug: `demo`)

Status: new build. The conversion point. The form should be minimal — name and email both optional.

### Hero band

**Heading:** Try the product.

**Body:** You get a working copy of NonprofitCRM. The full thing — no stripped-down trial, no scripted scenario, no sales-engineered demo. Spend as long as you want. It resets every 24 hours.

### Form band

**Heading:** Get demo access

The form widget should contain (all fields optional):

- **Name** (text, optional)
- **Email** (email, optional, with helper text underneath: "Only needed if you want me to follow up. Leave blank to look around anonymously.")
- **What are you interested in?** (radio or select, optional): Demo / Purchase inquiry / Just curious
- **Anything else you want to share?** (textarea, optional)

**Submit button:** [Get demo access] (primary)

### What happens next band

**Heading:** What happens next

**Body:** You get a login link, immediately. The instance is yours for 24 hours. If you provided an email, I'll send you a follow-up a day or two later — once, not a sequence. If you didn't, you won't hear from me, and nothing about your session is stored after the 24 hours are up.

### Privacy band (dark)

**Heading:** What I do with your information

**Body:** If you give me an email, I store it for follow-up. You can delete it from inside the demo at any time — there's a button for that. I do not run data enrichment. I do not look up your company. I do not put you on a mailing list. I do not retarget you with ads. There are no analytics on this site or in the demo.

The source code is on GitHub. You can verify all of the above by reading it.

**CTA:** [See the Code] (secondary, → `https://github.com/abeuscher/npc-beta/`)

---

## Notes for the agent

The `mailto:` addresses in this document are placeholders. The agent should leave them as `mailto:al@example.com` and surface a single gap item asking the user to provide the real address.

The exact slug of the in-product demo LPs (`/my-nonprofit` and `/my-nonprofit-workshop`) is not specified here. The agent should check the system for their actual URLs when finalizing the About page links, and flag if they don't yet exist.

Contact page section labeled "What I respond to fastest" is marked optional — the agent should include it if the Contact page reads too thin without it, omit it if including it makes the tone feel listicle-y. Designer's call. If omitted, log a one-line note in `build-summary.md`.
