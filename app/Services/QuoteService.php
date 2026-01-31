<?php

namespace App\Services;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

/**
 * QuoteService
 *
 * Árajánlat PDF generálásért felelős szolgáltatás.
 */
class QuoteService
{
    /**
     * PDF generálás Quote-ból
     *
     * @param  Quote  $quote
     * @return string PDF tartalom (binary)
     *
     * @throws \Throwable
     */
    public function generatePdf(Quote $quote): string
    {
        // Blade view renderelése
        $html = View::make('pdf.quote', [
            'quote' => $quote,
        ])->render();

        // DomPDF generálás Nunito fonttal
        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'fontDir' => storage_path('fonts'),
                'fontCache' => storage_path('fonts'),
                'chroot' => [base_path()],
                'defaultFont' => 'NotoSans',
            ]);

        return $pdf->output();
    }

    /**
     * PDF letöltés válasz generálás
     *
     * @param  Quote  $quote
     * @param  string  $filename Custom fájlnév (opcionális)
     * @return \Illuminate\Http\Response
     *
     * @throws \Throwable
     */
    public function downloadPdf(Quote $quote, ?string $filename = null): \Illuminate\Http\Response
    {
        $pdf = $this->generatePdf($quote);

        $filename = $filename ?? "arajanlat-{$quote->quote_number}.pdf";

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * PDF előnézet böngészőben
     *
     * @param  Quote  $quote
     * @return \Illuminate\Http\Response
     *
     * @throws \Throwable
     */
    public function previewPdf(Quote $quote): \Illuminate\Http\Response
    {
        $pdf = $this->generatePdf($quote);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="arajanlat-'.$quote->quote_number.'.pdf"',
        ]);
    }

    /**
     * Árajánlat validálás PDF generálás előtt
     *
     * @param  Quote  $quote
     * @return array Hiányosságok listája
     */
    public function validateQuote(Quote $quote): array
    {
        $errors = [];

        if (! $quote->customer_name) {
            $errors[] = 'Hiányzó ügyfél név';
        }

        if (! $quote->quote_number) {
            $errors[] = 'Hiányzó árajánlat szám';
        }

        if (! $quote->quote_date) {
            $errors[] = 'Hiányzó árajánlat dátum';
        }

        if ($quote->base_price <= 0 && $quote->discount_price <= 0) {
            $errors[] = 'Hiányzó árazás (alap ár vagy kedvezményes ár kötelező)';
        }

        return $errors;
    }
}
