<?php

use App\Models\Category;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Attributes\Computed;

new class extends Component {
    public bool    $showModal   = false;
    public bool    $showDelete  = false;
    public int|null $editId     = null;
    public int|null $deleteId   = null;
    public string  $name        = '';
    public string  $search      = '';

    #[Computed]
    public function categories(): \Illuminate\Database\Eloquent\Collection
    {
        return Category::withCount('products')
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->get();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $cat         = Category::findOrFail($id);
        $this->editId = $id;
        $this->name  = $cat->name;
        $this->showModal = true;
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId   = $id;
        $this->showDelete = true;
    }

    public function delete(): void
    {
        Category::find($this->deleteId)?->delete();
        $this->showDelete = false;
        $this->deleteId   = null;
        unset($this->categories);
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100',
                        Rule::unique('categories', 'name')->ignore($this->editId)],
        ]);

        $slug = \Illuminate\Support\Str::slug($this->name);

        if ($this->editId) {
            Category::find($this->editId)?->update(['name' => $this->name, 'slug' => $slug]);
        } else {
            Category::create(['name' => $this->name, 'slug' => $slug]);
        }

        $this->showModal = false;
        $this->resetForm();
        unset($this->categories);
    }

    private function resetForm(): void
    {
        $this->editId = null;
        $this->name   = '';
        $this->resetValidation();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('components.master.kategori');
    }
};
?>

