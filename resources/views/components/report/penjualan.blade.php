<?php

use App\Models\Order;
use Livewire\Component;
use Livewire\Attributes\Computed;

new class extends Component {
    public string $dateFrom    = '';
    public string $dateTo      = '';
    public string $filterMethod = ''; // '', 'cash', 'qris'
    public int|null $expandedId = null;

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo   = now()->format('Y-m-d');
    }

    #[Computed]
    public function orders(): \Illuminate\Database\Eloquent\Collection
    {
        return Order::with('items')
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo,   fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->when($this->filterMethod, fn ($q) => $q->where('payment_method', $this->filterMethod))
            ->latest()
            ->get();
    }

    #[Computed]
    public function totalOmset(): float
    {
        return (float) $this->orders->sum('total_amount');
    }

    #[Computed]
    public function totalTransaksi(): int
    {
        return $this->orders->count();
    }

    #[Computed]
    public function totalCups(): int
    {
        return (int) $this->orders->sum(fn ($order) => $order->items->sum('quantity'));
    }

    #[Computed]
    public function totalShots(): int
    {
        return (int) $this->orders->sum(fn ($order) => $order->items->sum(fn ($item) => ($item->shots_count ?? 0) * $item->quantity));
    }

    #[Computed]
    public function totalCash(): float
    {
        return (float) $this->orders->where('payment_method', 'cash')->sum('total_amount');
    }

    #[Computed]
    public function totalQris(): float
    {
        return (float) $this->orders->where('payment_method', 'qris')->sum('total_amount');
    }

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function resetFilter(): void
    {
        $this->dateFrom     = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo       = now()->format('Y-m-d');
        $this->filterMethod = '';
        unset($this->orders);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('components.report.penjualan');
    }
};
?>

