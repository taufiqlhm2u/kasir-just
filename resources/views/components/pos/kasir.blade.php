<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public string $search = '';
    public int|null $categoryFilter = null;

    // Cart: array of ['product_id', 'name', 'price', 'cost_price', 'qty', 'subtotal', 'image', 'stock']
    public array $cart = [];

    // Modals
    public bool $showCart     = false;
    public bool $showCheckout = false;
    public bool $showSuccess  = false;

    // Checkout
    public string $paymentMethod  = 'cash';
    public string $paidAmountRaw  = '';
    public mixed  $paymentProof   = null;

    // Error message
    public string $checkoutError = '';

    // Success / receipt data
    public array $lastOrder = [];

    #[Computed]
    public function categories(): \Illuminate\Database\Eloquent\Collection
    {
        return Category::orderBy('name')->get();
    }

    #[Computed]
    public function products(): \Illuminate\Database\Eloquent\Collection
    {
        return Product::with('category')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->orderBy('name')
            ->get();
    }

    public function addToCart(int $productId): void
    {
        $product = Product::find($productId);

        if (! $product || $product->stock <= 0) {
            return;
        }

        $existing = collect($this->cart)->search(fn ($item) => $item['product_id'] === $productId);

        if ($existing !== false) {
            if ($this->cart[$existing]['qty'] >= $product->stock) {
                return;
            }
            $this->cart[$existing]['qty']++;
            $this->cart[$existing]['subtotal'] = $this->cart[$existing]['qty'] * $this->cart[$existing]['price'];
        } else {
            $this->cart[] = [
                'product_id' => $product->id,
                'name'       => $product->name,
                'price'      => (float) $product->price,
                'qty'        => 1,
                'subtotal'   => (float) $product->price,
                'image'      => $product->image_url,
                'stock'      => $product->stock,
            ];
        }
    }

    public function incrementQty(int $index): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }

        $product = Product::find($this->cart[$index]['product_id']);

        if ($product && $this->cart[$index]['qty'] < $product->stock) {
            $this->cart[$index]['qty']++;
            $this->cart[$index]['subtotal'] = $this->cart[$index]['qty'] * $this->cart[$index]['price'];
        }
    }

    public function decrementQty(int $index): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }

        if ($this->cart[$index]['qty'] > 1) {
            $this->cart[$index]['qty']--;
            $this->cart[$index]['subtotal'] = $this->cart[$index]['qty'] * $this->cart[$index]['price'];
        } else {
            $this->removeFromCart($index);
        }
    }

    public function removeFromCart(int $index): void
    {
        array_splice($this->cart, $index, 1);
        $this->cart = array_values($this->cart);
    }

    public function getCartTotalProperty(): float
    {
        return collect($this->cart)->sum('subtotal');
    }

    public function getCartCountProperty(): int
    {
        return collect($this->cart)->sum('qty');
    }

    public function getPaidAmountProperty(): float
    {
        return (float) str_replace(['.', ','], ['', '.'], $this->paidAmountRaw);
    }

    public function getChangeAmountProperty(): float
    {
        return max(0, $this->paid_amount - $this->cartTotal);
    }

    public function updatedPaymentMethod(): void
    {
        // Reset proof and paid amount when switching payment method
        $this->paymentProof  = null;
        $this->paidAmountRaw = '';
        $this->checkoutError = '';
    }

    public function checkout(): void
    {
        $this->checkoutError = '';

        if (empty($this->cart)) {
            return;
        }

        // Validate cash: paid amount must be sufficient
        if ($this->paymentMethod === 'cash' && $this->paid_amount < $this->cartTotal) {
            $this->checkoutError = 'Nominal bayar kurang dari total tagihan.';
            return;
        }

        // Validate QRIS: payment proof photo is required
        if ($this->paymentMethod === 'qris' && ! $this->paymentProof) {
            $this->checkoutError = 'Bukti pembayaran QRIS wajib diunggah.';
            return;
        }

        $proofPath = null;

        if ($this->paymentMethod === 'qris' && $this->paymentProof) {
            $proofPath = $this->paymentProof->store('payment-proofs', 'public');
        }

        DB::transaction(function () use ($proofPath) {
            $totalAmount  = $this->cartTotal;
            $paidAmount   = $this->paymentMethod === 'cash' ? $this->paid_amount : $totalAmount;
            $changeAmount = $this->paymentMethod === 'cash' ? max(0, $paidAmount - $totalAmount) : 0;
            $invoiceNo    = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            $order = Order::create([
                'invoice_no'     => $invoiceNo,
                'total_amount'   => $totalAmount,
                'paid_amount'    => $paidAmount,
                'change_amount'  => $changeAmount,
                'payment_method' => $this->paymentMethod,
                'payment_proof'  => $proofPath,
            ]);

            foreach ($this->cart as $item) {
                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_id'   => $item['product_id'],
                    'product_name' => $item['name'],
                    'price'        => $item['price'],
                    'quantity'     => $item['qty'],
                    'subtotal'     => $item['subtotal'],
                ]);

                Product::where('id', $item['product_id'])
                    ->decrement('stock', $item['qty']);
            }

            $this->lastOrder = [
                'invoice_no'     => $order->invoice_no,
                'items'          => $this->cart,
                'total_amount'   => $totalAmount,
                'paid_amount'    => $paidAmount,
                'change_amount'  => $changeAmount,
                'payment_method' => $this->paymentMethod,
                'payment_proof'  => $proofPath ? asset('storage/' . $proofPath) : null,
                'created_at'     => now()->format('d M Y, H:i'),
            ];
        });

        // Reset state
        $this->cart          = [];
        $this->paidAmountRaw = '';
        $this->paymentProof  = null;
        $this->showCheckout  = false;
        $this->showCart      = false;
        $this->showSuccess   = true;

        unset($this->products);
    }

    public function resetPos(): void
    {
        $this->cart          = [];
        $this->paidAmountRaw = '';
        $this->paymentMethod = 'cash';
        $this->paymentProof  = null;
        $this->checkoutError = '';
        $this->showSuccess   = false;
        $this->showCheckout  = false;
        $this->showCart      = false;
        $this->lastOrder     = [];
        $this->search        = '';
        $this->categoryFilter = null;

        unset($this->products);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('components.pos.kasir');
    }
};
?>

