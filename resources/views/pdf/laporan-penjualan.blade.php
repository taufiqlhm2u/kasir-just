<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penjualan</title>
    <style>
        @page {
            margin: 25px 30px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #27272a;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #b45309;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header-title {
            font-size: 18px;
            font-weight: bold;
            color: #78350f;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .header-subtitle {
            font-size: 12px;
            color: #52525b;
            margin-top: 3px;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 15px;
        }
        .meta-table td {
            vertical-align: top;
        }
        .meta-right {
            text-align: right;
            color: #71717a;
            font-size: 10px;
        }

        /* Summary Cards Grid */
        .summary-box {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: separate;
            border-spacing: 8px;
        }
        .card {
            background-color: #fcfbf9;
            border: 1px solid #e7e5e4;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
        }
        .card-main {
            background-color: #78350f;
            color: #ffffff;
            border: 1px solid #451a03;
        }
        .card-title {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #78716c;
            margin-bottom: 4px;
            font-weight: bold;
        }
        .card-main .card-title {
            color: #fde68a;
        }
        .card-value {
            font-size: 15px;
            font-weight: bold;
            color: #1c1917;
        }
        .card-main .card-value {
            color: #ffffff;
            font-size: 18px;
        }
        .card-sub {
            font-size: 9px;
            color: #a8a29e;
            margin-top: 2px;
        }
        .card-main .card-sub {
            color: #fef3c7;
        }

        /* Table */
        .table-title {
            font-size: 12px;
            font-weight: bold;
            color: #27272a;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.data-table th {
            background-color: #f5f5f4;
            color: #44403c;
            border-top: 1px solid #d6d3d1;
            border-bottom: 1px solid #d6d3d1;
            padding: 6px 8px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: bold;
        }
        table.data-table td {
            border-bottom: 1px solid #e7e5e4;
            padding: 7px 8px;
            vertical-align: top;
            font-size: 10px;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-cash {
            background-color: #fef3c7;
            color: #92400e;
        }
        .badge-qris {
            background-color: #e0f2fe;
            color: #075985;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .font-bold {
            font-weight: bold;
        }
        .item-list {
            margin: 0;
            padding-left: 12px;
            color: #52525b;
        }
        .empty-state {
            padding: 30px;
            text-align: center;
            color: #a1a1aa;
            font-style: italic;
        }
        .footer {
            margin-top: 25px;
            padding-top: 8px;
            border-top: 1px solid #e7e5e4;
            font-size: 9px;
            color: #a1a1aa;
            text-align: center;
        }
    </style>
</head>
<body>

    <table class="meta-table">
        <tr>
            <td>
                <h1 class="header-title">KASIR JUST</h1>
                <div class="header-subtitle">Laporan Penjualan</div>
            </td>
            <td class="meta-right">
                <div>Periode: <strong>{{ \Carbon\Carbon::parse($dateFrom)->isoFormat('D MMM Y') }} – {{ \Carbon\Carbon::parse($dateTo)->isoFormat('D MMM Y') }}</strong></div>
                @if($filterMethod)
                <div>Metode Bayar: <strong>{{ strtoupper($filterMethod) }}</strong></div>
                @endif
                <div>Dicetak: {{ $printedAt }}</div>
            </td>
        </tr>
    </table>

    {{-- Ringkasan Laporan --}}
    <table class="summary-box">
        <tr>
            <td width="28%" class="card card-main">
                <div class="card-title">Total Penjualan</div>
                <div class="card-value">Rp {{ number_format($totalOmset, 0, ',', '.') }}</div>
                <div class="card-sub">{{ $totalTransaksi }} Transaksi</div>
            </td>
            <td width="18%" class="card">
                <div class="card-title">Total Cup</div>
                <div class="card-value">{{ number_format($totalCups, 0, ',', '.') }}</div>
                <div class="card-sub">Cup Terjual</div>
            </td>
            <td width="18%" class="card">
                <div class="card-title">Total Shot</div>
                <div class="card-value">{{ number_format($totalShots, 0, ',', '.') }}</div>
                <div class="card-sub">Shot Terjual</div>
            </td>
            <td width="18%" class="card">
                <div class="card-title">Tunai</div>
                <div class="card-value">Rp {{ number_format($totalCash, 0, ',', '.') }}</div>
            </td>
            <td width="18%" class="card">
                <div class="card-title">QRIS</div>
                <div class="card-value">Rp {{ number_format($totalQris, 0, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    {{-- Tabel Transaksi --}}
    <div class="table-title">Daftar Transaksi</div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="5%">#</th>
                <th width="18%">No. Invoice</th>
                <th width="15%">Waktu</th>
                <th width="12%">Metode</th>
                <th width="35%">Item Produk</th>
                <th width="15%" class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $index => $order)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="font-bold">{{ $order->invoice_no }}</td>
                <td>{{ $order->created_at->isoFormat('D MMM Y, HH:mm') }}</td>
                <td>
                    @if($order->payment_method === 'qris')
                        <span class="badge badge-qris">QRIS</span>
                    @else
                        <span class="badge badge-cash">Tunai</span>
                    @endif
                </td>
                <td>
                    <ul class="item-list">
                        @foreach($order->items as $item)
                        <li>
                            {{ $item->product_name }}
                            @if(($item->shots_count ?? 0) > 0)
                                <strong>(+{{ $item->shots_count }} Shot)</strong>
                            @endif
                            ({{ $item->quantity }}x @ Rp {{ number_format($item->price + (($item->shots_count ?? 0) * ($item->shot_price ?? 0)), 0, ',', '.') }})
                        </li>
                        @endforeach
                    </ul>
                </td>
                <td class="text-right font-bold">
                    Rp {{ number_format($order->total_amount, 0, ',', '.') }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="empty-state">Tidak ada transaksi pada periode ini.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Dokumen ini dihasilkan secara otomatis oleh sistem Kasir Just pada {{ $printedAt }}.
    </div>

</body>
</html>
