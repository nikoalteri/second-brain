<?php

namespace App\Services;

use App\Models\Trip;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

class TravelPdfExporter
{
    /**
     * Export a trip's itineraries to PDF format.
     *
     * Generates a downloadable PDF containing the trip details, all itineraries,
     * activities, budget information, and participant list.
     *
     * @param Trip $trip The trip to export
     * @return \Illuminate\Http\Response PDF response for download
     */
    public function export(Trip $trip): \Illuminate\Http\Response
    {
        // Eager load relationships to avoid N+1 queries
        $trip->loadMissing([
            'itineraries.activities',
            'destinations',
            'participants',
            'budget',
            'expenses',
        ]);

        // Get HTML content from Blade view
        $html = $this->getHtmlContent($trip);

        // Generate PDF from HTML using DomPDF
        $pdf = Pdf::loadHtml($html)
            ->setPaper('a4')
            ->setOption('isPhpEnabled', false);

        // Create filename: itinerary-{trip-id}-{trip-slug}.pdf
        $filename = sprintf(
            'itinerary-%d-%s.pdf',
            $trip->id,
            str()->slug($trip->title, '-')
        );

        // Return downloadable response
        return $pdf->download($filename);
    }

    /**
     * Generate HTML content for PDF rendering.
     *
     * Compiles trip details, itineraries, activities, budget, and participants
     * into a Blade template for PDF generation.
     *
     * @param Trip $trip The trip with all relationships loaded
     * @return string HTML content ready for PDF conversion
     */
    private function getHtmlContent(Trip $trip): string
    {
        return View::make('pdfs.itinerary', [
            'trip' => $trip,
            'itineraries' => $trip->itineraries,
            'destinations' => $trip->destinations,
            'participants' => $trip->participants,
            'budget' => $trip->budget,
            'expenses' => $trip->expenses,
        ])->render();
    }
}
