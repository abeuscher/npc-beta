// Tour A — Dashboard overview. Stays on /admin and walks the sidebar's major
// areas plus the help system, ending in a modal that hands off to the CRM and
// CMS deep-dive tours. Step copy is the owner's script verbatim
// (sessions/tourscripts/main-script.md at session 362).
//
// Anchors are sidebar nav groups/items located by our own route URLs, plus the
// owned `data-tour` help-search marker — never Filament classes.

export const dashboardTour = {
    id: 'dashboard',
    startUrl: 'dashboard',
    steps: [
        {
            anchor: { navGroup: 'contacts' },
            title: 'CRM',
            description:
                'The CRM (Customer Resource Manager) is the core of the product. It helps you track activity with your members and contacts and integrates with the public and portal protected portions of the website to allow signups, donations, and memberships.',
            side: 'right',
        },
        {
            anchor: { nav: 'memberships' },
            title: 'Memberships',
            description:
                'Annual, tiered, monthly, and free - memberships are completely configurable. Members can be granted Portal access to gate information and allow easy signups for new events.',
            side: 'right',
        },
        {
            anchor: { nav: 'donations' },
            title: 'Donations',
            description:
                'Donations and membership payments are handled through Stripe. The information then gets rolled up into the CRM and can be later exported into Quickbooks for accounting purposes.',
            side: 'right',
        },
        {
            anchor: { nav: 'events' },
            title: 'Events',
            description:
                'A first class events system is integrated into both the CMS and CRM, allowing flexibility around ticketing, recurrence, and display. Multiple event widgets allow for easy configuration of signup and messaging as well.',
            side: 'right',
        },
        {
            anchor: { navGroup: 'pages' },
            title: 'CMS',
            description:
                'The CMS (Content Management System) provides control over the appearance of the public site. It features tight integration with the CRM, events, and payment systems. The CMS also features theming, easy import/export, and provides performant and SEO / AEO friendly pages.',
            side: 'right',
        },
        {
            anchor: { nav: 'mailingLists' },
            title: 'Mailing Lists',
            description:
                'Email is a set of concerns best handled by experts, so this product integrates directly to Mailchimp. Lists and groups are handled on the CRM side. Message composition and send are handled by MailChimp’s superior suite of tools.',
            side: 'right',
        },
        {
            anchor: { tour: 'help.search' },
            title: 'Help System',
            description:
                'Help is available to search and is contextually available on every page of the product. This makes it easy to learn and easy to stay oriented to your task instead of hunting through forums and separate docs systems to find answers.',
            side: 'bottom',
        },
        {
            anchor: null,
            title: 'Conclusion',
            description:
                'This concludes your introduction to the product. If you would like to drill down on any of the pieces, there are additional tours built for these components:'
                + '<div class="np-tour-links">'
                + '<button type="button" data-np-tour-goto="crm">Tour the CRM</button>'
                + '<button type="button" data-np-tour-goto="cms">Tour the CMS</button>'
                + '</div>',
        },
    ],
};
