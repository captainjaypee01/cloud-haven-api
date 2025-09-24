<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class ResortPoliciesPdfService
{
    /**
     * Generate the resort policies PDF
     */
    public function generatePdf($booking = null): string
    {
        if ($booking) {
            // Generate PDF with booking details and policies
            $pdf = Pdf::loadView('pdfs.booking_with_policies', compact('booking'));
        } else {
            // Generate PDF with policies only
            $pdf = Pdf::loadView('pdfs.resort_policies');
        }
        
        // Set paper size and orientation
        $pdf->setPaper('A4', 'portrait');
        
        // Generate the PDF content
        $pdfContent = $pdf->output();
        
        // Create a temporary file
        $tempPath = tempnam(sys_get_temp_dir(), 'resort_policies_');
        file_put_contents($tempPath, $pdfContent);
        
        return $tempPath;
    }
    
    /**
     * Get the PDF filename
     */
    public function getFilename($booking = null): string
    {
        if ($booking) {
            return 'Booking_' . $booking->reference_number . '_Policies_' . date('Y-m-d') . '.pdf';
        }
        return 'Netania_De_Laiya_Resort_Policies_' . date('Y-m-d') . '.pdf';
    }
}
