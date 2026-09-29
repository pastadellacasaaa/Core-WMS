@props(['code'])

@php
    // Zone codes carry their temperature class: AFRZ1 is frozen, ACHL1 chilled,
    // CDRY1 dry. Location codes lead with the zone (BCHL1-A-06-03), so the same
    // read works for both. It is the first thing anyone needs to know about a bin.
    $zone = strtoupper(explode('-', (string) $code)[0]);
    $class = match (true) {
        str_contains($zone, 'FRZ') => 'frozen',
        str_contains($zone, 'CHL') => 'chilled',
        str_contains($zone, 'DRY') => 'dry',
        default => 'unknown',
    };
    $label = ['frozen' => 'Frozen', 'chilled' => 'Chilled', 'dry' => 'Ambient', 'unknown' => 'Unclassified'][$class];
@endphp

<span class="thermal thermal--{{ $class }}" title="{{ $label }} storage">
    <span class="thermal-dot" aria-hidden="true"></span>{{ $code }}
</span>
