# 015 — Contact and User Separation

**Date:** March 2026 (Session 052)
**Status:** Decided

---

`Contact` and `User` are intentionally separate, unlinked models with no foreign key between them. `User` records are admin and staff accounts — internal actors who authenticate into the Filament panel and manage the CRM. `Contact` records are constituents: donors, members, volunteers, and event registrants who are subjects of that management, not participants in it. Because these two entities have different lifecycles (a user account may be created, suspended, or deleted independently of any constituent record), different permission surfaces, and different retention expectations, keeping them as genuinely independent models makes the privacy-first philosophy of the product easier to honour — it is straightforward to delete or anonymise a constituent record without touching authentication infrastructure, and there is no risk of cascading access-control changes when constituent data is cleaned up.
