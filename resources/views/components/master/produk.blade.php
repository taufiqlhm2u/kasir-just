<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    // List state
    public string $search       = '';
    public int|null $filterCat  = null;

    // Form state
    public bool $showModal    = false;
    public bool $showDeleteId = false;

    public int|null $editId   = null;
    public int|null $deleteId = null;

    // Fields
    public int|null $category_id = null;
    public string   $name        = '';
    public string   $price       = '';
    public int      $stock       = 0;
    public mixed    $image       = null;
    public string   $existingImage = '';

    #[Computed]
    public function categories(): \Illuminate\Database\Eloquent\Collection
    {
        return Category::orderBy('name')->get();
    }

    #[Computed]
    public function products(): \Illuminate\Support\Collection|\Illuminate\Pagination\LengthAwarePaginator
    {
        return Product::with('category')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->filterCat, fn ($q) => $q->where('category_id', $this->filterCat))
            ->orderBy('name')
            ->paginate(12);
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $product = Product::findOrFail($id);

        $this->editId        = $id;
        $this->category_id   = $product->category_id;
        $this->name          = $product->name;
        $this->price         = (string) $product->price;
        $this->stock         = $product->stock;
        $this->existingImage = $product->image ?? '';
        $this->image         = null;
        $this->showModal     = true;
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId     = $id;
        $this->showDeleteId = true;
    }

    public function delete(): void
    {
        $product = Product::find($this->deleteId);

        if ($product) {
            if ($product->image && ! str_starts_with($product->image, 'http')) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($product->image);
            }
            $product->delete();
        }

        $this->showDeleteId = false;
        $this->deleteId     = null;
        unset($this->products);
    }

    public function save(): void
    {
        $this->validate([
            'category_id' => 'required|exists:categories,id',
            'name'        => ['required', 'string', 'max:100',
                              Rule::unique('products', 'name')->ignore($this->editId)],
            'price'       => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'image'       => $this->editId ? 'nullable|image|max:2048' : 'nullable|image|max:2048',
        ]);

        $imagePath = $this->existingImage;

        if ($this->image) {
            if ($this->editId && $this->existingImage && ! str_starts_with($this->existingImage, 'http')) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($this->existingImage);
            }
            $imagePath = $this->image->store('products', 'public');
        }

        $data = [
            'category_id' => $this->category_id,
            'name'        => $this->name,
            'price'       => $this->price,
            'stock'       => $this->stock,
            'image'       => $imagePath ?: null,
        ];

        if ($this->editId) {
            Product::find($this->editId)?->update($data);
        } else {
            Product::create($data);
        }

        $this->showModal = false;
        $this->resetForm();
        unset($this->products);
    }

    private function resetForm(): void
    {
        $this->editId        = null;
        $this->category_id   = null;
        $this->name          = '';
        $this->price         = '';
        $this->stock         = 0;
        $this->image         = null;
        $this->existingImage = '';
        $this->resetValidation();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('components.master.produk');
    }
};
?>

