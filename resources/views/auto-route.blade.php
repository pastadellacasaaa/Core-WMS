@extends('layouts.agent')

@section('endpoint', $endpoint)

@section('content')
    @if ($contracts)
        <section class="panel">
            <div class="panel-head">
                <span class="panel-title">Routing table</span>
                <span class="panel-note">Live from <code>GET {{ $endpoint }}</code></span>
            </div>
            <div class="scroller">
                <table>
                    <thead>
                    <tr><th>Agent</th><th>Required fields</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($contracts as $name => $contract)
                        <tr>
                            <td class="code">{{ $name }}</td>
                            <td class="code">{{ implode(', ', $contract['required']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <form class="panel" method="post" action="{{ route('auto-route') }}" data-busy="Routing…">
        @csrf
        <div class="panel-head">
            <span class="panel-title">Request</span>
            <span class="panel-note">Raw body, no agent in the URL</span>
        </div>
        <div class="panel-body">
            <div class="field">
                <label for="payload">Request body (JSON)</label>
                <textarea id="payload" name="payload" rows="13" spellcheck="false">{{ old('payload', $payload) }}</textarea>
            </div>

            <div class="actions">
                <button class="primary" type="submit">Send without naming an agent</button>
            </div>

            <p class="field-hint" style="margin:1.15rem 0 .5rem">Load a sample</p>
            <div class="chips">
                @foreach ($samples as $label => $json)
                    <button class="chip sample" type="button" data-payload="{{ $json }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </form>

    <section class="panel" id="response">
        <div class="panel-head">
            <span class="panel-title">Response</span>
            @isset($status)
                <span class="status {{ $agent ? 'is-ok' : 'is-bad' }}">
                    <span class="status-dot" aria-hidden="true"></span>
                    {{ $status }}
                    <span class="status-sep">·</span>{{ number_format($elapsed ?? 0, 2) }}s
                </span>
            @endisset
        </div>

        @isset($status)
            <div class="panel-body" style="padding-bottom:0">
                @if ($agent)
                    <div class="notice ok" style="margin-bottom:1.15rem">
                        <span class="notice-label">Routed to</span>
                        <div class="notice-body"><strong class="code">{{ $agent }}</strong></div>
                    </div>
                @else
                    <div class="notice bad" style="margin-bottom:1.15rem">
                        <span class="notice-label">Not routed</span>
                        <div class="notice-body">{{ $message ?? 'The Agent Manager did not name an agent.' }}</div>
                    </div>
                @endif
            </div>
            <pre class="json">{{ $body }}</pre>
            @if ($maps)
                <div class="maps panel-body" style="display:flex;flex-wrap:wrap;gap:1rem">
                    @foreach ($maps as $map)
                        {{-- An img data URI displays the SVG without executing scripts inside it. --}}
                        <figure>
                            <figcaption>{{ $map['waveCode'] }}</figcaption>
                            <img src="data:image/svg+xml;base64,{{ base64_encode($map['svg']) }}"
                                 width="{{ $map['widthPx'] }}" height="{{ $map['heightPx'] }}"
                                 alt="Completed location map for wave {{ $map['waveCode'] }}">
                        </figure>
                    @endforeach
                </div>
            @endif
        @else
            <p class="empty">Send a payload to see which agent the fields resolve to.</p>
        @endisset
    </section>

@endsection

@push('scripts')
    <script>
        document.querySelectorAll('.sample').forEach((button) => {
            button.addEventListener('click', () => {
                const field = document.getElementById('payload');
                field.value = button.dataset.payload;
                field.focus();
            });
        });

        @isset($status)
            document.getElementById('response').scrollIntoView({ behavior: 'smooth', block: 'start' });
        @endisset
    </script>
@endpush
