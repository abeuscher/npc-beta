# A Better Nonprofit Platform
### A Whitepaper and Product Proposal
*March 2026 — Early Stage. Nothing built yet.*

> This document and the software it describes are being developed with agentic AI assistance. This is declared here and will be declared in the project README.

---

## The Problem

Small nonprofits are being failed by the software they depend on.

Wild Apricot and Neon One — the dominant platforms in this space — are SaaS products assembled by sales-driven organizations from white-labeled components and underfunded development teams. The software reflects that. Integration between their own modules is poor. Extensibility is nonexistent. When something goes wrong, the platform communicates nothing useful. Pricing sits at $200–300/month for a product that routinely fails at basic tasks.

CiviCRM, the open source alternative, is powerful but resource-intensive. Running it well requires a capable server and significant technical expertise. For most small nonprofits it is not a realistic option.

The organizations suffering most from this gap are exactly the ones with the least capacity to absorb it — small nonprofits with 10 to 50 staff, a few thousand members, real grant accounting requirements, and board members who have used Salesforce at their day jobs and reasonably expect software to work.

This is a solvable problem.

---

## The Proposal

An open source nonprofit CRM and CMS platform, purpose-built for small organizations, offered as a managed hosting service at a price that is directly competitive with — and meaningfully better than — the existing options.

The software is MIT licensed. Anyone can run it. The business is managed hosting and setup. These are not the same thing, and the distinction matters.

---

## What the Platform Does

The platform handles the core operational needs of a small nonprofit in one place, with one login, under one coherent interface:

**Contacts and Members** — a unified contact record that supports individuals, households, organizations, and the relationships between them. Members get a gated portal. Admins get a full CRM.

**Donations and Grants** — donation tracking with grant allocation that supports splitting a single transaction across multiple funding sources. Built for auditors from day one. QuickBooks sync is outbound and automatic.

**Memberships** — tiers, renewals, full history. Recurring payments handled through Stripe.

**Events** — registration, ticketing, waitlists, waivers.

**Commerce** — products and orders for things like plot rentals, merchandise, or program fees. All payments through Stripe.

**Content Management** — pages, posts, media, and navigation managed through a clean editor interface. Not WordPress. No WordPress security problems.

**Email Marketing** — contact segmentation happens in the platform. Lists are pushed to Mailchimp. Campaigns stay in Mailchimp.

**Custom Fields** — admins define additional fields at setup. All fields are filterable. All fields can be used to build Mailchimp segments.

---

## What Makes It Different

**It tells you what it is doing.** Every async operation — a database query, a Mailchimp sync, a QuickBooks push — broadcasts its status in real time to a persistent panel visible to every admin. When something takes 45 seconds, the user knows it is taking 45 seconds and why. When something fails, the message is in plain language, not a stack trace.

**It is honest about its scope.** Payments go through Stripe. Email goes through Mailchimp. Accounting goes to QuickBooks. These are solved problems handled by purpose-built tools. The platform integrates with them cleanly rather than attempting to replace them badly.

**It is open source.** The software can be forked, modified, and self-hosted. No vendor lock-in. Full data portability. The managed hosting service exists because running it well takes expertise and time — not because the software is a black box.

**It is built to last.** The data model is designed with extensibility and intercompatibility in mind. Custom fields, clean integration boundaries, and standards-based data shapes are not features to add later. They are in the foundation.

---

## The Technical Foundation

The platform is built on Laravel with Twill for content management and Filament for the CRM admin panel. PostgreSQL handles the database. Stripe handles payments. The Spatie package ecosystem handles permissions, activity logging, media management, and custom fields. Each client runs on their own isolated server — no shared databases, no multi-tenancy risk, no blast radius from one client affecting another.

Deployment is automated via Laravel Forge and Envoyer. Migrations, server provisioning, and configuration are scripted. Each new installation takes a fraction of the time the previous one did.

The full technical specification is maintained as a separate living document.

---

## The Business Model

**Setup: $1,500 per client.** Covers data migration from Wild Apricot, Neon, or CiviCRM export formats, server provisioning, integration configuration, and handoff. The client receives a working installation with a base template and their brand colors. Web design is out of scope. This is stated clearly upfront.

**Hosting: $150/month per client.** Infrastructure cost per install is approximately $21/month. The margin is real. This price is directly competitive with Wild Apricot and Neon One for a meaningfully better product.

**Support: billed hourly** beyond a self-service knowledge base. Time is a resource. This is not hidden.

At 31 active clients the recurring revenue alone clears $4,000/month gross. Getting there takes time. In the near term, setup fees are the income engine and recurring compounds in the background.

---

## The Path Forward

The immediate next step is building the software. The data model has been designed. The stack has been chosen. The scope is defined.

The platform is being developed with agentic AI assistance. This accelerates development and reduces the cost of building something that would otherwise require a team. It does not reduce the quality of the decisions being made — those are human decisions, made carefully, with the reasoning documented.

The goal is a working platform with one pilot client inside of six months.

---

## Who This Is For

Any small nonprofit currently on Wild Apricot or Neon One that is frustrated with what they are paying for and open to something better. The sales conversation does not require embellishment. The product either works or it does not.

The platform is also for developers who want to contribute to or fork an open source nonprofit tool that was built with care. The architecture is documented. The decisions are logged. The reasoning is visible.

---

*This is an early-stage document describing an idea in active development. Nothing has been built yet. Everything in here is subject to change. The intention is that it changes less than most things do, because the decisions behind it were made carefully.*
