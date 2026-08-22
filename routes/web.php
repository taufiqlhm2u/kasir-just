<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReportPdfController;
use Illuminate\Support\Facades\Route;

// Guest Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');

// Auth Protected Routes
Route::middleware('auth')->group(function () {
    Route::redirect('/', '/kasir')->name('home');

    Route::get('/kasir', function () {
        return view('livewire.pos.kasir');
    })->name('kasir');

    Route::get('/produk', function () {
        return view('livewire.master.produk');
    })->name('produk');

    Route::get('/kategori', function () {
        return view('livewire.master.kategori');
    })->name('kategori');

    Route::get('/tambahan', function () {
        return view('livewire.master.tambahan');
    })->name('tambahan');

    Route::get('/laporan', function () {
        return view('livewire.report.penjualan');
    })->name('laporan');

    Route::get('/laporan/pdf', ReportPdfController::class)->name('laporan.pdf');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
