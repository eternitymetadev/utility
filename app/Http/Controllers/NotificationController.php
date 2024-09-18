<?php

// app/Http/Controllers/NotificationController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class NotificationController extends Controller
{
    public function handleNotification(Request $request)
    {
        Log::info('Notification received: ' . json_encode($request->all()));

        $clientState = $request->header('client-state');
        if ($clientState !== 'secretClientState') {
            return response('Invalid client state', 400);
        }

        $notifications = $request->input('value');
        foreach ($notifications as $notification) {
            $resource = $notification['resource'];
            $resourceData = $this->getResourceData($resource);

            if ($this->isNewPdf($resourceData)) {
                $this->handleNewPdf($resourceData);
            }
        }

        return response('Notification processed', 200);
    }

    private function getResourceData($resource)
    {
        // Example of fetching resource data
        $client = new Client();
        $accessToken = session('access_token');
        
        $response = $client->request('GET', "https://graph.microsoft.com/v1.0/$resource", [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ]
        ]);

        return json_decode($response->getBody(), true);
    }

    private function isNewPdf($resourceData)
    {
        return isset($resourceData['file']) && strtolower(pathinfo($resourceData['name'], PATHINFO_EXTENSION)) === 'pdf';
    }

    private function handleNewPdf($resourceData)
    {
        // Your logic to handle the new PDF file
        // Example: Download the PDF, parse it, etc.
        $client = new Client();
        $accessToken = session('access_token');

        // Fetch PDF content
        $pdfFileResponse = $client->request('GET', "https://graph.microsoft.com/v1.0/drive/items/{$resourceData['id']}/content", [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/pdf',
            ]
        ]);

        $pdfContentStream = $pdfFileResponse->getBody()->getContents();
        
        // Assuming $parser is defined here or passed in as needed
        try {
            $parser = new Parser();
            $pdf = $parser->parseContent($pdfContentStream);
            $pdfContent = $pdf->getText();
            $invoiceNo = $this->extractInvoiceNo($pdfContent);
            $buyerSection = $this->extractBuyerSection($pdfContent);
            $gstins = $this->extractGSTINsFromBuyerSection($buyerSection);

            $fileInfos[] = [
                'Subject' => $invoiceNo,
                'Bill to GST' => $gstins,
                'Attachment' => $resourceData['@microsoft.graph.downloadUrl'],
                'Email Time' => $resourceData['lastModifiedDateTime'],
            ];
            
            // Save or process $fileInfos as needed
        } catch (\Exception $e) {
            Log::error('PDF parsing failed: ' . $e->getMessage());
        }
    }
}
