// The demo / marketing product tour. A cold, anonymous prospect lands in the
// admin and gets walked through the real product — real pages, real seeded data
// behind the spotlight — so the breadth becomes the selling point instead of an
// overwhelm. Point-and-invite; every claim is a live, demoable feature.
//
// `page` is a key into window.__npTour.urls (server-emitted only for pages the
// viewer can reach). A step whose page the role can't reach is skipped, so the
// tour adapts to the viewer rather than breaking.

export const productTour = [
    {
        page: 'dashboard',
        anchor: null,
        title: 'Welcome to your CRM',
        description:
            'This is the back office for your whole organization — supporters, events, donations, your website, and your books, all in one place. Take a minute and I’ll show you around. Close anytime and explore on your own.',
    },
    {
        page: 'contacts',
        anchor: { tour: 'resource.records', target: 'next' },
        title: 'Contacts',
        description:
            'Every supporter is one rich record — profile, giving history, memberships, event attendance, and notes, together. Click any row to open the full profile.',
        side: 'top',
    },
    {
        page: 'donations',
        anchor: { tour: 'resource.records', target: 'next' },
        title: 'Donations',
        description:
            'Track every gift and see each donor’s full giving history at a glance — the heartbeat of a nonprofit.',
        side: 'top',
    },
    {
        page: 'mailingLists',
        anchor: { tour: 'resource.records', target: 'next' },
        title: 'Mailing lists',
        description:
            'Build segments here and sync them straight to your email tool (Mailchimp) — no exporting spreadsheets back and forth.',
        side: 'top',
    },
    {
        page: 'memberships',
        anchor: { tour: 'resource.records', target: 'next' },
        title: 'Memberships',
        description:
            'Offer tiered memberships with renewals, so your recurring supporters are tracked and managed automatically.',
        side: 'top',
    },
    {
        page: 'events',
        anchor: { tour: 'resource.records', target: 'next' },
        title: 'Events & your website',
        description:
            'Create and manage events here — and every event gets a public landing page you build visually. Open an event’s landing page to see the page builder, then hit “View site” to see it live in a new tab. Your whole public website is built right in.',
        side: 'top',
    },
    {
        page: 'transactions',
        anchor: { tour: 'resource.records', target: 'next' },
        title: 'Payments & accounting',
        description:
            'Every payment — events, products, memberships, donations — runs through Stripe and lands here as a real transaction. It all syncs to QuickBooks in the background, so your books stay current without double entry.',
        side: 'top',
    },
    {
        page: 'importer',
        anchor: { tour: 'page.content', target: 'parent' },
        title: 'Bring your data in (and out)',
        description:
            'Guided importers move your existing contacts, donations, and more into the CRM — upload a spreadsheet, map your columns, preview, import. And your data is always yours to export back out.',
    },
    {
        page: 'roles',
        anchor: { tour: 'page.content', target: 'parent' },
        title: 'Roles & permissions',
        description:
            'Create as many roles as you need and control exactly who can see and do what — fine-grained, down to read, write, and delete on every part of the system. That’s the tour — close this and explore anything you like.',
    },
];
