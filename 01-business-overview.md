# Nonprofit Platform — Business Overview
*Working Document — Nothing built yet. March 2026.*

> This document and the software it describes are being developed with agentic AI assistance. This will be declared in the project README.

---

## The Problem

Small nonprofits — 10 to 50 staff, a few thousand members — are paying $200–300/month for platforms that are badly integrated, opaque when things go wrong, and impossible to extend. The dominant options are Wild Apricot and Neon One. Both are SaaS products assembled from white-labeled components by sales-driven organizations. The software reflects that. CiviCRM is the open source alternative but requires expensive infrastructure and significant technical expertise to run.

There is a gap. It is real and well-documented by direct experience with all three platforms.

---

## The Opportunity

The target client is a small nonprofit currently on Wild Apricot or Neon One, frustrated with the product, and looking for something that works. Board members at these organizations come from corporate environments and expect something that functions like real software. The bar is not high. The existing products are genuinely poor.

A focused, well-built alternative — open source, honestly priced, and transparent about what it is doing — is a meaningful differentiator without embellishment.

---

## The Product

A managed hosting service built on an open source nonprofit CRM/CMS platform. The software handles contacts, members, donations, grants, events, and basic content management. Payments go through Stripe. Accounting syncs to QuickBooks. Email marketing syncs to Mailchimp. Each client gets their own isolated installation on their own server.

The software is open source under the MIT license. The business is managed hosting and setup. These are different things.

---

## Revenue Model

**Setup fee:** $1,500 per client. Covers data migration from known export formats (Wild Apricot, Neon, CiviCRM), server provisioning, integration configuration, and handoff. Client receives a working installation with a base template. Web design is not included. This scope is explicit.

**Monthly hosting:** $150/month per client. Covers server, database, backups, monitoring, and platform updates. Infrastructure cost per install is approximately $21/month lean. Margin is real.

**Support:** Billable at an hourly rate beyond a knowledge base. Time is money. This is stated clearly upfront.

---

## Unit Economics

| Metric | Value |
|--------|-------|
| Infrastructure cost per install | ~$21/month |
| Monthly client charge | $150/month |
| Monthly margin per client | ~$129/month |
| Recurring freedom number (4k/month gross) | ~31 clients |
| Setup fee | $1,500 |
| Approximate setup time at maturity | 4–6 hours |

The setup fee is the near-term income engine. Recurring compounds in the background. At 10 active clients plus one setup per month, gross is approximately $2,800/month. Not retirement, but real and growing.

---

## Operational Model

**Automated provisioning** — server setup is scripted. Agentic tooling assists with configuration. Client 10 takes a fraction of the time client 1 does.

**Known import formats** — importers for Wild Apricot, Neon, and CiviCRM export formats are built early. This covers the majority of likely clients and is a one-time investment that pays on every subsequent migration.

**Template-first onboarding** — clients receive a working site with their brand colors and placeholder content. They build their content. This keeps scope clean and keeps the operator out of the web design business.

**Support tiers** — a knowledge base handles common questions. Anything beyond that is billed. This is communicated clearly at onboarding.

---

## Competitive Position

| | Wild Apricot | Neon One | This Platform |
|--|-------------|----------|---------------|
| Price | ~$200–300/month | ~$200–300/month | $150/month + setup |
| Open source | No | No | Yes |
| Data portability | Limited | Limited | Full |
| Integration quality | Poor | Poor | Designed for it |
| Transparency | None | None | Core value |

The sales conversation with a frustrated Wild Apricot or Neon customer does not require embellishment. The product either works or it does not. The goal is to make it work.

---

## Risks

**Client acquisition is slow.** Nonprofit sales cycles are long and referral-dependent. This is not a business that scales quickly. It is a business that compounds steadily.

**Solo operator support load.** Each client is a relationship. Scope discipline is essential to avoid support creep eating margin.

**Open source forks.** Anyone can run the software themselves. The response is to make managed hosting genuinely easier and better than self-hosting, not to fight the license.
