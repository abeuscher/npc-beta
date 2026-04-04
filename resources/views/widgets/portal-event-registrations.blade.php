@php
    $portalUser = auth('portal')->user();
    $contact    = $portalUser?->contact;
    $regs       = $contact
        ? $contact->eventRegistrations()->with('event')->orderByDesc('registered_at')->get()
        : collect();
@endphp

@if ($portalUser && $contact)
    @if ($regs->isEmpty())
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
                            <td>{{ $reg->event->title ?? '—' }}</td>
                            <td>{{ $reg->event?->starts_at?->format('F j, Y') ?? '—' }}</td>
                            <td>{{ ucfirst($reg->status) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endif
