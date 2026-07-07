// Tour B — CRM deep-dive. Runs on the Contacts list with one contained hop:
// the user's own click on the seeded hero contact carries the tour into that
// record, where the remaining steps finish. Step copy is the owner's script
// verbatim (sessions/tourscripts/crm-script.md at session 362); the click
// instruction on the record step is the one mechanical addition.
//
// Anchors follow the 361 lesson — eager, stable, in-view content (the contacts
// table, the record's custom-fields section) or sidebar items located by our
// own route URLs. Anchors that legitimately may not exist on an install (the
// custom-fields section hides when none are defined) carry a short timeout and
// fall back to a centered popover.

export const crmTour = {
    id: 'crm',
    startUrl: 'contacts',
    steps: [
        {
            anchor: null,
            title: 'Intro',
            description:
                'The Customer Resource Manager is a central location for keeping track of your online contacts, members, and affiliates.',
        },
        {
            anchor: { tour: 'resource.records', target: 'next' },
            title: 'Contacts List View',
            description:
                'On the main contacts view page, you can filter and sort results, and search for specific individuals as well.',
            side: 'top',
        },
        {
            anchor: { nav: 'importer' },
            title: 'Import / Export',
            description:
                'Contacts are easy to export and import and can be imported from other systems',
            side: 'right',
        },
        {
            anchor: { navRow: 'contactRecord' },
            interactive: true,
            title: 'Contact Record View',
            description:
                'Each contact record contains detailed information on the contacts activity, origin, and membership and donation information. <strong>Click this contact</strong> to open their record.',
            side: 'top',
        },
        {
            anchor: { tour: 'record.actions' },
            title: 'Contact Notes View',
            description:
                'To track personal or offline interactions with the contact, notes are available and can be tagged and stored with the record.',
            side: 'bottom',
        },
        {
            anchor: { tour: 'record.custom-fields', timeout: 2000 },
            title: 'Custom Fields',
            description:
                'Adding extra fields to the contact record can be done either at time of import or at any point afterwards',
            side: 'top',
        },
        {
            anchor: { nav: 'recordDetailViews' },
            title: 'Custom Views',
            description:
                'Custom fields can also be organized into custom views. If you are coming to us from another system, your data can remain intact and display similarly to how it did in your previous system. Note that this involves custom work and therefore additional payment. Please see the pricing page for full details.',
            side: 'right',
        },
        {
            anchor: { nav: 'mailingLists' },
            title: 'Mailing Lists',
            description:
                'You can create mailing lists and manage subscription and unsubscribe. These lists are then packaged and managed within the mailing list view, which integrates into MailChimp for composition and sending.',
            side: 'right',
        },
    ],
};
