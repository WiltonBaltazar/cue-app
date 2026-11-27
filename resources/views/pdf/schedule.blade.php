@php
    $zone = config('app.display_timezone');
    $sameDay = $schedule->starts_on->isSameDay($schedule->ends_on);
    $sameMonth = $schedule->starts_on->isSameMonth($schedule->ends_on);
    $sameYear = $schedule->starts_on->isSameYear($schedule->ends_on);
    $range = match (true) {
        $sameDay => $schedule->starts_on->translatedFormat('j \d\e F \d\e Y'),
        $sameMonth => $schedule->starts_on->format('j').' a '.$schedule->ends_on->translatedFormat('j \d\e F \d\e Y'),
        $sameYear => $schedule->starts_on->translatedFormat('j \d\e F').' a '.$schedule->ends_on->translatedFormat('j \d\e F \d\e Y'),
        default => $schedule->starts_on->translatedFormat('j \d\e F \d\e Y').' a '.$schedule->ends_on->translatedFormat('j \d\e F \d\e Y'),
    };
@endphp
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Escala da equipa de mídia, {{ $range }}</title>
    {{-- dompdf: no flexbox or grid, and fonts must be local TTF files. Layout is tables. --}}
    <style>
        @font-face { font-family: 'Instrument Sans'; font-weight: normal; src: url('{{ resource_path('fonts/InstrumentSans-Regular.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Instrument Sans'; font-weight: bold; src: url('{{ resource_path('fonts/InstrumentSans-SemiBold.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Saira'; font-weight: normal; src: url('{{ resource_path('fonts/SairaSemiCondensed-SemiBold.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Saira'; font-weight: bold; src: url('{{ resource_path('fonts/SairaSemiCondensed-Bold.ttf') }}') format('truetype'); }

        @page { margin: 34pt 36pt 44pt; }
        body { font-family: 'Instrument Sans', 'DejaVu Sans', sans-serif; font-size: 10pt; line-height: 1.1; color: #1a2436; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 0; vertical-align: top; }

        .slate { margin: -34pt -36pt 14pt; padding: 15pt 36pt 14pt; background: #142f5c; border-bottom: 4pt solid #fec015; color: #fff; }
        .slate td { vertical-align: middle; }
        .slate .mark { width: 62pt; }
        .mark-tile { width: 34pt; height: 36pt; padding: 6pt 7pt; background: #fff; border-radius: 6pt; }
        .mark-tile img { width: 34pt; height: 36pt; }
        .slate .team { font-size: 10.5pt; color: #c3cfe4; }
        .slate .range { font-family: 'Saira'; font-weight: bold; font-size: 23pt; line-height: 1.05; }
        .slate .state { width: 80pt; text-align: right; }
        .draft { display: inline-block; padding: 3pt 9pt; background: #fec015; color: #142f5c; border-radius: 4pt; font-weight: bold; }

        .event { margin-bottom: 9pt; border: 0.75pt solid #d5dce8; border-radius: 6pt; page-break-inside: avoid; }
        .head { background: #f1f4f9; border-bottom: 0.75pt solid #d5dce8; border-radius: 6pt 6pt 0 0; padding: 4pt 12pt 5pt; }
        .head td { vertical-align: middle; }
        .date { width: 62pt; }
        .date .day { font-family: 'Saira'; font-weight: bold; font-size: 20pt; line-height: 0.7; color: #142f5c; }
        .date .month { font-size: 9pt; color: #536079; }
        .name { font-family: 'Saira'; font-weight: normal; font-size: 13pt; line-height: 0.95; color: #142f5c; }
        .where { font-size: 9pt; color: #536079; }
        .time { width: 62pt; text-align: right; }
        .time .label { font-size: 8.5pt; color: #536079; }
        .time .value { font-family: 'Saira'; font-weight: normal; font-size: 15pt; line-height: 0.85; color: #536079; }
        .time.arrival .value { font-weight: bold; color: #155aa8; }

        .crew { padding: 2pt 12pt 3pt; }
        .crew td { padding: 3pt 0; border-bottom: 0.75pt solid #e6eaf1; }
        .crew tr.last td { border-bottom: none; }
        .crew td.role { width: 38%; padding-right: 10pt; color: #536079; }
        .person { font-weight: bold; }
        .gap { color: #b3261e; }
        .empty { color: #536079; }
    </style>
</head>
<body>
    <div class="slate">
        <table>
            <tr>
                <td class="mark">
                    <div class="mark-tile"><img src="{{ public_path('images/rtvao-mark.png') }}" alt=""></div>
                </td>
                <td>
                    <div class="team">Escala da equipa de mídia</div>
                    <div class="range">{{ $range }}</div>
                </td>
                <td class="state">
                    @unless ($schedule->isPublished())
                        <span class="draft">{{ $schedule->status->label() }}</span>
                    @endunless
                </td>
            </tr>
        </table>
    </div>

    @forelse ($events as $event)
        <div class="event">
            <div class="head">
                <table>
                    <tr>
                        <td class="date">
                            <div class="day">{{ $event->date->format('d') }}</div>
                            <div class="month">{{ $event->date->translatedFormat('F') }}</div>
                        </td>
                        <td>
                            <div class="name">{{ $event->name }}</div>
                            <div class="where">{{ Str::ucfirst($event->date->translatedFormat('l')) }}, {{ $event->location }}</div>
                        </td>
                        <td class="time arrival">
                            <div class="label">Chegada</div>
                            <div class="value">{{ $event->arrival_at->setTimezone($zone)->format('H:i') }}</div>
                        </td>
                        <td class="time">
                            <div class="label">Início</div>
                            <div class="value">{{ $event->starts_at->setTimezone($zone)->format('H:i') }}</div>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="crew">
                <table>
                    @foreach ($event->positions->sortBy('teamRole.sort_order') as $position)
                        @php($missing = $position->quantity - $position->assignments->count())
                        <tr @class(['last' => $loop->last])>
                            <td class="role">{{ $position->teamRole->name }}</td>
                            <td>
                                @foreach ($position->assignments as $assignment)
                                    <div class="person">{{ $fullNames ? $assignment->volunteer->firstAndLastName() : $assignment->volunteer->shortName() }}</div>
                                @endforeach
                                @if ($missing > 0)
                                    <div class="gap">{{ $missing }} por preencher</div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
    @empty
        <p class="empty">Não há eventos nesta escala.</p>
    @endforelse
</body>
</html>
