
@extends('layouts.agent')

@section('heading', 'Wave Planning & Picking Route Optimisation')

@section('lede')
    Give the agent one Picking List ID, a warehouse zone, and a list of orders
    with their items. The agent returns picking lists and
    2D warehouse location maps.
@endsection

@section('endpoint', $endpoint ?? '')

@section('content')
    @php
        $pickingListId = old('pickingListId', $pickingListId ?? 'PL001');
        $warehouseZone = old('warehouseZone', $warehouseZone ?? 'EFRZ1');
        $orders = old('orders', $orders ?? []);
    @endphp

    <form class="panel" method="post"
          action="{{ route('wave-planning') }}"
          data-busy="Planning waves…">
        @csrf

        <div class="panel-head">
            <span class="panel-title">Request</span>
            <span class="panel-note">
                One Picking List ID per request
            </span>
        </div>

        <div class="panel-body">

            <div style="margin-bottom:1.5rem">
                <label for="pickingListId">
                    Picking List ID
                </label>
                <input
                    type="text"
                    id="pickingListId"
                    name="pickingListId"
                    value="{{ $pickingListId }}"
                    maxlength="50"
                    required
                    placeholder="e.g. PL001"
                    style="display:block;width:100%;margin-top:.4rem"
                >
                @error('pickingListId')
                    <div class="notice warn">
                        {{ $message }}
                    </div>
                @enderror
            </div>

            <div style="margin-bottom:1.5rem">
                <label for="warehouseZone">Warehouse Zone</label>
                <input type="text" id="warehouseZone" name="warehouseZone"
                       value="{{ $warehouseZone }}" maxlength="50" required
                       placeholder="e.g. EFRZ1"
                       style="display:block;width:100%;margin-top:.4rem">
                @error('warehouseZone')
                    <div class="notice warn">{{ $message }}</div>
                @enderror
            </div>
            @if ($errors->any())
                <div class="notice warn" role="alert">
                    <span class="notice-label">
                        Validation Error
                    </span>
                    <div class="notice-body">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <div id="orders">
                @foreach ($orders as $index => $order)
                    <div class="wave-order panel"
                         style="margin-bottom:1rem">

                        <div class="panel-head">
                            <span class="panel-title order-number">
                                Order {{ $index + 1 }}
                            </span>
                            <button type="button"
                                    class="ghost remove-order">
                                Remove order
                            </button>
                        </div>

                        <div class="panel-body">

                            <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">

                                <label style="flex:1;min-width:160px">
                                    Order Number
                                    <input
                                        type="text"
                                        data-order-field="orderNo"
                                        name="orders[{{ $index }}][orderNo]"
                                        value="{{ $order['orderNo'] ?? '' }}"
                                        maxlength="50"
                                        required
                                        style="display:block;width:100%"
                                    >
                                </label>

                                <label style="flex:1;min-width:160px">
                                    Client Code
                                    <input
                                        type="text"
                                        data-order-field="clientCode"
                                        name="orders[{{ $index }}][clientCode]"
                                        value="{{ $order['clientCode'] ?? '' }}"
                                        maxlength="50"
                                        required
                                        style="display:block;width:100%"
                                    >
                                </label>
                            </div>

                            <div class="scroller">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Item Code</th>
                                            <th>Quantity</th>
                                            <th>UOM</th>
                                            <th>Specific Expiry Date</th>
                                            <th>Specific Location</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach (($order['items'] ?? [[]]) as $itemIndex => $item)
                                            <tr>
                                                <td>
                                                    <input
                                                        type="text"
                                                        data-item-field="itemCode"
                                                        name="orders[{{ $index }}][items][{{ $itemIndex }}][itemCode]"
                                                        value="{{ $item['itemCode'] ?? '' }}"
                                                        required
                                                        maxlength="50"
                                                        placeholder="DRY-0211"
                                                    >
                                                </td>
                                                <td>
                                                    <input
                                                        type="number"
                                                        data-item-field="quantity"
                                                        name="orders[{{ $index }}][items][{{ $itemIndex }}][quantity]"
                                                        value="{{ $item['quantity'] ?? '' }}"
                                                        min="0.000001"
                                                        step="any"
                                                        required
                                                    >
                                                </td>
                                                <td>
                                                    <input
                                                        type="text"
                                                        data-item-field="uom"
                                                        name="orders[{{ $index }}][items][{{ $itemIndex }}][uom]"
                                                        value="{{ $item['uom'] ?? '' }}"
                                                        required
                                                        maxlength="10"
                                                        placeholder="CTN"
                                                    >
                                                </td>
                                                <td>
                                                    <input
                                                        type="date"
                                                        data-item-field="specificExpiryDate"
                                                        name="orders[{{ $index }}][items][{{ $itemIndex }}][specificExpiryDate]"
                                                        value="{{ $item['specificExpiryDate'] ?? '' }}"
                                                    >
                                                </td>
                                                <td>
                                                    <input
                                                        type="text"
                                                        data-item-field="specificLocation"
                                                        name="orders[{{ $index }}][items][{{ $itemIndex }}][specificLocation]"
                                                        value="{{ $item['specificLocation'] ?? '' }}"
                                                        maxlength="80"
                                                        placeholder="Optional"
                                                    >
                                                </td>
                                                <td>
                                                    <button type="button"
                                                            class="ghost remove-item">
                                                        Remove
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <div class="actions">
                                <button type="button"
                                        class="ghost add-item">
                                    Add item
                                </button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <template id="order-template">
                <div class="wave-order panel"
                     style="margin-bottom:1rem">
                    <div class="panel-head">
                        <span class="panel-title order-number">
                            Order
                        </span>
                        <button type="button"
                                class="ghost remove-order">
                            Remove order
                        </button>
                    </div>

                    <div class="panel-body">
                        <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">

                            <label style="flex:1;min-width:160px">
                                Order Number
                                <input type="text"
                                       data-order-field="orderNo"
                                       maxlength="50"
                                       required
                                       style="display:block;width:100%">
                            </label>

                            <label style="flex:1;min-width:160px">
                                Client Code
                                <input type="text"
                                       data-order-field="clientCode"
                                       maxlength="50"
                                       required
                                       style="display:block;width:100%">
                            </label>
                        </div>

                        <div class="scroller">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Item Code</th>
                                        <th>Quantity</th>
                                        <th>UOM</th>
                                        <th>Specific Expiry Date</th>
                                        <th>Specific Location</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <input type="text"
                                                   data-item-field="itemCode"
                                                   required maxlength="50"
                                                   placeholder="DRY-0211">
                                        </td>
                                        <td>
                                            <input type="number"
                                                   data-item-field="quantity"
                                                   min="0.000001"
                                                   step="any" required>
                                        </td>
                                        <td>
                                            <input type="text"
                                                   data-item-field="uom"
                                                   required maxlength="10"
                                                   placeholder="CTN">
                                        </td>
                                        <td>
                                            <input type="date"
                                                   data-item-field="specificExpiryDate">
                                        </td>
                                        <td>
                                            <input type="text"
                                                   data-item-field="specificLocation"
                                                   maxlength="80"
                                                   placeholder="Optional">
                                        </td>
                                        <td>
                                            <button type="button"
                                                    class="ghost remove-item">
                                                Remove
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="actions">
                            <button type="button" class="ghost add-item">
                                Add item
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <div class="actions">
                <button class="primary" type="submit">
                    Plan waves
                </button>
                <button class="ghost" type="button" id="add-order">
                    Add order
                </button>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="panel-head">
            <span class="panel-title">Response</span>

            @isset($result)
                <span class="panel-note">
                    {{ count($result['pickingLists'] ?? []) }}
                    {{ Str::plural('zone', count($result['pickingLists'] ?? [])) }}
                </span>
            @endisset

            @isset($status)
                <span class="status {{ $status === 200 ? 'is-ok' : 'is-bad' }}">
                    <span class="status-dot" aria-hidden="true"></span>
                    {{ $status }}
                    {{ $status === 200 ? 'OK' : 'Error' }}
                    <span class="status-sep">·</span>
                    {{ number_format($elapsed ?? 0, 2) }}s
                </span>
            @endisset
        </div>

        @if (!empty($error))
            <div class="notice warn">
                <span class="notice-label">Error</span>
                <div class="notice-body">{{ $error }}</div>
            </div>
        @endif

        @empty($result)
            <p class="empty">
                Send a request to see the picking lists and location maps.
            </p>
        @else
            <div class="panel-body" style="padding-bottom:.4rem">
                <p class="panel-note" style="margin:0">
                    Planned at {{ $result['processedAt'] ?? 'N/A' }}.
                    Walk order is shown by the sequence badge.
                </p>
            </div>
        @endempty
    </section>

    @isset($result)
        @foreach (($result['pickingLists'] ?? []) as $pickingList)
            <section class="panel">
                <div class="panel-head">
                    <span class="panel-title">Zone</span>
                    <x-thermal :code="$pickingList['warehouseZone']" />
                    <span class="panel-note">
                        {{ count($pickingList['pickingList'] ?? []) }}
                        {{ Str::plural('stop', count($pickingList['pickingList'] ?? [])) }}
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
                                    @foreach (($pickingList['pickingList'] ?? []) as $line)
                                        <tr>
                                            <td class="num">
                                                <span class="rank">
                                                    {{ $line['travelSequence'] }}
                                                </span>
                                            </td>
                                            <td class="code">{{ $line['location'] }}</td>
                                            <td class="code">{{ $line['orderNo'] }}</td>
                                            <td class="code">{{ $line['clientCode'] }}</td>
                                            <td class="code">{{ $line['itemCode'] }}</td>
                                            <td class="num code">{{ $line['quantity'] }}</td>
                                            <td class="code">{{ $line['uom'] }}</td>
                                            <td>
                                                <ul class="reasons">
                                                    @foreach (($line['reasons'] ?? []) as $reason)
                                                        <li>{{ $reason }}</li>
                                                    @endforeach
                                                </ul>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if (!empty($pickingList['maps']))
                            <div class="maps">
                                @foreach ($pickingList['maps'] as $map)
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
                    </div>
                </div>
            </section>
        @endforeach

        @if (!empty($result['unallocated']))
            <section class="panel">
                <div class="panel-head">
                    <span class="panel-title">Unallocated</span>
                    <span class="panel-note">
                        Lines the agent could not fill
                    </span>
                </div>
                <div class="scroller">
                    <table>
                        <thead>
                            <tr>
                                <th>Order no.</th>
                                <th>Client</th>
                                <th>Item code</th>
                                <th class="num">Qty short</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($result['unallocated'] as $item)
                                <tr>
                                    <td class="code">{{ $item['orderNo'] }}</td>
                                    <td class="code">{{ $item['clientCode'] }}</td>
                                    <td class="code">{{ $item['itemCode'] }}</td>
                                    <td class="num code">
                                        {{ $item['quantityUnallocated'] }}
                                    </td>
                                    <td>{{ $item['reason'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @foreach (($result['warnings'] ?? []) as $warning)
            <div class="notice warn">
                <span class="notice-label">Warning</span>
                <div class="notice-body">{{ $warning }}</div>
            </div>
        @endforeach
    @endisset
@endsection

@push('scripts')
<script>
    const orders = document.getElementById('orders');
    const template = document.getElementById('order-template');

    function reindex() {
        [...orders.children].forEach((order, index) => {
            order.querySelectorAll('[data-order-field]').forEach((input) => {
                input.name = `orders[${index}][${input.dataset.orderField}]`;
            });

            order.querySelectorAll('tbody tr').forEach((row, itemIndex) => {
                row.querySelectorAll('[data-item-field]').forEach((input) => {
                    input.name = `orders[${index}][items][${itemIndex}][${input.dataset.itemField}]`;
                });
            });

            order.querySelector('.order-number').textContent =
                `Order ${index + 1}`;

            order.querySelector('.remove-order').disabled =
                orders.children.length === 1;

            const rows = order.querySelectorAll('tbody tr');

            rows.forEach((row) => {
                row.querySelector('.remove-item').disabled =
                    rows.length === 1;
            });
        });
    }

    document.getElementById('add-order').addEventListener('click', () => {
        orders.append(template.content.cloneNode(true));
        reindex();

        orders.lastElementChild
            .querySelector('[data-order-field="orderNo"]')
            .focus();
    });

    orders.addEventListener('click', (event) => {
        const button = event.target.closest('button');

        if (!button) return;

        const order = button.closest('.wave-order');

        if (
            button.classList.contains('remove-order')
            && orders.children.length > 1
        ) {
            order.remove();
        }

        if (button.classList.contains('add-item')) {
            const row = template.content
                .querySelector('tbody tr')
                .cloneNode(true);

            order.querySelector('tbody').append(row);

            row.querySelector('[data-item-field="itemCode"]').focus();
        }

        if (
            button.classList.contains('remove-item')
            && order.querySelectorAll('tbody tr').length > 1
        ) {
            button.closest('tr').remove();
        }

        reindex();
    });

    reindex();
</script>
@endpush