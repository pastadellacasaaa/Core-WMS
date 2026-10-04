@extends('layouts.agent')

@section('heading', 'Intelligent Slotting')

@section('lede')
    Select one or more products from the Core WMS and request recommended
    warehouse locations from the Intelligent Slotting agent.
@endsection

@section('endpoint', $endpoint ?? '')

@section('content')
    @php
        $items = old('items', $items);
    @endphp

    <form
        class="panel"
        method="post"
        action="{{ route('slotting') }}"
        data-busy="Asking the agent..."
    >
        @csrf

        <div class="panel-head">
            <span class="panel-title">Request</span>
            <span class="panel-note">Items to slot</span>
        </div>

        <div class="panel-body">
            @if (empty($products))
                <p class="empty">
                    No products are available in the Core WMS product catalogue.
                </p>
            @else
                <div class="scroller">
                    <table id="items">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Client</th>
                                <th>Category</th>
                                <th class="num">Quantity</th>
                                <th>UOM</th>
                                <th>Expiry date (optional)</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($items as $index => $item)
                                @php
                                    $selectedKey =
                                        ($item['clientCode'] ?? '') . '|' .
                                        ($item['itemCode'] ?? '') . '|' .
                                        ($item['uom'] ?? '');

                                    $selectedProduct = collect($products)->first(
                                        fn ($product) =>
                                            $product['clientCode'] === ($item['clientCode'] ?? '')
                                            && $product['itemCode'] === ($item['itemCode'] ?? '')
                                            && $product['uom'] === ($item['uom'] ?? '')
                                    );
                                @endphp

                                <tr>
                                    <td>
                                        <select
                                            class="product-select"
                                            aria-label="Product"
                                        >
                                            @foreach ($products as $product)
                                                @php
                                                    $key =
                                                        $product['clientCode'] . '|' .
                                                        $product['itemCode'] . '|' .
                                                        $product['uom'];
                                                @endphp

                                                <option
                                                    value="{{ $key }}"
                                                    data-client="{{ $product['clientCode'] }}"
                                                    data-item="{{ $product['itemCode'] }}"
                                                    data-uom="{{ $product['uom'] }}"
                                                    data-category="{{ $product['category'] ?? '' }}"
                                                    @selected($key === $selectedKey)
                                                >
                                                    {{ $product['itemCode'] }}
                                                    · {{ $product['uom'] }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <input
                                            type="hidden"
                                            class="client-code"
                                            name="items[{{ $index }}][clientCode]"
                                            value="{{ $item['clientCode'] ?? '' }}"
                                        >

                                        <input
                                            type="hidden"
                                            class="item-code"
                                            name="items[{{ $index }}][itemCode]"
                                            value="{{ $item['itemCode'] ?? '' }}"
                                        >
                                    </td>

                                    <td class="client-display code">
                                        {{ $item['clientCode'] ?? '—' }}
                                    </td>

                                    <td class="category-display">
                                        {{ $selectedProduct['category'] ?? '—' }}
                                    </td>

                                    <td>
                                        <input
                                            name="items[{{ $index }}][quantity]"
                                            inputmode="decimal"
                                            value="{{ $item['quantity'] ?? '' }}"
                                            aria-label="Quantity"
                                            required
                                        >
                                    </td>

                                    <td>
                                        <span class="uom-display code">
                                            {{ $item['uom'] ?? '—' }}
                                        </span>

                                        <input
                                            type="hidden"
                                            class="uom"
                                            name="items[{{ $index }}][uom]"
                                            value="{{ $item['uom'] ?? '' }}"
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
            @endif
        </div>
    </form>

    @if (!empty($contract['rules']))
        <section class="panel">
            <div class="panel-head">
                <span class="panel-title">
                    Current IWMS Input Contract
                </span>

                <span class="panel-note">
                    Retrieved from Agent Manager
                </span>
            </div>

            <div class="panel-body">
                <div class="scroller">
                    <table>
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Rules</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($contract['rules'] as $field => $rules)
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
            </div>
        </section>
    @endif

    <section class="panel">
        <div class="panel-head">
            <span class="panel-title">Response</span>

            @isset($status)
                <span class="status {{ $status === 200 ? 'is-ok' : 'is-bad' }}">
                    <span
                        class="status-dot"
                        aria-hidden="true"
                    ></span>

                    {{ $status }}
                    {{ $status === 200 ? 'OK' : 'Error' }}

                    <span class="status-sep">·</span>

                    {{ number_format($elapsed ?? 0, 2) }}s
                </span>
            @endisset
        </div>

        @isset($result)
            @php
                $locations = $result['slottingLocations'] ?? [];
                $unallocated = $result['unallocated'] ?? [];
            @endphp

            @if (!empty($locations))
                <div class="scroller">
                    <table>
                        <thead>
                            <tr>
                                <th class="num">Rank</th>
                                <th>Item code</th>
                                <th>Location</th>
                                <th class="num">Quantity</th>
                                <th>UOM</th>
                                <th>Reasons</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($locations as $location)
                                <tr>
                                    <td class="num code">
                                        {{ $location['rank'] ?? '—' }}
                                    </td>

                                    <td class="code">
                                        {{ $location['itemCode'] ?? '—' }}
                                    </td>

                                    <td>
                                        @if (!empty($location['location']))
                                            <x-thermal
                                                :code="$location['location']"
                                            />
                                        @else
                                            —
                                        @endif
                                    </td>

                                    <td class="num code">
                                        {{ $location['quantity'] ?? '—' }}
                                    </td>

                                    <td class="code">
                                        {{ $location['uom'] ?? '—' }}
                                    </td>

                                    <td>
                                        @if (!empty($location['reasons']))
                                            {{ implode(' ', $location['reasons']) }}
                                        @else
                                            —
                                        @endif
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

            @if (!empty($unallocated))
                <div class="panel-body">
                    <strong>Unallocated items</strong>

                    <div class="scroller">
                        <table>
                            <thead>
                                <tr>
                                    <th>Client code</th>
                                    <th>Item code</th>
                                    <th class="num">Quantity</th>
                                    <th>UOM</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($unallocated as $item)
                                    <tr>
                                        <td class="code">
                                            {{ $item['clientCode'] ?? '—' }}
                                        </td>

                                        <td class="code">
                                            {{ $item['itemCode'] ?? '—' }}
                                        </td>

                                        <td class="num code">
                                            {{ $item['quantity'] ?? '—' }}
                                        </td>

                                        <td class="code">
                                            {{ $item['uom'] ?? '—' }}
                                        </td>

                                        <td>
                                            {{ $item['reason'] ?? '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @else
            <p class="empty">
                Send a request to see where the agent would slot the stock.
            </p>
        @endisset

        @isset($error)
            @if ($error)
                <p class="empty">
                    {{ $error }}
                </p>
            @endif
        @endisset
    </section>
@endsection

@push('scripts')
    <script>
        const itemRows = document.querySelector('#items tbody');
        const addItemButton = document.getElementById('add-item');

        function updateProduct(row) {
            const select = row.querySelector('.product-select');
            const option = select?.options[select.selectedIndex];

            if (!option) {
                return;
            }

            row.querySelector('.client-code').value =
                option.dataset.client ?? '';

            row.querySelector('.item-code').value =
                option.dataset.item ?? '';

            row.querySelector('.uom').value =
                option.dataset.uom ?? '';

            row.querySelector('.client-display').textContent =
                option.dataset.client ?? '—';

            row.querySelector('.uom-display').textContent =
                option.dataset.uom ?? '—';

            row.querySelector('.category-display').textContent =
                option.dataset.category ?? '—';
        }

        function renumberRows() {
            if (!itemRows) {
                return;
            }

            [...itemRows.children].forEach((row, index) => {
                row.querySelectorAll('[name]').forEach((field) => {
                    field.name = field.name.replace(
                        /\[\d+\]/,
                        '[' + index + ']'
                    );
                });
            });
        }

        if (itemRows) {
            itemRows.addEventListener('change', (event) => {
                if (
                    !event.target.classList.contains('product-select')
                ) {
                    return;
                }

                updateProduct(event.target.closest('tr'));
            });

            itemRows.addEventListener('click', (event) => {
                if (!event.target.classList.contains('remove')) {
                    return;
                }

                if (itemRows.children.length <= 1) {
                    return;
                }

                event.target.closest('tr').remove();

                renumberRows();
            });

            [...itemRows.children].forEach((row) => {
                updateProduct(row);
            });
        }

        if (addItemButton && itemRows) {
            addItemButton.addEventListener('click', () => {
                const source = itemRows.lastElementChild;

                if (!source) {
                    return;
                }

                const row = source.cloneNode(true);

                row.querySelector('.product-select').selectedIndex = 0;

                row.querySelector(
                    '[name$="[quantity]"]'
                ).value = '1';

                row.querySelector(
                    '[name$="[expiryDate]"]'
                ).value = '';

                itemRows.append(row);

                renumberRows();
                updateProduct(row);

                row.querySelector('.product-select').focus();
            });
        }
    </script>
@endpush