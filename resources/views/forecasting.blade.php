@extends('layouts.agent')

@section('heading', 'Demand Forecasting')

@section('lede')
    Give the agent an item and a horizon; it answers with one forecast point per week
    inside that horizon, and flags the item when stock will not cover the demand.
@endsection

@section('endpoint', $endpoint ?? '')

@section('content')
    <form class="panel" method="post" action="{{ route('forecasting') }}" data-busy="Forecasting…">
        @csrf
        <div class="panel-head">
            <span class="panel-title">Request</span>
            <span class="panel-note">One item, up to 90 days ahead</span>
        </div>
        <div class="panel-body">
            <div class="fields">
                <div class="field">
                    <label for="clientCode">Client code</label>
                    <input id="clientCode" name="clientCode" value="{{ old('clientCode', $query['clientCode']) }}">
                </div>
                <div class="field">
                    <label for="itemCode">Item code</label>
                    <input id="itemCode" name="itemCode" value="{{ old('itemCode', $query['itemCode']) }}">
                </div>
                <div class="field">
                    <label for="forecastDays">Forecast days</label>
                    <input id="forecastDays" name="forecastDays" inputmode="numeric" value="{{ old('forecastDays', $query['forecastDays']) }}">
                    <span class="field-hint">1 to 90</span>
                </div>
            </div>
            <div class="actions">
                <button class="primary" type="submit">Forecast demand</button>
            </div>
        </div>
    </form>

    <section class="panel">
        <div class="panel-head">
            <span class="panel-title">Response</span>
            @isset($result)
                <span class="panel-note">{{ $result['forecastFromDate'] }} → {{ $result['forecastToDate'] }}</span>
            @endisset
            @isset($status)
                <span class="status {{ $status === 200 ? 'is-ok' : 'is-bad' }}">
                    <span class="status-dot" aria-hidden="true"></span>
                    {{ $status }} {{ $status === 200 ? 'OK' : 'Error' }}
                    <span class="status-sep">·</span>{{ number_format($elapsed ?? 0, 2) }}s
                </span>
            @endisset
        </div>

        @isset($result)
            @if ($result['reorderAlert'])
                <div style="padding:1.15rem 1.15rem 0">
                    <div class="notice warn" style="margin-bottom:0">
                        <span class="notice-label">{{ $result['reorderAlert']['severity'] }}</span>
                        <div class="notice-body">{{ $result['reorderAlert']['reason'] }}</div>
                    </div>
                </div>
            @endif

            @if ($result['reorderActions'] ?? [])
                <div class="scroller">
                    <table>
                        <thead>
                        <tr>
                            <th class="num">Reorder quantity</th>
                            <th>UOM</th>
                            <th>Reorder date</th>
                            <th>Reasons</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($result['reorderActions'] as $action)
                            <tr>
                                <td class="num code">{{ $action['quantity'] }}</td>
                                <td class="code">{{ $action['uom'] }}</td>
                                <td class="code">{{ $action['date'] }}</td>
                                <td>
                                    <ul class="reasons">
                                        @foreach ($action['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($result['forecastPoints'])
                <div class="scroller">
                    <table>
                        <thead>
                        <tr>
                            <th>Forecast as at</th>
                            <th class="num">Expected demand</th>
                            <th>UOM</th>
                            <th>Reasons</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($result['forecastPoints'] as $point)
                            <tr>
                                <td class="code">{{ $point['forecastAsAtDate'] }}</td>
                                <td class="num code">{{ $point['expectedDemandQuantity'] }}</td>
                                <td class="code">{{ $point['uom'] }}</td>
                                <td>
                                    <ul class="reasons">
                                        @foreach ($point['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach
                                    </ul>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="empty">The agent returned no forecast points inside this horizon.</p>
            @endif
        @else
            <p class="empty">Send a request to see the forecast.</p>
        @endisset
    </section>
@endsection
