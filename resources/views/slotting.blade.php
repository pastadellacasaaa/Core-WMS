@extends('layouts.agent')

@section('heading', 'Intelligent Slotting')

@section('lede')
    Give the agent one or more items; it answers with the bins it would put the stock in,
    best first, and why each one earned its place.
@endsection

@section('endpoint', $endpoint ?? '')

@section('content')
    @php
        $clientCode = old('clientCode', $clientCode);
        $items = old('items', $items);
    @endphp

    <form class="panel" method="post" action="{{ route('slotting') }}" data-busy="Asking the agent…">
        @csrf

        <div class="panel-head">
            <span class="panel-title">Request</span>
            <span class="panel-note">Items to slot</span>
        </div>

        <div class="panel-body">
            <div style="margin-bottom: 1rem;">
                <label for="clientCode">Client code</label>
                <input
                    id="clientCode"
                    name="clientCode"
                    value="{{ $clientCode }}"
                    aria-label="Client code"
                    required
                >
            </div>

            <div class="scroller">
                <table id="items">
                    <thead>
                    <tr>
                        <th>Item code</th>
                        <th class="num">Quantity</th>
                        <th>UOM</th>
                        <th>Expiry date (optional)</th>
                        <th></th>
                    </tr>
                    </thead>

                    <tbody>
                    @foreach (array_merge($items, [[]]) as $index => $item)
                        <tr>
                            <td>
                                <input
                                    name="items[{{ $index }}][itemCode]"
                                    value="{{ $item['itemCode'] ?? '' }}"
                                    aria-label="Item code"
                                >
                            </td>

                            <td>
                                <input
                                    name="items[{{ $index }}][quantity]"
                                    inputmode="decimal"
                                    value="{{ $item['quantity'] ?? '' }}"
                                    aria-label="Quantity"
                                >
                            </td>

                            <td>
                                <input
                                    name="items[{{ $index }}][uom]"
                                    value="{{ $item['uom'] ?? '' }}"
                                    aria-label="UOM"
                                >
                            </td>

                            <td>
                                <input
                                    type="date"
                                    name="items[{{ $index }}][expiryDate]"
                                    value="{{ $item['expiryDate'] ?? '' }}"
                                    aria-label="Expiry date"
                                >
                            </td>

                            <td>
                                <button
                                    class="link remove"
                                    type="button"
                                    title="Remove this item"
                                >
                                    Remove
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="actions">
                <button class="primary" type="submit">
                    Find slotting locations
                </button>

                <button class="ghost" type="button" id="add-item">
                    Add item
                </button>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="panel-head">
            <span class="panel-title">Response</span>

            @isset($status)
                <span class="status {{ $status === 200 ? 'is-ok' : 'is-bad' }}">
                    <span class="status-dot" aria-hidden="true"></span>
                    {{ $status }} {{ $status === 200 ? 'OK' : 'Error' }}
                    <span class="status-sep">·</span>
                    {{ number_format($elapsed ?? 0, 2) }}s
                </span>
            @endisset
        </div>

        @isset($result)
            @php
                $moduleOutputs = $result['moduleOutputs'] ?? [];
            @endphp

            @if (!empty($moduleOutputs))
                <div class="scroller">
                    <table>
                        <thead>
                        <tr>
                            <th>Item code</th>
                            <th>Recommended location</th>
                            <th class="num">Score</th>
                            <th>Status</th>
                            <th>Reason</th>
                        </tr>
                        </thead>

                        <tbody>
                        @foreach ($moduleOutputs as $output)
                            <tr>
                                <td class="code">
                                    {{ $output['query']['sku_id'] ?? '—' }}
                                </td>

                                <td>
                                    @if (!empty($output['recommendation']['location_code']))
                                        <x-thermal :code="$output['recommendation']['location_code']" />
                                    @else
                                        —
                                    @endif
                                </td>

                                <td class="num code">
                                    {{ isset($output['recommendation']['putaway_score'])
                                        ? number_format($output['recommendation']['putaway_score'], 2)
                                        : '—' }}
                                </td>

                                <td class="code">
                                    {{ $output['status'] ?? '—' }}
                                </td>

                                <td>
                                    {{ $output['short_reason']
                                        ?? $result['reason']
                                        ?? '—' }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="empty">
                    No slotting recommendations were returned.
                </p>
            @endif
        @else
            <p class="empty">
                Send a request to see where the agent would slot the stock.
            </p>
        @endisset

        @isset($error)
            @if ($error)
                <p class="empty">{{ $error }}</p>
            @endif
        @endisset
    </section>
@endsection

@push('scripts')
    <script>
        const itemRows = document.querySelector('#items tbody');

        document.getElementById('add-item').addEventListener('click', () => {
            const row = itemRows.lastElementChild.cloneNode(true);

            row.querySelectorAll('input').forEach((input) => {
                input.name = input.name.replace(
                    /\[\d+\]/,
                    '[' + itemRows.children.length + ']'
                );

                input.value = '';
            });

            itemRows.append(row);
            row.querySelector('input').focus();
        });

        itemRows.addEventListener('click', (event) => {
            if (!event.target.classList.contains('remove')) {
                return;
            }

            if (itemRows.children.length > 1) {
                event.target.closest('tr').remove();
            }

            [...itemRows.children].forEach((row, index) => {
                row.querySelectorAll('input').forEach((input) => {
                    input.name = input.name.replace(
                        /\[\d+\]/,
                        '[' + index + ']'
                    );
                });
            });
        });
    </script>
@endpush