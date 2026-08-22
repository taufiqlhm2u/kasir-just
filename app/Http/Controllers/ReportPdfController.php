<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportPdfController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $dateFrom = $request->query('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->query('date_to', now()->format('Y-m-d'));
        $filterMethod = $request->query('filter_method', '');

        $orders = Order::with('items')
            ->when($dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->when($filterMethod, fn ($q) => $q->where('payment_method', $filterMethod))
            ->latest()
            ->get();

        $totalOmset = (float) $orders->sum('total_amount');
        $totalTransaksi = $orders->count();
        $totalCups = (int) $orders->sum(fn ($order) => $order->items->sum('quantity'));
        $totalCash = (float) $orders->where('payment_method', 'cash')->sum('total_amount');
        $totalQris = (float) $orders->where('payment_method', 'qris')->sum('total_amount');

        $pdf = Pdf::loadView('pdf.laporan-penjualan', [
            'orders' => $orders,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'filterMethod' => $filterMethod,
            'totalOmset' => $totalOmset,
            'totalTransaksi' => $totalTransaksi,
            'totalCups' => $totalCups,
            'totalCash' => $totalCash,
            'totalQris' => $totalQris,
            'printedAt' => now()->isoFormat('D MMMM Y, HH:mm'),
        ]);

        $pdf->setPaper('a4', 'portrait');

        $filename = 'Laporan-Penjualan-'.$dateFrom.'-sd-'.$dateTo.'.pdf';

        return $pdf->stream($filename);
    }
}
