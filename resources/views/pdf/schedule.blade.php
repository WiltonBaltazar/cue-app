@php($zone = config('app.display_timezone'))
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Escala {{ $schedule->starts_on->format('d/m/Y') }}–{{ $schedule->ends_on->format('d/m/Y') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { color: #555; margin-bottom: 12px; }
        .event { margin-bottom: 10px; page-break-inside: avoid; }
        .event h2 { font-size: 12px; margin: 0; padding: 4px 6px; background: #eee; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 6px; border-bottom: 1px solid #ddd; vertical-align: top; }
        td.role { width: 40%; }
        .gap { color: #b00; }
    </style>
</head>
<body>
    <h1>Escala da equipa de mídia</h1>
    <div class="meta">
        {{ $schedule->starts_on->format('d/m/Y') }} a {{ $schedule->ends_on->format('d/m/Y') }}
        · {{ $schedule->status->label() }}
    </div>

    @foreach ($events as $event)
        <div class="event">
            <h2>
                {{ $event->date->format('d/m') }} · {{ $event->name }}
                · chegada {{ $event->arrival_at->setTimezone($zone)->format('H:i') }}
                · início {{ $event->starts_at->setTimezone($zone)->format('H:i') }}
                · {{ $event->location }}
            </h2>
            <table>
                @foreach ($event->positions as $position)
                    <tr>
                        <td class="role">{{ $position->teamRole->name }}</td>
                        <td>
                            {{ $position->assignments->map(fn ($assignment) => $assignment->volunteer->shortName())->join(', ') }}
                            @if ($position->assignments->count() < $position->quantity)
                                <span class="gap">({{ $position->quantity - $position->assignments->count() }} por preencher)</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach
</body>
</html>
