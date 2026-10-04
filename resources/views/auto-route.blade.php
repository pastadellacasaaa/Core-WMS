@extends('layouts.agent')

@section('endpoint', $endpoint)

@section('content')
    @if ($contracts)
        <section class="panel">
            <div class="panel-head">
                <span class="panel-title">Agent Routing Contracts</span>

                <span class="panel-note">
                    Live from <code>GET {{ $endpoint }}</code>
                </span>
            </div>

            <div class="scroller">
                <table>
                    <thead>
                    <tr>
                        <th>Agent</th>
                        <th>Required top-level fields</th>
                    </tr>
                    </thead>

                    <tbody>
                    @foreach ($contracts as $name => $contract)
                        <tr>
                            <td class="code">
                                {{ $name }}
                            </td>

                            <td class="code">
                                {{ implode(', ', $contract['required'] ?? []) }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="panel-body">
                <p class="field-hint" style="margin-top:0">
                    These contracts are retrieved directly from the Agent Manager.
                    Expand an agent to view its current input requirements.
                </p>

                @foreach ($contracts as $name => $contract)
                    <details style="margin-top:.75rem;">
                        <summary
                            class="code"
                            style="cursor:pointer;"
                        >
                            {{ $name }}
                        </summary>

                        <div
                            class="scroller"
                            style="margin-top:.75rem;"
                        >
                            <table>
                                <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Validation rules</th>
                                </tr>
                                </thead>

                                <tbody>
                                @foreach (($contract['rules'] ?? []) as $field => $rules)
                                    <tr>
                                        <td class="code">
                                            {{ $field }}
                                        </td>

                                        <td class="code">
                                            {{ implode(', ', $rules) }}
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endforeach
            </div>
        </section>
    @endif

    <form
        class="panel"
        method="post"
        action="{{ route('auto-route') }}"
        data-busy="Routing request..."
    >
        @csrf

        <div class="panel-head">
            <span class="panel-title">
                Automatic Routing Request
            </span>

            <span class="panel-note">
                Agent selected from request structure
            </span>
        </div>

        <div class="panel-body">
            <div class="field">
                <label for="payload">
                    Request Payload (JSON)
                </label>

                <textarea
                    id="payload"
                    name="payload"
                    rows="13"
                    spellcheck="false"
                >{{ old('payload', $payload) }}</textarea>
            </div>

            <div class="actions">
                <button
                    class="primary"
                    type="submit"
                >
                    Send via Automatic Routing
                </button>
            </div>

            <p
                class="field-hint"
                style="margin:1.15rem 0 .5rem"
            >
                Example Requests
            </p>

            <div class="chips">
                @foreach ($samples as $label => $json)
                    <button
                        class="chip sample"
                        type="button"
                        data-payload="{{ $json }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </form>

    <section
        class="panel"
        id="response"
    >
        <div class="panel-head">
            <span class="panel-title">
                Routing Result
            </span>

            @isset($status)
                <span class="status {{ $agent ? 'is-ok' : 'is-bad' }}">
                    <span
                        class="status-dot"
                        aria-hidden="true"
                    ></span>

                    {{ $status }}

                    <span class="status-sep">·</span>

                    {{ number_format($elapsed ?? 0, 2) }}s
                </span>
            @endisset
        </div>

        @isset($status)
            <div class="panel-body">
                @if ($agent)
                    <div class="notice ok">
                        <span class="notice-label">
                            Routed to
                        </span>

                        <div class="notice-body">
                            <strong class="code">
                                {{ $agent }}
                            </strong>
                        </div>
                    </div>
                @else
                    <div class="notice bad">
                        <span class="notice-label">
                            Request not routed
                        </span>

                        <div class="notice-body">
                            {{ $message ?? 'The Agent Manager did not identify a matching agent.' }}
                        </div>
                    </div>

                    <p
                        class="field-hint"
                        style="margin-top:1rem;"
                    >
                        Review the routing contracts above to see the
                        request fields accepted by each agent.
                    </p>
                @endif
            </div>

            @if ($agent)
                <div class="panel-head">
                    <span class="panel-title">
                        Agent Response
                    </span>
                </div>

                <pre class="json">{{ $body }}</pre>
            @else
                <div
                    class="panel-body"
                    style="padding-top:0;"
                >
                    <details>
                        <summary style="cursor:pointer;">
                            Show Raw API Response
                        </summary>

                        <pre
                            class="json"
                            style="margin-top:.75rem;"
                        >{{ $body }}</pre>
                    </details>
                </div>
            @endif

            @if ($maps)
                <div
                    class="maps panel-body"
                    style="display:flex;flex-wrap:wrap;gap:1rem"
                >
                    @foreach ($maps as $map)
                        <figure>
                            <figcaption>
                                {{ $map['waveCode'] }}
                            </figcaption>

                            <img
                                src="data:image/svg+xml;base64,{{ base64_encode($map['svg']) }}"
                                width="{{ $map['widthPx'] }}"
                                height="{{ $map['heightPx'] }}"
                                alt="Completed location map for wave {{ $map['waveCode'] }}"
                            >
                        </figure>
                    @endforeach
                </div>
            @endif
        @else
            <p class="empty">
                Send a request to see which agent the Agent Manager selects.
            </p>
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
            document
                .getElementById('response')
                .scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
        @endisset
    </script>
@endpush