<div x-effect="document.body.style.overflow = ($wire.showModal || $wire.showDeleteId) ? 'hidden' : ''">

    {{-- ===== PAGE HEADER ===== --}}
    <header class="sticky top-0 z-20 bg-white/95 border-b border-stone-200 px-4 py-3.5 flex items-center justify-between backdrop-blur-md">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-xl text-amber-700">inventory_2</span>
            <div>
                <h1 class="text-base font-bold text-stone-900">Kelola Produk</h1>
                <p class="text-xs text-stone-500">{{ $this->products->total() }} produk terdaftar</p>
            </div>
        </div>
        <button id="btn-tambah-produk" wire:click="openCreate" class="inline-flex items-center justify-center gap-1 min-h-[36px] px-3.5 py-1.5 rounded-xl font-semibold text-xs cursor-pointer border-0 transition-all select-none whitespace-nowrap active:scale-[0.96] bg-amber-700 hover:bg-amber-800 text-white shadow-xs">
            <span class="material-symbols-outlined text-base">add</span>
            <span>Tambah</span>
        </button>
    </header>

    {{-- ===== SUB NAVIGATION ===== --}}
    <div class="bg-stone-100 border-b border-stone-200 px-4 py-2 flex gap-2">
        <a href="{{ route('produk') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-700 text-white shadow-xs">Produk</a>
        <a href="{{ route('kategori') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-stone-600 hover:bg-stone-200 transition-colors">Kategori</a>
        <a href="{{ route('tambahan') }}" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-stone-600 hover:bg-stone-200 transition-colors">Opsi Tambahan</a>
    </div>

    {{-- ===== SEARCH & FILTER ===== --}}
    <div class="sticky top-[64px] z-10 px-4 py-3 bg-stone-100 border-b border-stone-200">
        <div class="relative mb-3">
            <span class="material-symbols-outlined text-base absolute left-3 top-1/2 -translate-y-1/2 text-stone-400">search</span>
            <input id="produk-search" type="search" wire:model.live.debounce.300ms="search"
                   placeholder="Cari produk..." class="w-full min-h-[44px] px-3.5 py-2.5 pl-10 bg-white border border-stone-300 rounded-xl text-stone-900 text-sm font-sans outline-hidden transition-all placeholder:text-stone-400 focus:border-amber-700 focus:ring-2 focus:ring-amber-700/20" autocomplete="off">
        </div>
        <div class="flex gap-2 overflow-x-auto pb-1" style="scrollbar-width: none;">
            <button wire:click="$set('filterCat', null)" class="inline-flex items-center px-4 py-1.5 rounded-full text-xs whitespace-nowrap cursor-pointer transition-all border {{ $filterCat === null ? 'border-amber-700 bg-amber-700 text-white font-semibold shadow-xs' : 'border-stone-300 bg-white text-stone-600 font-medium active:scale-95' }}">Semua</button>
            @foreach($this->categories as $cat)
            <button wire:key="fcat-{{ $cat->id }}" wire:click="$set('filterCat', {{ $cat->id }})"
                    class="inline-flex items-center px-4 py-1.5 rounded-full text-xs whitespace-nowrap cursor-pointer transition-all border {{ $filterCat === $cat->id ? 'border-amber-700 bg-amber-700 text-white font-semibold shadow-xs' : 'border-stone-300 bg-white text-stone-600 font-medium active:scale-95' }}">{{ $cat->name }}</button>
            @endforeach
        </div>
    </div>

    {{-- ===== PRODUCT GRID ===== --}}
    <div class="px-4 py-4 grid grid-cols-2 gap-3">
        @forelse($this->products as $product)
        <div wire:key="mgmt-product-{{ $product->id }}" class="bg-white border border-stone-200 rounded-2xl overflow-hidden shadow-xs flex flex-col">
            <div class="relative overflow-hidden aspect-4/3">
                <img src="{{ $product->image_url }}" alt="{{ $product->name }}"
                     class="w-full h-full object-cover" loading="lazy"
                     onerror="this.src='https://placehold.co/400x300/f5f5f4/78716c?text={{ urlencode($product->name) }}'">
                <div class="absolute bottom-2 right-2">
                    <span class="text-xs px-2 py-1 rounded-full font-semibold bg-black/70 {{ $product->stock > 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                        Stok: {{ $product->stock }}
                    </span>
                </div>
            </div>
            <div class="p-3">
                <p class="font-semibold text-sm truncate mb-0.5 text-stone-900">{{ $product->name }}</p>
                <p class="text-xs mb-2 text-stone-500">{{ $product->category->name ?? '-' }}</p>
                <div class="flex justify-between items-center text-xs mb-3">
                    <span class="text-stone-500">Harga</span>
                    <span class="font-bold text-sm text-amber-700">Rp {{ number_format($product->price, 0, ',', '.') }}</span>
                </div>
                <div class="flex gap-2">
                    <button wire:click="openEdit({{ $product->id }})" id="edit-prod-{{ $product->id }}"
                            class="inline-flex items-center justify-center gap-1 min-h-[32px] px-2.5 py-1 rounded-lg font-semibold text-xs border border-stone-300 bg-stone-100 hover:bg-stone-200 text-stone-700 transition-all select-none active:scale-95 flex-1">
                        <span class="material-symbols-outlined text-xs">edit</span>
                        <span>Edit</span>
                    </button>
                    <button wire:click="confirmDelete({{ $product->id }})" id="del-prod-{{ $product->id }}"
                            class="inline-flex items-center justify-center min-w-[36px] min-h-[32px] p-1 rounded-lg font-semibold text-xs border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 transition-all select-none active:scale-95">
                        <span class="material-symbols-outlined text-base">delete</span>
                    </button>
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-2 py-16 text-center text-stone-400">
            <p class="font-medium text-stone-500">Belum ada produk</p>
            <p class="text-sm mt-1 text-stone-400">Tap "Tambah" untuk menambah produk baru</p>
        </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($this->products->hasPages())
    <div class="px-4 pb-4">{{ $this->products->links() }}</div>
    @endif

    {{-- ===== PRODUCT FORM MODAL ===== --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-xs flex flex-col justify-end transition-opacity" wire:click.self="$set('showModal', false)">
        <div class="w-full max-w-lg bg-white rounded-t-3xl px-5 pt-2 pb-8 max-h-[90dvh] overflow-y-auto overscroll-contain shadow-xl mx-auto animate-[slideUp_0.25s_cubic-bezier(0.34,1.56,0.64,1)]" id="produk-form-modal">
            <div class="w-9 h-1 bg-stone-300 rounded-full mx-auto mb-4"></div>
            <div class="flex items-center justify-between mb-5">
                <h2 class="font-bold text-lg text-stone-900">{{ $editId ? 'Edit Produk' : 'Tambah Produk' }}</h2>
                <button wire:click="$set('showModal', false)" class="inline-flex items-center justify-center min-w-[32px] min-h-[32px] p-1.5 rounded-lg font-semibold text-xs border border-stone-300 bg-stone-100 hover:bg-stone-200 text-stone-700 transition-all select-none active:scale-95">
                    <span class="material-symbols-outlined text-base">close</span>
                </button>
            </div>

            <div class="flex flex-col gap-4 max-h-[70dvh] overflow-y-auto">

                {{-- Kategori --}}
                <div>
                    <label class="block text-xs font-semibold text-stone-500 mb-1.5 uppercase tracking-wider" for="prod-category">Kategori</label>
                    <select id="prod-category" wire:model="category_id" class="w-full min-h-[44px] px-3.5 py-2.5 bg-white border border-stone-300 rounded-xl text-stone-900 text-sm font-sans outline-hidden transition-all placeholder:text-stone-400 focus:border-amber-700 focus:ring-2 focus:ring-amber-700/20">
                        <option value="">Pilih kategori...</option>
                        @foreach($this->categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <p class="text-xs mt-1 text-rose-600">{{ $message }}</p> @enderror
                </div>

                {{-- Nama --}}
                <div>
                    <label class="block text-xs font-semibold text-stone-500 mb-1.5 uppercase tracking-wider" for="prod-name">Nama Produk</label>
                    <input id="prod-name" type="text" wire:model="name" class="w-full min-h-[44px] px-3.5 py-2.5 bg-white border border-stone-300 rounded-xl text-stone-900 text-sm font-sans outline-hidden transition-all placeholder:text-stone-400 focus:border-amber-700 focus:ring-2 focus:ring-amber-700/20" placeholder="Nama produk...">
                    @error('name') <p class="text-xs mt-1 text-rose-600">{{ $message }}</p> @enderror
                </div>

                {{-- Harga Jual --}}
                <div>
                    <label class="block text-xs font-semibold text-stone-500 mb-1.5 uppercase tracking-wider" for="prod-price">Harga Jual</label>
                    <input id="prod-price" type="number" wire:model="price" class="w-full min-h-[44px] px-3.5 py-2.5 bg-white border border-stone-300 rounded-xl text-stone-900 text-sm font-sans outline-hidden transition-all placeholder:text-stone-400 focus:border-amber-700 focus:ring-2 focus:ring-amber-700/20" placeholder="0" min="0" inputmode="numeric">
                    @error('price') <p class="text-xs mt-1 text-rose-600">{{ $message }}</p> @enderror
                </div>

                {{-- Stok --}}
                <div>
                    <label class="block text-xs font-semibold text-stone-500 mb-1.5 uppercase tracking-wider" for="prod-stock">Stok</label>
                    <input id="prod-stock" type="number" wire:model="stock" class="w-full min-h-[44px] px-3.5 py-2.5 bg-white border border-stone-300 rounded-xl text-stone-900 text-sm font-sans outline-hidden transition-all placeholder:text-stone-400 focus:border-amber-700 focus:ring-2 focus:ring-amber-700/20" placeholder="0" min="0" inputmode="numeric">
                    @error('stock') <p class="text-xs mt-1 text-rose-600">{{ $message }}</p> @enderror
                </div>

                {{-- Gambar --}}
                <div>
                    <label class="block text-xs font-semibold text-stone-500 mb-1.5 uppercase tracking-wider">Gambar Produk</label>

                    @if($image)
                    <div class="relative mb-2">
                        <img src="{{ $image->temporaryUrl() }}" class="w-full h-32 object-cover rounded-xl border border-stone-200" alt="Preview">
                        <button wire:click="$set('image', null)" type="button"
                                class="absolute top-2 right-2 inline-flex items-center justify-center min-w-[32px] min-h-[32px] p-1.5 rounded-lg font-semibold text-xs border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 transition-all select-none active:scale-95">
                            <span class="material-symbols-outlined text-base">close</span>
                        </button>
                    </div>
                    @elseif($existingImage)
                    <div class="relative mb-2">
                        <img src="{{ asset('storage/' . ltrim(str_replace('storage/', '', $existingImage), '/')) }}"
                             class="w-full h-32 object-cover rounded-xl border border-stone-200" alt="Gambar saat ini">
                        <div class="absolute bottom-2 left-2">
                            <span class="text-xs px-2 py-1 rounded-full bg-white/90 text-stone-800 font-medium border border-stone-200">Gambar saat ini</span>
                        </div>
                    </div>
                    @endif

                    <label for="prod-image"
                           class="flex items-center gap-3 p-4 rounded-xl border-2 border-dashed border-stone-300 bg-stone-50 hover:bg-stone-100 cursor-pointer">
                        <span class="material-symbols-outlined text-3xl text-amber-700 shrink-0">cloud_upload</span>
                        <div>
                            <p class="text-sm font-medium text-stone-800">{{ $image || $existingImage ? 'Ganti gambar' : 'Upload gambar' }}</p>
                            <p class="text-xs text-stone-500">JPG, PNG, maks. 2MB</p>
                        </div>
                        <input id="prod-image" type="file" wire:model="image" accept="image/*" class="sr-only">
                    </label>
                    @error('image') <p class="text-xs mt-1 text-rose-600">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="image" class="text-xs mt-1 text-stone-500">Mengunggah...</div>
                </div>
            </div>

            <button wire:click="save" id="btn-save-produk"
                    class="inline-flex items-center justify-center gap-1.5 min-h-[44px] px-5 py-3 rounded-xl font-semibold text-base cursor-pointer border-0 transition-all select-none whitespace-nowrap active:scale-[0.96] bg-amber-700 hover:bg-amber-800 text-white shadow-xs w-full mt-5"
                    wire:loading.attr="disabled" wire:loading.class="opacity-70">
                <span wire:loading.remove>{{ $editId ? 'Simpan Perubahan' : 'Tambah Produk' }}</span>
                <span wire:loading>Menyimpan...</span>
            </button>
        </div>
    </div>
    @endif

    {{-- ===== DELETE CONFIRM MODAL ===== --}}
    @if($showDeleteId)
    <div class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-xs flex items-center justify-center p-4 transition-opacity">
        <div class="w-[90%] max-w-md bg-white rounded-3xl p-6 max-h-[90dvh] overflow-y-auto shadow-2xl m-auto self-center animate-[scaleIn_0.2s_ease]" id="delete-confirm-modal">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4 bg-rose-100 border border-rose-200">
                <span class="material-symbols-outlined text-3xl text-rose-600">warning</span>
            </div>
            <h3 class="font-bold text-lg text-center text-stone-900 mb-2">Hapus Produk?</h3>
            <p class="text-sm text-center text-stone-500 mb-5">Produk yang dihapus tidak dapat dikembalikan.</p>
            <div class="flex gap-3">
                <button wire:click="$set('showDeleteId', false)" class="inline-flex items-center justify-center gap-1.5 min-h-[36px] px-3.5 py-1.5 rounded-lg font-semibold text-xs border border-stone-300 bg-stone-100 hover:bg-stone-200 text-stone-700 transition-all select-none active:scale-95 flex-1" id="btn-cancel-delete">Batal</button>
                <button wire:click="delete" class="inline-flex items-center justify-center gap-1.5 min-h-[36px] px-3.5 py-1.5 rounded-lg font-semibold text-xs border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 transition-all select-none active:scale-95 flex-1" id="btn-confirm-delete">Hapus</button>
            </div>
        </div>
    </div>
    @endif

</div>