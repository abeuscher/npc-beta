// The demo / marketing product tour. A cold, anonymous prospect lands in the
// admin and gets walked through the real product — real pages, real seeded data
// behind the spotlight — so the breadth becomes the selling point instead of an
// overwhelm. Point-and-invite; every claim is a live, demoable feature.
//
// `page` is a key into window.__npTour.urls (server-emitted only for pages the
// viewer can reach). A step whose page the role can't reach is skipped, so the
// tour adapts to the viewer rather than breaking. `interactive: true` steps hand
// the click to the user — they navigate the product themselves and the tour
// follows.

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
        anchor: { navRow: 'contactRecord' },
        interactive: true,
        title: 'Contacts',
        description:
            'Every supporter is one rich record — profile, giving history, memberships, event attendance, and notes, together. <strong>Click this contact</strong> to open their record.',
        side: 'top',
    },
    {
        page: 'contactRecord',
        anchor: { tour: 'record.membership' },
        title: 'One record, the whole picture',
        description:
            'Open a supporter and everything about them is right here. This is their membership — tier, status, and renewals, tracked automatically.',
        side: 'top',
    },
    {
        page: 'contactRecord',
        anchor: { tour: 'view-transactions' },
        interactive: true,
        title: 'Their giving & payments',
        description:
            'And their full giving and payment history is one click away. <strong>Click “View transactions”</strong> to see it.',
        side: 'bottom',
    },
    {
        page: 'transactions',
        anchor: { tour: 'resource.records', target: 'next' },
        title: 'Payments & accounting',
        description:
            'Every gift and payment — donations, event tickets, membership dues — runs through Stripe and lands here as a real transaction, and syncs to QuickBooks so your books stay current without double entry.',
        side: 'top',
    },
    {
        page: 'mailingLists',
        anchor: { tour: 'resource.records', target: 'next' },
        title: 'Mailing lists',
        description:
            'Build segments here and sync them straight to Mailchimp — no exporting spreadsheets back and forth.',
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
        page: 'importer',
        anchor: null,
        title: 'Bring your data in (and out)',
        description:
            'Guided importers move your existing contacts, donations, and more into the CRM — upload a spreadsheet, map your columns, preview, import. And your data is always yours to export back out.',
    },
    {
        page: 'roles',
        anchor: { tour: 'resource.records', target: 'next' },
        title: 'Roles & permissions',
        description:
            'Create as many roles as you need and control exactly who can see and do what — fine-grained, down to read, write, and delete on every part of the system.',
        side: 'top',
    },
    {
        page: 'dashboard',
        anchor: { tour: 'help.search' },
        title: 'That’s the tour',
        description:
            'Curious about anything else? Search any feature right here and jump straight to help for it.',
        side: 'bottom',
    },
    {
        page: 'dashboard',
        anchor: { tour: 'help.flyout' },
        title: 'Help is always one click away',
        description:
            'And the <strong>?</strong> on every screen opens help for that exact page. Close this and explore anything you like.',
        side: 'bottom',
    },
];
