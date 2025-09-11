<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class ResortPoliciesPdfService
{
    /**
     * Generate the resort policies PDF
     */
    public function generatePdf(): string
    {
        $pdf = Pdf::loadView('pdfs.resort_policies');
        
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
    public function getFilename(): string
    {
        return 'Netania_De_Laiya_Resort_Policies_' . date('Y-m-d') . '.pdf';
    }
}
