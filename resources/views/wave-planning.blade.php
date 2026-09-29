@extends('layouts.agent')

@section('heading', 'Wave Planning & Picking Route Optimisation')

@section('lede')
    Give the agent a set of order lines; it answers with a picking list per warehouse zone,
    sorted by location, and a completed-style 2D location map for each wave.
@endsection

@section('endpoint', $endpoint ?? '')

@section('content')
    @php
        // A validation failure redirects back, so prefer what was typed over the samples.
        $orders = old('orders', $orders);
    @endphp

    <form class="panel" method="post" action="{{ route('wave-planning') }}" data-busy="Planning waves…">
        @csrf
        <div class="panel-head">
            <span class="panel-title">Request</span>
            <span class="panel-note">Order lines to plan</span>
        </div>
        <div class="panel-body">
            <div class="scroller">
                <table id="orders">
                    <thead>
                    <tr>
                        <th>Order no.</th>
                        <th>Client code</th>
                        <th>Item code</th>
                        <th class="num">Quantity</th>
                        <th>UOM</th>
                        <th>Expiry date (optional)</th>
                        <th>Warehouse location (optional)</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach (array_merge($orders, [[]]) as $index => $order)
                        <tr>
                            <td><input name="orders[{{ $index }}][orderNo]" value="{{ $order['orderNo'] ?? '' }}" aria-label="Order number"></td>
                            <td><input name="orders[{{ $index }}][clientCode]" value="{{ $order['clientCode'] ?? '' }}" aria-label="Client code"></td>
                            <td><input name="orders[{{ $index }}][itemCode]" value="{{ $order['itemCode'] ?? '' }}" aria-label="Item code"></td>
                            <td><input name="orders[{{ $index }}][quantity]" inputmode="decimal" value="{{ $order['quantity'] ?? '' }}" aria-label="Quantity"></td>
                            <td><input name="orders[{{ $index }}][uom]" value="{{ $order['uom'] ?? '' }}" aria-label="UOM"></td>
                            <td><input type="date" name="orders[{{ $index }}][expiryDate]" value="{{ $order['expiryDate'] ?? '' }}" aria-label="Expiry date"></td>
                            <td><input name="orders[{{ $index }}][warehouseLocation]" value="{{ $order['warehouseLocation'] ?? '' }}" aria-label="Warehouse location"></td>
                            <td><button class="link remove" type="button" title="Remove this line">Remove</button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="actions">
                <button class="primary" type="submit">Plan waves</button>
                <button class="ghost" type="button" id="add-row">Add line</button>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="panel-head">
            <span class="panel-title">Response</span>
            @isset($result)
                <span class="panel-note">
                    {{ count($result['pickingLists']) }} {{ Str::plural('zone', count($result['pickingLists'])) }}
                </span>
            @endisset
            @isset($status)
                <span class="status {{ $status === 200 ? 'is-ok' : 'is-bad' }}">
                    <span class="status-dot" aria-hidden="true"></span>
                    {{ $status }} {{ $status === 200 ? 'OK' : 'Error' }}
                    <span class="status-sep">·</span>{{ number_format($elapsed ?? 0, 2) }}s
                </span>
            @endisset
        </div>
        @empty($result)
            <p class="empty">Send a request to see the picking lists and location maps.</p>
        @else
            <div class="panel-body" style="padding-bottom:.4rem">
                <p class="panel-note" style="margin:0">
                    Planned at {{ $result['processedAt'] }}. Walk order is the sequence badge; the list
                    itself is sorted by location, as the agent contract specifies.
                </p>
            </div>
        @endempty
    </section>

    @isset($result)
        @foreach ($result['pickingLists'] as $pickingList)
            <section class="panel">
                <div class="panel-head">
                    <span class="panel-title">Zone</span>
                    <x-thermal :code="$pickingList['warehouseZone']" />
                    <span class="panel-note">
                        {{ count($pickingList['pickingList']) }} {{ Str::plural('stop', count($pickingList['pickingList'])) }}
                    </span>
                </div>
                <div class="panel-body">
                    <div class="zone-split">
                        <div class="picks scroller">
                            <table>
                                <thead>
                                <tr>
                                    <th class="num">Walk</th>
                                    <th>Location</th>
                                    <th>Order no.</th>
                                    <th>Client</th>
                                    <th>Item code</th>
                                    <th class="num">Qty</th>
                                    <th>UOM</th>
                                    <th>Reasons</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($pickingList['pickingList'] as $line)
                                    <tr>
                                        <td class="num"><span class="rank">{{ $line['travelSequence'] }}</span></td>
                                        <td class="code">{{ $line['location'] }}</td>
                                        <td class="code">{{ $line['orderNo'] }}</td>
                                        <td class="code">{{ $line['clientCode'] }}</td>
                                        <td class="code">{{ $line['itemCode'] }}</td>
                                        <td class="num code">{{ $line['quantity'] }}</td>
                                        <td class="code">{{ $line['uom'] }}</td>
                                        <td>
                                            <ul class="reasons">
                                                @foreach ($line['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach
                                            </ul>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if ($pickingList['maps'])
                            <div class="maps">
                                @foreach ($pickingList['maps'] as $map)
                                    {{-- Through a data URI: an img element never runs script in the SVG. --}}
                                    <figure>
                                        <figcaption>{{ $map['waveCode'] }}</figcaption>
                                        <img src="data:image/svg+xml;base64,{{ base64_encode($map['svg']) }}"
                                             width="{{ $map['widthPx'] }}" height="{{ $map['heightPx'] }}"
                                             alt="Completed location map for wave {{ $map['waveCode'] }}">
                                    </figure>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endforeach

        @if ($result['unallocated'])
            <section class="panel">
                <div class="panel-head">
                    <span class="panel-title">Unallocated</span>
                    <span class="panel-note">Lines the agent could not fill</span>
                </div>
                <div class="scroller">
                    <table>
                        <thead>
                        <tr>
                            <th>Order no.</th><th>Client</th><th>Item code</th>
                            <th class="num">Qty short</th><th>Reason</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($result['unallocated'] as $item)
                            <tr>
                                <td class="code">{{ $item['orderNo'] }}</td>
                                <td class="code">{{ $item['clientCode'] }}</td>
                                <td class="code">{{ $item['itemCode'] }}</td>
                                <td class="num code">{{ $item['quantityUnallocated'] }}</td>
                                <td>{{ $item['reason'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @foreach ($result['warnings'] as $warning)
            <div class="notice warn">
                <span class="notice-label">Warning</span>
                <div class="notice-body">{{ $warning }}</div>
            </div>
        @endforeach
    @endisset
@endsection

@push('scripts')
    <script>
        const orderRows = document.querySelector('#orders tbody');

        document.getElementById('add-row').addEventListener('click', () => {
            const row = orderRows.lastElementChild.cloneNode(true);
            row.querySelectorAll('input').forEach((input) => {
                input.name = input.name.replace(/\[\d+\]/, '[' + orderRows.children.length + ']');
                input.value = '';
            });
            orderRows.append(row);
            row.querySelector('input').focus();
        });

        // Names are re-indexed after a removal so the posted array stays contiguous.
        orderRows.addEventListener('click', (event) => {
            if (!event.target.classList.contains('remove')) return;
            if (orderRows.children.length > 1) event.target.closest('tr').remove();

            [...orderRows.children].forEach((row, index) => {
                row.querySelectorAll('input').forEach((input) => {
                    input.name = input.name.replace(/\[\d+\]/, '[' + index + ']');
                });
            });
        });
    </script>
@endpush