<div x-effect="document.body.style.overflow = $wire.showModal || $wire.showDelete ? 'hidden' : ''">

    <header class="sticky top-0 z-20 bg-white/95 border-b border-stone-200 px-4 py-3.5 flex items-center justify-between backdrop-blur-md">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-xl text-amber-700">label</span>
            <div>
                <h1 class="text-base font-bold text-stone-900">Kelola Kategori</h1>
                <p class="text-xs text-stone-500">{{ $this->categories->count() }} kategori</p>
            </div>
        </div>
        <button id="btn-tambah-kategori" wire:click="openCreate" class="inline-flex items-center justify-center gap-1 min-h-[36px] px-3.5 py-1.5 rounded-xl font-semibold text-xs cursor-pointer border-0 transition-all select-none whitespace-nowrap active:scale-[0.96] bg-amber-700 hover:bg-amber-800 text-white shadow-xs">
            <span class="material-symbols-outlined text-base">add</span>
            <span>Tambah</span>
        </button>
    </header>

    <div class="px-4 py-4 pb-8">
        {{-- Search --}}
        <div class="relative mb-4">
            <span class="material-symbols-outlined text-base absolute left-3 top-1/2 -translate-y-1/2 text-stone-400">search</span>
            <input id="kat-search" type="search" wire:model.live.debounce.300ms="search"
                   placeholder="Cari kategori..." class="w-full min-h-[44px] px-3.5 py-2.5 pl-10 bg-white border border-stone-300 rounded-xl text-stone-900 text-sm font-sans outline-hidden transition-all placeholder:text-stone-400 focus:border-amber-700 focus:ring-2 focus:ring-amber-700/20">
        </div>

        {{-- List --}}
        <div class="flex flex-col gap-3">
            @forelse($this->categories as $cat)
            <div wire:key="kat-{{ $cat->id }}" class="bg-white border border-stone-200 rounded-2xl p-4 shadow-xs flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 bg-amber-100 border border-amber-200">
                    <span class="material-symbols-outlined text-3xl text-amber-700">label</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-stone-900">{{ $cat->name }}</p>
                    <p class="text-sm text-stone-500">{{ $cat->products_count }} produk</p>
                </div>
                <div class="flex gap-2 shrink-0">
                    <button wire:click="openEdit({{ $cat->id }})" id="edit-kat-{{ $cat->id }}"
                            class="inline-flex items-center justify-center gap-1 min-h-[32px] px-2.5 py-1 rounded-lg font-semibold text-xs border border-stone-300 bg-stone-100 hover:bg-stone-200 text-stone-700 transition-all select-none active:scale-95">
                        <span class="material-symbols-outlined text-xs">edit</span>
                        <span>Edit</span>
                    </button>
                    <button wire:click="confirmDelete({{ $cat->id }})" id="del-kat-{{ $cat->id }}"
                            class="inline-flex items-center justify-center min-w-[36px] min-h-[32px] p-1 rounded-lg font-semibold text-xs border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 transition-all select-none active:scale-95">
                        <span class="material-symbols-outlined text-base">delete</span>
                    </button>
                </div>
            </div>
            @empty
            <div class="py-16 text-center text-stone-400">
                <p class="font-medium text-stone-500">Belum ada kategori</p>
            </div>
            @endforelse
        </div>
    </div>

    {{-- FORM MODAL --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-xs flex items-center justify-center p-4 transition-opacity">
        <div class="w-[90%] max-w-md bg-white rounded-3xl p-6 max-h-[90dvh] overflow-y-auto shadow-2xl m-auto self-center animate-[scaleIn_0.2s_ease]" id="kategori-form-modal">
            <h2 class="font-bold text-lg text-stone-900 mb-4">{{ $editId ? 'Edit Kategori' : 'Tambah Kategori' }}</h2>
            <div class="mb-4">
                <label class="block text-xs font-semibold text-stone-500 mb-1.5 uppercase tracking-wider" for="kat-name">Nama Kategori</label>
                <input id="kat-name" type="text" wire:model="name" class="w-full min-h-[44px] px-3.5 py-2.5 bg-white border border-stone-300 rounded-xl text-stone-900 text-sm font-sans outline-hidden transition-all placeholder:text-stone-400 focus:border-amber-700 focus:ring-2 focus:ring-amber-700/20" placeholder="Nama kategori..." wire:keydown.enter="save">
                @error('name') <p class="text-xs mt-1 text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-3">
                <button wire:click="$set('showModal', false)" class="inline-flex items-center justify-center gap-1.5 min-h-[36px] px-3.5 py-1.5 rounded-lg font-semibold text-xs border border-stone-300 bg-stone-100 hover:bg-stone-200 text-stone-700 transition-all select-none active:scale-95 flex-1" id="btn-cancel-kat">Batal</button>
                <button wire:click="save" class="inline-flex items-center justify-center gap-1.5 min-h-[36px] px-3.5 py-1.5 rounded-xl font-semibold text-xs cursor-pointer border-0 transition-all select-none whitespace-nowrap active:scale-[0.96] bg-amber-700 hover:bg-amber-800 text-white shadow-xs flex-1" id="btn-save-kat">Simpan</button>
            </div>
        </div>
    </div>
    @endif

    {{-- DELETE CONFIRM --}}
    @if($showDelete)
    <div class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-xs flex items-center justify-center p-4 transition-opacity">
        <div class="w-[90%] max-w-md bg-white rounded-3xl p-6 max-h-[90dvh] overflow-y-auto shadow-2xl m-auto self-center animate-[scaleIn_0.2s_ease]" id="kat-delete-modal">
            <h3 class="font-bold text-lg text-center text-stone-900 mb-2">Hapus Kategori?</h3>
            <p class="text-sm text-center text-stone-500 mb-5">Semua produk di kategori ini juga akan terhapus.</p>
            <div class="flex gap-3">
                <button wire:click="$set('showDelete', false)" class="inline-flex items-center justify-center gap-1.5 min-h-[36px] px-3.5 py-1.5 rounded-lg font-semibold text-xs border border-stone-300 bg-stone-100 hover:bg-stone-200 text-stone-700 transition-all select-none active:scale-95 flex-1">Batal</button>
                <button wire:click="delete" class="inline-flex items-center justify-center gap-1.5 min-h-[36px] px-3.5 py-1.5 rounded-lg font-semibold text-xs border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 transition-all select-none active:scale-95 flex-1" id="btn-confirm-del-kat">Hapus</button>
            </div>
        </div>
    </div>
    @endif

</div>