<div x-effect="document.body.style.overflow = ($wire.showCart || $wire.showCheckout || $wire.showSuccess) ? 'hidden' : ''">

    {{-- ===== PAGE HEADER ===== --}}
    <header class="sticky top-0 z-20 bg-white/95 border-b border-stone-200 px-4 py-3.5 flex items-center justify-between backdrop-blur-md">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-xl text-amber-700">coffee</span>
            <div>
                <h1 class="text-base font-bold text-stone-900">Kasir Just</h1>
                <p class="text-xs text-stone-500">{{ now()->isoFormat('dddd, D MMMM Y') }}</p>
            </div>
        </div>
        @if(count($cart) > 0)
        <span class="bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-1 rounded-full text-xs font-semibold">{{ $this->cartCount }} item di keranjang</span>
        @endif
    </header>

    {{-- ===== SEARCH & FILTER ===== --}}
    <div class="sticky top-16 z-10 px-4 py-3 bg-stone-100 border-b border-stone-200">
        <div class="relative mb-3">
            <span class="material-symbols-outlined text-base absolute left-3 top-1/2 -translate-y-1/2 text-stone-400">search</span>
            <input id="kasir-search" type="search" wire:model.live.debounce.300ms="search"
                   placeholder="Cari produk..." class="w-full min-h-[44px] px-3.5 py-2.5 pl-10 bg-white border border-stone-300 rounded-xl text-stone-900 text-sm font-sans outline-hidden transition-all placeholder:text-stone-400 focus:border-amber-700 focus:ring-2 focus:ring-amber-700/20" autocomplete="off">
        </div>

        <div class="flex gap-2 overflow-x-auto pb-1" style="scrollbar-width: none;">
            <button id="pill-all" wire:click="$set('categoryFilter', null)"
                    class="inline-flex items-center px-4 py-1.5 rounded-full text-xs whitespace-nowrap cursor-pointer transition-all border {{ $categoryFilter === null ? 'border-amber-700 bg-amber-700 text-white font-semibold shadow-xs' : 'border-stone-300 bg-white text-stone-600 font-medium active:scale-95' }}">Semua</button>
            @foreach($this->categories as $cat)
            <button id="pill-cat-{{ $cat->id }}" wire:key="pill-{{ $cat->id }}"
                    wire:click="$set('categoryFilter', {{ $cat->id }})"
                    class="inline-flex items-center px-4 py-1.5 rounded-full text-xs whitespace-nowrap cursor-pointer transition-all border {{ $categoryFilter === $cat->id ? 'border-amber-700 bg-amber-700 text-white font-semibold shadow-xs' : 'border-stone-300 bg-white text-stone-600 font-medium active:scale-95' }}">{{ $cat->name }}</button>
            @endforeach
        </div>
    </div>

    {{-- ===== PRODUCT GRID ===== --}}
    <div class="px-4 py-4 grid grid-cols-2 gap-3 pb-36">
        @forelse($this->products as $product)
        <button wire:key="product-{{ $product->id }}"
                wire:click="addToCart({{ $product->id }})"
                id="product-{{ $product->id }}"
                class="text-left flex flex-col rounded-2xl overflow-hidden transition-all border {{ $product->stock <= 0 ? 'bg-stone-50 border-stone-200 opacity-60 cursor-not-allowed' : 'bg-white border-stone-200 shadow-xs hover:border-amber-600 hover:shadow-md active:scale-[0.98] cursor-pointer' }}"
                {{ $product->stock <= 0 ? 'disabled' : '' }}>
            <div class="relative overflow-hidden aspect-4/3">
                <img src="{{ $product->image_url }}" alt="{{ $product->name }}"
                     class="w-full h-full object-cover" loading="lazy"
                     onerror="this.src='https://placehold.co/400x300/f5f5f4/78716c?text={{ urlencode($product->name) }}'">
                @if($product->stock <= 0)
                <div class="absolute inset-0 flex items-center justify-center bg-black/60">
                    <span class="text-rose-400 font-bold text-xs">Habis</span>
                </div>
                @endif
                @php $cartItem = collect($cart)->firstWhere('product_id', $product->id); @endphp
                @if($cartItem)
                <div class="absolute top-2 right-2"><span class="bg-amber-700 text-white rounded-full px-2 py-0.5 text-xs font-bold">{{ $cartItem['qty'] }}</span></div>
                @endif
            </div>
            <div class="p-3">
                <p class="font-semibold text-sm leading-tight mb-1 text-stone-900">{{ $product->name }}</p>
                <p class="font-bold text-sm text-amber-700">Rp {{ number_format($product->price, 0, ',', '.') }}</p>
                <p class="text-xs mt-1 text-stone-500">Stok: {{ $product->stock }}</p>
            </div>
        </button>
        @empty
        <div class="col-span-2 py-16 text-center text-stone-400 flex flex-col items-center">
            <span class="material-symbols-outlined text-5xl mb-3 opacity-40">search_off</span>
            <p class="font-medium text-stone-500">Produk tidak ditemukan</p>
        </div>
        @endforelse
    </div>

    {{-- ===== FLOATING CART BUTTON ===== --}}
    @if(count($cart) > 0)
    <button id="btn-open-cart" wire:click="$set('showCart', true)" class="fixed bottom-18 left-1/2 -translate-x-1/2 z-30 bg-amber-700 hover:bg-amber-800 text-white px-6 py-3.5 rounded-full font-bold text-sm flex items-center justify-between gap-2.5 shadow-lg shadow-amber-900/20 cursor-pointer transition-all min-w-[200px] border-0 active:scale-[0.96]">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-xl">shopping_cart</span>
            <span>{{ $this->cartCount }} Item</span>
        </div>
        <span class="font-bold">Rp {{ number_format($this->cartTotal, 0, ',', '.') }}</span>
    </button>
    @endif

    {{-- ===== CART BOTTOM SHEET ===== --}}
    @if($showCart)
    <div class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-xs flex flex-col justify-end transition-opacity" wire:click.self="$set('showCart', false)">
        <div class="w-full max-w-lg bg-white rounded-t-3xl px-5 pt-2 pb-8 max-h-[90dvh] overflow-y-auto overscroll-contain shadow-xl mx-auto animate-[slideUp_0.25s_cubic-bezier(0.34,1.56,0.64,1)]" id="cart-sheet">
            <div class="w-9 h-1 bg-stone-300 rounded-full mx-auto mb-4"></div>
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold text-lg text-stone-900">Keranjang</h2>
                <button wire:click="$set('showCart', false)" class="inline-flex items-center justify-center min-w-[32px] min-h-[32px] p-1.5 rounded-lg font-semibold text-xs border border-stone-300 bg-stone-100 hover:bg-stone-200 text-stone-700 transition-all select-none active:scale-95" id="btn-close-cart">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>

            <div class="flex flex-col gap-3 mb-4 max-h-[50dvh] overflow-y-auto">
                @foreach($cart as $index => $item)
                <div wire:key="cart-item-{{ $index }}" class="flex items-center gap-3 p-3 rounded-xl bg-stone-100 border border-stone-200">
                    <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}"
                         class="w-12 h-12 rounded-lg object-cover shrink-0"
                         onerror="this.src='https://placehold.co/48x48/f5f5f4/78716c?text=?'">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm truncate text-stone-900">{{ $item['name'] }}</p>
                        <p class="text-xs text-amber-700 font-medium">Rp {{ number_format($item['price'], 0, ',', '.') }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <div class="flex items-center bg-stone-100 rounded-lg overflow-hidden border border-stone-200">
                            <button wire:click="decrementQty({{ $index }})" class="w-9 h-9 flex items-center justify-center bg-transparent border-0 cursor-pointer text-lg font-bold text-amber-700 hover:bg-amber-100/50 active:bg-amber-100 transition-colors" id="cart-dec-{{ $index }}">−</button>
                            <span class="min-w-[32px] text-center font-bold text-sm text-stone-800">{{ $item['qty'] }}</span>
                            <button wire:click="incrementQty({{ $index }})" class="w-9 h-9 flex items-center justify-center bg-transparent border-0 cursor-pointer text-lg font-bold text-amber-700 hover:bg-amber-100/50 active:bg-amber-100 transition-colors" id="cart-inc-{{ $index }}">+</button>
                        </div>
                        <button wire:click="removeFromCart({{ $index }})" class="inline-flex items-center justify-center min-w-[32px] min-h-[32px] p-1.5 rounded-lg font-semibold text-xs border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 transition-all select-none active:scale-95" id="cart-del-{{ $index }}">
                            <span class="material-symbols-outlined text-base">delete</span>
                        </button>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="flex justify-between items-center py-2 border-b border-stone-200 last:border-b-0 mb-4">
                <span class="text-stone-500">Total ({{ $this->cartCount }} item)</span>
                <span class="font-bold text-lg text-amber-700">Rp {{ number_format($this->cartTotal, 0, ',', '.') }}</span>
            </div>

            <button id="btn-checkout" wire:click="$set('showCheckout', true); $set('showCart', false)"
                    class="inline-flex items-center justify-center gap-1.5 min-h-[44px] px-5 py-3 rounded-xl font-semibold text-base cursor-pointer border-0 transition-all select-none whitespace-nowrap active:scale-[0.96] bg-amber-700 hover:bg-amber-800 text-white shadow-xs w-full">Bayar Sekarang</button>
        </div>
    </div>
    @endif

    {{-- ===== CHECKOUT MODAL ===== --}}
    @if($showCheckout)
    <div class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-xs flex flex-col justify-end transition-opacity" wire:click.self="$set('showCheckout', false)">
        <div class="w-full max-w-lg bg-white rounded-t-3xl px-5 pt-2 pb-8 max-h-[90dvh] overflow-y-auto overscroll-contain shadow-xl mx-auto animate-[slideUp_0.25s_cubic-bezier(0.34,1.56,0.64,1)]" id="checkout-sheet">
            <div class="w-9 h-1 bg-stone-300 rounded-full mx-auto mb-4"></div>
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-bold text-lg text-stone-900">Pembayaran</h2>
                <button wire:click="$set('showCheckout', false); $set('showCart', true)"
                        class="inline-flex items-center justify-center min-w-[32px] min-h-[32px] p-1.5 rounded-lg font-semibold text-xs border border-stone-300 bg-stone-100 hover:bg-stone-200 text-stone-700 transition-all select-none active:scale-95" id="btn-back-to-cart">
                    <span class="material-symbols-outlined text-base">chevron_left</span>
                </button>
            </div>

            {{-- Total Tagihan --}}
            <div class="p-4 rounded-xl mb-4 bg-stone-100 border border-stone-200">
                <p class="block text-xs font-semibold text-stone-500 mb-1.5 uppercase tracking-wider">Total Tagihan</p>
                <p class="text-2xl font-bold text-amber-700">Rp {{ number_format($this->cartTotal, 0, ',', '.') }}</p>
            </div>

            {{-- Error Banner --}}
            @if($checkoutError)
            <div class="p-3 rounded-xl mb-4 flex items-center gap-2 bg-rose-50 border border-rose-200">
                <span class="material-symbols-outlined text-base shrink-0 text-rose-600">warning</span>
                <span class="text-sm font-medium text-rose-700">{{ $checkoutError }}</span>
            </div>
            @endif

            {{-- Metode Pembayaran --}}
            <div class="mb-5">
                <label class="block text-xs font-semibold text-stone-500 mb-1.5 uppercase tracking-wider">Metode Pembayaran</label>
                <div class="grid grid-cols-2 gap-3">
                    {{-- Cash --}}
                    <button id="pay-cash" wire:click="$set('paymentMethod', 'cash')"
                            class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all
                                   {{ $paymentMethod === 'cash'
                                      ? 'border-amber-700 bg-amber-50/60'
                                      : 'border-stone-200 bg-white' }}">
                        <span class="material-symbols-outlined text-4xl {{ $paymentMethod === 'cash' ? 'text-amber-700' : 'text-stone-400' }}">payments</span>
                        <span class="text-sm font-semibold {{ $paymentMethod === 'cash' ? 'text-amber-700' : 'text-stone-600' }}">Tunai (Cash)</span>
                    </button>
                    {{-- QRIS --}}
                    <button id="pay-qris" wire:click="$set('paymentMethod', 'qris')"
                            class="flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all
                                   {{ $paymentMethod === 'qris'
                                      ? 'border-amber-700 bg-amber-50/60'
                                      : 'border-stone-200 bg-white' }}">
                        <span class="material-symbols-outlined text-4xl {{ $paymentMethod === 'qris' ? 'text-amber-700' : 'text-stone-400' }}">qr_code_scanner</span>
                        <span class="text-sm font-semibold {{ $paymentMethod === 'qris' ? 'text-amber-700' : 'text-stone-600' }}">QRIS</span>
                    </button>
                </div>
            </div>

            {{-- CASH: Input Nominal --}}
            @if($paymentMethod === 'cash')
            <div class="mb-4">
                <label for="paid-amount" class="block text-xs font-semibold text-stone-500 mb-1.5 uppercase tracking-wider">Uang Diterima</label>
                <input id="paid-amount" type="number"
                       wire:model.live="paidAmountRaw"
                       placeholder="Masukkan nominal..."
                       class="w-full min-h-[44px] px-3.5 py-2.5 bg-white border border-stone-300 rounded-xl text-stone-900 text-xl font-bold font-sans outline-hidden transition-all placeholder:text-stone-400 focus:border-amber-700 focus:ring-2 focus:ring-amber-700/20"
                       min="{{ (int) $this->cartTotal }}"
                       inputmode="numeric">

                {{-- Quick Amounts including 50K --}}
                <div class="flex gap-2 mt-2 overflow-x-auto pb-1" style="scrollbar-width: none;">
                    @foreach([10000, 20000, 50000, 100000] as $amount)
                    <button id="quick-{{ $amount }}"
                            type="button"
                            wire:click="$set('paidAmountRaw', '{{ $amount }}')"
                            class="inline-flex items-center px-3 py-1.5 rounded-full text-xs shrink-0 cursor-pointer transition-all border {{ (float)$paidAmountRaw === (float)$amount ? 'border-amber-700 bg-amber-700 text-white font-semibold shadow-xs' : 'border-stone-300 bg-white text-stone-600 font-medium active:scale-95' }}">
                        {{ number_format($amount / 1000, 0) }}K
                    </button>
                    @endforeach
                    <button id="quick-exact"
                            type="button"
                            wire:click="$set('paidAmountRaw', '{{ (int) $this->cartTotal }}')"
                            class="inline-flex items-center px-3 py-1.5 rounded-full text-xs shrink-0 cursor-pointer transition-all border {{ (float)$paidAmountRaw === $this->cartTotal ? 'border-amber-700 bg-amber-700 text-white font-semibold shadow-xs' : 'border-stone-300 bg-white text-stone-600 font-medium active:scale-95' }}">Pas</button>
                </div>

                @if($this->paidAmount >= $this->cartTotal)
                <div class="flex justify-between items-center mt-3 p-3 rounded-xl bg-emerald-50 border border-emerald-200">
                    <span class="text-sm font-medium text-emerald-700">Kembalian</span>
                    <span class="font-bold text-lg text-emerald-700">Rp {{ number_format($this->changeAmount, 0, ',', '.') }}</span>
                </div>
                @endif
            </div>
            @endif

            {{-- QRIS: Foto Bukti Pembayaran Langsung Kamera Depan --}}
            @if($paymentMethod === 'qris')
            <div class="mb-4" x-data="{
                stream: null,
                cameraActive: false,
                cameraError: '',
                async startCamera() {
                    this.cameraError = '';
                    try {
                        this.stream = await navigator.mediaDevices.getUserMedia({
                            video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } }
                        });
                        if ($refs.video) {
                            $refs.video.srcObject = this.stream;
                            await $refs.video.play();
                            this.cameraActive = true;
                        }
                    } catch (err) {
                        console.error('Camera error:', err);
                        this.cameraError = 'Gagal mengakses kamera depan: ' + (err.message || 'Izin kamera ditolak');
                    }
                },
                stopCamera() {
                    if (this.stream) {
                        this.stream.getTracks().forEach(track => track.stop());
                        this.stream = null;
                    }
                    this.cameraActive = false;
                },
                capturePhoto() {
                    if (! $refs.video) return;
                    const video = $refs.video;
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth || 640;
                    canvas.height = video.videoHeight || 480;
                    const ctx = canvas.getContext('2d');
                    ctx.translate(canvas.width, 0);
                    ctx.scale(-1, 1);
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                    canvas.toBlob((blob) => {
                        if (!blob) return;
                        const file = new File([blob], 'qris-proof-' + Date.now() + '.jpg', { type: 'image/jpeg' });
                        @this.upload('paymentProof', file, 
                            () => { this.stopCamera(); }, 
                            (err) => { alert('Gagal mengunggah foto: ' + err); }
                        );
                    }, 'image/jpeg', 0.9);
                }
            }" x-init="startCamera()" x-on:unmount="stopCamera()">
                <label class="block text-xs font-semibold text-stone-500 mb-1.5 uppercase tracking-wider">
                    Foto Bukti QRIS (Kamera Depan)
                    <span class="text-rose-600">*</span>
                </label>

                <div class="relative w-full rounded-2xl overflow-hidden border-2 border-amber-700 bg-black mb-3 text-center min-h-[220px]">
                    <video x-ref="video" 
                           x-show="cameraActive && ! $wire.paymentProof" 
                           autoplay 
                           playsinline 
                           muted 
                           class="w-full max-h-64 object-cover -scale-x-100"></video>

                    @if($paymentProof)
                        <div class="p-3">
                            <img src="{{ $paymentProof->temporaryUrl() }}" alt="Bukti QRIS" class="w-full max-h-56 object-contain rounded-xl border border-stone-200">
                            <div class="flex gap-2 mt-3">
                                <button type="button" @click="startCamera(); $wire.set('paymentProof', null)" class="inline-flex items-center justify-center gap-1.5 min-h-[36px] px-3.5 py-1.5 rounded-lg font-semibold text-xs border border-stone-300 bg-stone-100 hover:bg-stone-200 text-stone-700 transition-all select-none active:scale-95 flex-1">
                                    <span class="material-symbols-outlined text-base">photo_camera</span>
                                    <span>Foto Ulang (Kamera Depan)</span>
                                </button>
                            </div>
                        </div>
                    @else
                        <div x-show="cameraActive" class="p-3 flex items-center justify-center bg-white">
                            <button type="button" @click="capturePhoto()" id="btn-capture-qris" class="inline-flex items-center justify-center gap-1.5 min-h-[44px] px-5 py-2.5 rounded-xl font-semibold text-sm cursor-pointer border-0 transition-all select-none whitespace-nowrap active:scale-[0.96] bg-amber-700 hover:bg-amber-800 text-white shadow-lg shadow-amber-700/20 w-full py-2.5 font-bold">
                                <span class="material-symbols-outlined text-xl">photo_camera</span>
                                <span>Ambil Foto Bukti (Kamera Depan)</span>
                            </button>
                        </div>

                        <div x-show="! cameraActive" class="p-6 text-center space-y-3">
                            <div x-show="cameraError" class="text-xs text-rose-600 p-2 rounded bg-rose-50 border border-rose-200 mb-2" x-text="cameraError"></div>
                            <button type="button" @click="startCamera()" class="inline-flex items-center justify-center gap-1.5 min-h-[36px] px-3.5 py-1.5 rounded-lg font-semibold text-xs border border-stone-300 bg-stone-100 hover:bg-stone-200 text-stone-700 transition-all select-none active:scale-95">
                                <span class="material-symbols-outlined text-base">photo_camera</span>
                                <span>Aktifkan Kamera Depan</span>
                            </button>
                            <div class="pt-2">
                                <label for="payment-proof-upload" class="text-xs text-amber-700 underline cursor-pointer">
                                    Atau buka kamera depan HP
                                </label>
                                <input id="payment-proof-upload" type="file"
                                       wire:model="paymentProof"
                                       accept="image/*"
                                       capture="user"
                                       class="sr-only">
                            </div>
                        </div>
                    @endif
                </div>

                <div wire:loading wire:target="paymentProof" class="flex items-center gap-2 mt-2 text-sm text-stone-500">
                    <span class="material-symbols-outlined text-base animate-spin">sync</span>
                    Memproses foto kamera...
                </div>
            </div>
            @endif

            {{-- Confirm Button --}}
            @php
                $isReady = match($paymentMethod) {
                    'cash'  => $this->paidAmount >= $this->cartTotal,
                    'qris'  => $paymentProof !== null,
                    default => false,
                };
            @endphp

            <button id="btn-confirm-checkout"
                    wire:click="checkout"
                    @if(! $isReady) disabled @endif
                    class="inline-flex items-center justify-center gap-1.5 min-h-[44px] px-5 py-3 rounded-xl font-semibold text-base cursor-pointer border-0 transition-all select-none whitespace-nowrap w-full text-base mt-2 {{ $isReady ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs active:scale-[0.96]' : 'bg-stone-200 text-stone-400 border border-stone-300 opacity-50 cursor-not-allowed' }}"
                    wire:loading.attr="disabled"
                    wire:loading.class="opacity-70">
                <span wire:loading.remove class="material-symbols-outlined text-xl">check</span>
                <span wire:loading class="material-symbols-outlined text-xl animate-spin">sync</span>
                <span wire:loading.remove>Konfirmasi Pembayaran</span>
                <span wire:loading>Memproses...</span>
            </button>
        </div>
    </div>
    @endif

    {{-- ===== SUCCESS RECEIPT MODAL ===== --}}
    @if($showSuccess && !empty($lastOrder))
    <div class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-xs flex flex-col items-center justify-center p-4 transition-opacity">
        <div class="w-[90%] max-w-md bg-white rounded-3xl p-6 max-h-[92dvh] overflow-y-auto shadow-2xl m-auto self-center animate-[scaleIn_0.2s_ease]" id="receipt-modal">
            <div class="w-20 h-20 rounded-full bg-emerald-50 border-4 border-emerald-600 flex items-center justify-center mx-auto mb-4 animate-[popIn_0.4s_cubic-bezier(0.34,1.56,0.64,1)]">
                <span class="material-symbols-outlined text-4xl text-emerald-600">check_circle</span>
            </div>
            <h2 class="text-xl font-bold text-center text-stone-900 mb-1">Pembayaran Berhasil!</h2>
            <p class="text-center text-sm text-stone-500 mb-5">{{ $lastOrder['created_at'] }}</p>

            {{-- Receipt Box --}}
            <div class="p-4 rounded-xl mb-4 bg-stone-50 border border-stone-200">
                <div class="text-center mb-3">
                    <div class="flex items-center justify-center gap-1.5 font-bold text-base text-stone-900">
                        <span class="material-symbols-outlined text-lg text-amber-700">coffee</span>
                        <span>KASIR JUST</span>
                    </div>
                    <p class="text-xs text-stone-500">{{ $lastOrder['invoice_no'] }}</p>
                </div>
                <hr class="border-0 border-t border-dashed border-stone-300 my-3">

                @foreach($lastOrder['items'] as $item)
                <div class="flex justify-between text-sm py-1">
                    <div>
                        <p class="font-medium text-stone-900">{{ $item['name'] }}</p>
                        <p class="text-stone-500 text-xs">{{ $item['qty'] }} × Rp {{ number_format($item['price'], 0, ',', '.') }}</p>
                    </div>
                    <p class="font-semibold text-stone-900">Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</p>
                </div>
                @endforeach

                <hr class="border-0 border-t border-dashed border-stone-300 my-3">
                <div class="flex justify-between items-center py-2 border-b border-stone-200 last:border-b-0">
                    <span class="text-stone-500">Total</span>
                    <span class="font-bold text-amber-700">Rp {{ number_format($lastOrder['total_amount'], 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-stone-200 last:border-b-0">
                    <span class="text-stone-500">Metode</span>
                    <span class="font-semibold uppercase text-stone-800">{{ $lastOrder['payment_method'] }}</span>
                </div>
                @if($lastOrder['payment_method'] === 'cash')
                <div class="flex justify-between items-center py-2 border-b border-stone-200 last:border-b-0">
                    <span class="text-stone-500">Bayar</span>
                    <span class="font-semibold text-stone-800">Rp {{ number_format($lastOrder['paid_amount'], 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-stone-200 last:border-b-0">
                    <span class="text-stone-500">Kembalian</span>
                    <span class="font-bold text-emerald-700">Rp {{ number_format($lastOrder['change_amount'], 0, ',', '.') }}</span>
                </div>
                @endif
            </div>

            {{-- QRIS Proof Preview in Receipt --}}
            @if($lastOrder['payment_method'] === 'qris' && !empty($lastOrder['payment_proof']))
            <div class="mb-4">
                <p class="block text-xs font-semibold text-stone-500 mb-1.5 uppercase tracking-wider mb-2">Bukti Pembayaran QRIS</p>
                <img src="{{ $lastOrder['payment_proof'] }}" alt="Bukti QRIS"
                     class="w-full rounded-xl object-contain border border-stone-200" style="max-height: 180px;">
            </div>
            @endif

            <button id="btn-new-transaction" wire:click="resetPos" class="inline-flex items-center justify-center gap-1.5 min-h-[44px] px-5 py-3 rounded-xl font-semibold text-base cursor-pointer border-0 transition-all select-none whitespace-nowrap active:scale-[0.96] bg-amber-700 hover:bg-amber-800 text-white shadow-xs w-full text-base">
                <span class="material-symbols-outlined text-xl">autorenew</span>
                <span>Transaksi Baru</span>
            </button>
        </div>
    </div>
    @endif

</div>