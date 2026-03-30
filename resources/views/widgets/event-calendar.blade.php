@php
    $heading = $config['heading'] ?? '';
    $defaultView = in_array($config['default_view'] ?? '', ['month', 'week']) ? $config['default_view'] : 'month';
    $calendarId = 'cal-' . \Illuminate\Support\Str::random(8);
@endphp

@if ($heading)
    <h2>{{ $heading }}</h2>
@endif

<div id="{{ $calendarId }}" class="widget-event-calendar"></div>

<script>
(function () {
    var id = @json($calendarId);
    var defaultView = @json($defaultView);

    function init() {
        var cal = new calendarJs(id, {
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
                fullWeek: { enabled: true, showExtraTitleBarButtons: false },
                fullDay: { enabled: false, showExtraTitleBarButtons: false },
                fullYear: { enabled: false, showExtraTitleBarButtons: false },
                timeline: { enabled: false, showExtraTitleBarButtons: false },
                allEvents: { enabled: false, showExtraTitleBarButtons: false }
            },
            urlWindowTarget: '_self',
            sideMenu: { showDays: false, showGroups: false, showEventTypes: false, showWorkingDays: false, showWeekendDays: false },
            searchOptions: { enabled: false }
        });

        if (defaultView === 'week') {
            cal.setCurrentView(1);
        }

        fetch('/api/events.json')
            .then(function (r) { return r.json(); })
            .then(function (events) {
                var holidays = events.map(function (e) {
                    var d = new Date(e.from);
                    return {
                        day: d.getDate(),
                        month: d.getMonth() + 1,
                        year: d.getFullYear(),
                        title: e.title,
                        onClick: function () { if (e.url) { location.href = e.url; } }
                    };
                });
                cal.addHolidays(holidays);
            });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