<div>
    <header class="sticky top-0 z-20 bg-white/95 border-b border-stone-200 px-4 py-3.5 flex items-center justify-between backdrop-blur-md">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-xl text-amber-700">bar_chart</span>
            <div>
                <h1 class="text-base font-bold text-stone-900">Laporan Penjualan</h1>
                <p class="text-xs text-stone-500">{{ \Carbon\Carbon::parse($dateFrom)->isoFormat('D MMM') }} – {{ \Carbon\Carbon::parse($dateTo)->isoFormat('D MMM Y') }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('laporan.pdf', ['date_from' => $dateFrom, 'date_to' => $dateTo, 'filter_method' => $filterMethod, '_t' => time()]) }}"
               target="_blank"
               id="btn-cetak-pdf"
               class="inline-flex items-center justify-center gap-1.5 min-h-[32px] px-3 py-1 rounded-lg font-semibold text-xs border border-amber-700 bg-amber-700 hover:bg-amber-800 text-white transition-all select-none active:scale-95 shadow-xs">
                <span class="material-symbols-outlined text-base">picture_as_pdf</span>
                <span>Cetak PDF</span>
            </a>
            <button wire:click="resetFilter" class="inline-flex items-center justify-center gap-1 min-h-[32px] px-2.5 py-1 rounded-lg font-semibold text-xs border border-stone-300 bg-stone-100 hover:bg-stone-200 text-stone-700 transition-all select-none active:scale-95" id="btn-reset-filter">Reset</button>
        </div>
    </header>

    {{-- ===== FILTERS ===== --}}
    <div class="px-4 pt-4 pb-3 flex flex-col gap-3 border-b border-stone-200">
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-stone-500 mb-1.5 uppercase tracking-wider" for="date-from">Dari Tanggal</label>
                <input id="date-from" type="date" wire:model.live="dateFrom" class="w-full min-h-[44px] px-3.5 py-2.5 bg-white border border-stone-300 rounded-xl text-stone-900 text-sm font-sans outline-hidden transition-all placeholder:text-stone-400 focus:border-amber-700 focus:ring-2 focus:ring-amber-700/20">
            </div>
            <div>
                <label class="block text-xs font-semibold text-stone-500 mb-1.5 uppercase tracking-wider" for="date-to">Sampai Tanggal</label>
                <input id="date-to" type="date" wire:model.live="dateTo" class="w-full min-h-[44px] px-3.5 py-2.5 bg-white border border-stone-300 rounded-xl text-stone-900 text-sm font-sans outline-hidden transition-all placeholder:text-stone-400 focus:border-amber-700 focus:ring-2 focus:ring-amber-700/20">
            </div>
        </div>

        {{-- Filter Metode Bayar --}}
        <div class="flex gap-2">
            <button wire:click="$set('filterMethod', '')"
                    class="inline-flex items-center px-4 py-1.5 rounded-full text-xs whitespace-nowrap cursor-pointer transition-all border {{ $filterMethod === '' ? 'border-amber-700 bg-amber-700 text-white font-semibold shadow-xs' : 'border-stone-300 bg-white text-stone-600 font-medium active:scale-95' }}" id="filter-all">Semua</button>
            <button wire:click="$set('filterMethod', 'cash')"
                    class="inline-flex items-center gap-1 px-4 py-1.5 rounded-full text-xs whitespace-nowrap cursor-pointer transition-all border {{ $filterMethod === 'cash' ? 'border-amber-700 bg-amber-700 text-white font-semibold shadow-xs' : 'border-stone-300 bg-white text-stone-600 font-medium active:scale-95' }}" id="filter-cash">
                <span class="material-symbols-outlined text-sm">payments</span>
                <span>Tunai</span>
            </button>
            <button wire:click="$set('filterMethod', 'qris')"
                    class="inline-flex items-center gap-1 px-4 py-1.5 rounded-full text-xs whitespace-nowrap cursor-pointer transition-all border {{ $filterMethod === 'qris' ? 'border-amber-700 bg-amber-700 text-white font-semibold shadow-xs' : 'border-stone-300 bg-white text-stone-600 font-medium active:scale-95' }}" id="filter-qris">
                <span class="material-symbols-outlined text-sm">qr_code_scanner</span>
                <span>QRIS</span>
            </button>
        </div>
    </div>

    {{-- ===== METRIC CARDS ===== --}}
    <div class="px-4 py-4 grid grid-cols-2 gap-3">
        {{-- Omset --}}
        <div class="col-span-2 bg-gradient-to-br from-amber-700 to-amber-900 border border-amber-800 text-white shadow-md rounded-2xl p-4 text-center">
            <p class="text-xs font-semibold mb-1 text-amber-200">Total Penjualan</p>
            <p class="text-2xl font-bold text-white">Rp {{ number_format($this->totalOmset, 0, ',', '.') }}</p>
            <p class="text-xs mt-1 text-amber-100/90">{{ $this->totalTransaksi }} transaksi</p>
        </div>

        {{-- Total Cup & Total Shot Terjual --}}
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-3 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-amber-700 text-white flex items-center justify-center shrink-0 shadow-xs">
                    <span class="material-symbols-outlined text-lg">local_cafe</span>
                </div>
                <div>
                    <p class="text-xs font-semibold text-amber-900">Total Cup</p>
                    <p class="text-[10px] text-amber-700">Cup Terjual</p>
                </div>
            </div>
            <p class="text-lg font-extrabold text-amber-900">{{ number_format($this->totalCups, 0, ',', '.') }}</p>
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-3 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-amber-800 text-white flex items-center justify-center shrink-0 shadow-xs">
                    <span class="material-symbols-outlined text-lg">coffee</span>
                </div>
                <div>
                    <p class="text-xs font-semibold text-amber-900">Total Shot</p>
                    <p class="text-[10px] text-amber-700">Shot Terjual</p>
                </div>
            </div>
            <p class="text-lg font-extrabold text-amber-900">{{ number_format($this->totalShots, 0, ',', '.') }}</p>
        </div>

        {{-- Cash vs QRIS --}}
        <div class="bg-white border border-stone-200 rounded-2xl p-4 text-center shadow-xs">
            <p class="text-xs font-semibold mb-1 text-stone-500 flex items-center justify-center gap-1">
                <span class="material-symbols-outlined text-sm">payments</span>
                <span>Tunai</span>
            </p>
            <p class="text-base font-bold text-stone-900">Rp {{ number_format($this->totalCash, 0, ',', '.') }}</p>
        </div>
        <div class="bg-white border border-stone-200 rounded-2xl p-4 text-center shadow-xs">
            <p class="text-xs font-semibold mb-1 text-stone-500 flex items-center justify-center gap-1">
                <span class="material-symbols-outlined text-sm">qr_code_scanner</span>
                <span>QRIS</span>
            </p>
            <p class="text-base font-bold text-stone-900">Rp {{ number_format($this->totalQris, 0, ',', '.') }}</p>
        </div>
    </div>

    {{-- ===== TRANSACTION LIST ===== --}}
    <div class="px-4 pb-24">
        <p class="text-xs font-semibold mb-3 text-stone-500 uppercase tracking-wider">
            Riwayat Transaksi
        </p>

        @forelse($this->orders as $order)
        <div wire:key="order-{{ $order->id }}" class="mb-3">
            {{-- Transaction Header (Accordion Toggle) --}}
            <button wire:click="toggleExpand({{ $order->id }})"
                    id="order-row-{{ $order->id }}"
                    class="w-full bg-white border border-stone-200 rounded-2xl p-4 shadow-xs text-left transition-all {{ $expandedId === $order->id ? 'border-amber-700 rounded-b-none' : '' }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-bold text-sm text-stone-900">{{ $order->invoice_no }}</span>
                            {{-- Payment Method Badge --}}
                            @if($order->payment_method === 'qris')
                            <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full font-semibold bg-sky-50 text-sky-700 border border-sky-200">
                                <span class="material-symbols-outlined text-xs">qr_code_scanner</span>
                                <span>QRIS</span>
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                                <span class="material-symbols-outlined text-xs">payments</span>
                                <span>Tunai</span>
                            </span>
                            @endif
                        </div>
                        <p class="text-xs text-stone-500">
                            {{ $order->created_at->isoFormat('D MMM Y, HH:mm') }}
                            · {{ $order->items->sum('quantity') }} item
                        </p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="font-bold text-amber-700">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</p>
                        <p class="text-xs {{ $expandedId === $order->id ? 'text-amber-700 font-semibold' : 'text-stone-400' }}">
                            {{ $expandedId === $order->id ? '▲ tutup' : '▼ lihat' }}
                        </p>
                    </div>
                </div>
            </button>

            {{-- Accordion Detail --}}
            @if($expandedId === $order->id)
            <div id="order-detail-{{ $order->id }}"
                 class="p-4 rounded-b-2xl bg-stone-50 border border-amber-700 border-t-0">

                {{-- Items --}}
                <div class="mb-3">
                    @foreach($order->items as $item)
                    <div class="flex justify-between text-sm py-1.5 border-b border-stone-200">
                        <div>
                            <p class="font-medium text-stone-900">
                                {{ $item->product_name }}
                                @if(($item->shots_count ?? 0) > 0)
                                    <span class="text-xs text-amber-800 font-semibold">(+{{ $item->shots_count }} Shot)</span>
                                @endif
                            </p>
                            <p class="text-xs text-stone-500">
                                {{ $item->quantity }} × Rp {{ number_format($item->price + (($item->shots_count ?? 0) * ($item->shot_price ?? 0)), 0, ',', '.') }}
                            </p>
                        </div>
                        <p class="font-semibold text-right text-stone-900">Rp {{ number_format($item->subtotal, 0, ',', '.') }}</p>
                    </div>
                    @endforeach
                </div>

                {{-- Financials --}}
                <div class="grid grid-cols-2 gap-2 text-sm mb-3">
                    <div class="p-2.5 rounded-xl col-span-2 bg-white border border-stone-200">
                        <p class="text-xs text-stone-500">Total Tagihan</p>
                        <p class="font-bold text-base text-amber-700">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</p>
                    </div>
                    @if($order->payment_method === 'cash')
                    <div class="p-2.5 rounded-xl bg-white border border-stone-200">
                        <p class="text-xs text-stone-500">Bayar</p>
                        <p class="font-semibold text-stone-900">Rp {{ number_format($order->paid_amount, 0, ',', '.') }}</p>
                    </div>
                    <div class="p-2.5 rounded-xl bg-white border border-stone-200">
                        <p class="text-xs text-stone-500">Kembalian</p>
                        <p class="font-semibold text-emerald-700">Rp {{ number_format($order->change_amount, 0, ',', '.') }}</p>
                    </div>
                    @endif
                </div>

                {{-- QRIS Payment Proof --}}
                @if($order->payment_method === 'qris' && $order->payment_proof)
                <div>
                    <p class="block text-xs font-semibold text-stone-500 mb-1.5 uppercase tracking-wider">Bukti Pembayaran QRIS</p>
                    <a href="{{ asset('storage/' . $order->payment_proof) }}" target="_blank" rel="noopener noreferrer">
                        <img src="{{ asset('storage/' . $order->payment_proof) }}"
                             alt="Bukti QRIS {{ $order->invoice_no }}"
                             class="w-full rounded-xl object-contain border border-stone-200"
                             style="max-height: 220px;"
                             loading="lazy">
                        <p class="text-xs text-center mt-1 text-amber-700 underline font-medium">Tap untuk lihat penuh ↗</p>
                    </a>
                </div>
                @elseif($order->payment_method === 'qris' && !$order->payment_proof)
                <div class="p-3 rounded-xl text-center bg-rose-50 border border-rose-200 flex items-center justify-center gap-1.5">
                    <span class="material-symbols-outlined text-base text-rose-600">warning</span>
                    <p class="text-xs text-rose-700 font-medium">Bukti QRIS tidak tersedia</p>
                </div>
                @endif
            </div>
            @endif
        </div>
        @empty
        <div class="py-16 text-center text-stone-400 flex flex-col items-center">
            <span class="material-symbols-outlined text-5xl mb-3 opacity-40">query_stats</span>
            <p class="font-medium text-stone-500">Tidak ada transaksi</p>
            <p class="text-sm mt-1 text-stone-400">Coba ubah filter tanggal atau metode pembayaran</p>
        </div>
        @endforelse
    </div>
</div>