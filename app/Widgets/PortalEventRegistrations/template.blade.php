@php
    $member = $widgetData['item'] ?? null;
    $regs   = $member['event_registrations'] ?? [];
@endphp

@if ($member && $member['has_contact'])
    @if ($regs === [])
        <p class="text-muted">No event registrations on file.</p>
    @else
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Event</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($regs as $reg)
                        <tr>
                            <td>{{ $reg['event_title'] !== '' ? $reg['event_title'] : '—' }}</td>
                            <td>{{ $reg['event_date'] !== '' ? $reg['event_date'] : '—' }}</td>
                            <td>{{ ucfirst($reg['status']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif
