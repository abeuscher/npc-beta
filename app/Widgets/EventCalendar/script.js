window.NPWidgets = window.NPWidgets || {};

window.NPWidgets.eventCalendar = function (opts) {
    return {
        init() {
            if (!window.calendarJs) return;

            const cal = new window.calendarJs(this.$el.id, {
                manualEditingEnabled: false,
                dragAndDropForEventsEnabled: false,
                organizerEditing: false,
                exportEventsEnabled: false,
                importEventsEnabled: false,
                fullScreenModeEnabled: false,
                shareEventsEnabled: false,
                jumpToDateEnabled: false,
                shortcutKeysEnabled: false,
                configurationDialogEnabled: false,
                popUpNotificationsEnabled: false,
                useEscapeKeyToExitFullScreenMode: false,
                tooltipsEnabled: true,
                views: {
                    fullMonth: { showDayNumbers: true, showExtraTitleBarButtons: false },
                    fullWeek:  { enabled: true, showExtraTitleBarButtons: false },
                    fullDay:   { enabled: false, showExtraTitleBarButtons: false },
                    fullYear:  { enabled: false, showExtraTitleBarButtons: false },
                    timeline:  { enabled: false, showExtraTitleBarButtons: false },
                    allEvents: { enabled: false, showExtraTitleBarButtons: false },
                },
                urlWindowTarget: '_self',
                sideMenu: {
                    showDays: false,
                    showGroups: false,
                    showEventTypes: false,
                    showWorkingDays: false,
                    showWeekendDays: false,
                },
                searchOptions: { enabled: false },
            });

            if (opts.defaultView === 'week') {
                cal.setCurrentView(1);
            }

            fetch('/api/events.json')
                .then(r => r.json())
                .then(events => {
                    const holidays = events.map(e => {
                        const d = new Date(e.from);
                        return {
                            day: d.getDate(),
                            month: d.getMonth() + 1,
                            year: d.getFullYear(),
                            title: e.title,
                            onClick: () => { if (e.url) { location.href = e.url; } },
                        };
                    });
                    cal.addHolidays(holidays);
                });
        },
    };
};
