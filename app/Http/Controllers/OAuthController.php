<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use League\OAuth2\Client\Provider\GenericProvider;
use GuzzleHttp\Client;
use App\Exports\FileInfoExport;
use Maatwebsite\Excel\Facades\Excel;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class OAuthController extends Controller
{
    private $provider;

    public function __construct()
    {
        $this->provider = new GenericProvider([
            'clientId'                => env('MICROSOFT_CLIENT_ID'),
            'clientSecret'            => env('MICROSOFT_CLIENT_SECRET'),
            'redirectUri'             => env('MICROSOFT_REDIRECT_URL'),
            'urlAuthorize'            => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'urlAccessToken'          => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'urlResourceOwnerDetails' => 'https://graph.microsoft.com/v2.0/me',
            'scope'                  => ['openid','User.Read','profile','Files.ReadWrite']
        ]);
    }

    public function triggerRedirect()
    {
        \Log::info('Attempting to trigger redirect');
    
        $redirectUrl = 'https://utility.etsbeta.com/auth/redirect';
    
        try {
            $response = Http::get($redirectUrl);
    
            if ($response->successful()) {
                \Log::info('Redirect triggered successfully', ['status' => $response->status(), 'body' => $response->body()]);
    
                $handleCallback = 'https://utility.etsbeta.com/auth/callback';
                \Log::info('Attempting to trigger callback');
    
                $callbackResponse = Http::get($handleCallback);
    
                if ($callbackResponse->successful()) {
                    \Log::info('Callback triggered successfully', ['status' => $callbackResponse->status(), 'body' => $callbackResponse->body()]);
                    return response()->json(['message' => 'Successfully triggered callback']);
                } else {
                    \Log::error('Failed to trigger callback', ['status' => $callbackResponse->status(), 'body' => $callbackResponse->body()]);
                    return response()->json(['error' => 'Failed to trigger callback'], 500);
                }
            } else {
                \Log::error('Failed to trigger redirect', ['status' => $response->status(), 'body' => $response->body()]);
                return response()->json(['error' => 'Failed to trigger redirect'], 500);
            }
        } catch (\Exception $e) {
            \Log::error('Exception occurred while triggering redirect or callback', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Exception occurred while triggering redirect or callback'], 500);
        }
    }
    


    public function redirectToProvider()
    {
        $baseUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
        $params = [
            'client_id' => env('MICROSOFT_CLIENT_ID'),
            'redirect_uri' => env('MICROSOFT_REDIRECT_URL'),
            'response_type' => 'code',
            'scope' => 'openid Files.ReadWrite User.Read', // Space-separated and not encoded
            'state' => bin2hex(random_bytes(16))
        ];
    
        $authorizationUrl = $baseUrl . '?' . http_build_query($params);
    
        session(['oauth2state' => $params['state']]);
       \Log::info('Stored state 1: ' . session('oauth2state')); 

        return redirect($authorizationUrl);
    }
    

    // public function handleProviderCallback(Request $request)
    // {
    //    // \Log::info('Request state: ' . $request->input('state')); // Log incoming state for debugging
    
    //     $state = $request->input('state'); 
    //     $storedState = session('oauth2state'); 
    
    //     // Now, handle the code
    //     $code = $request->input('code');
    //     if (empty($code)) {
    //         return view('auth.callback', ['message' => 'Authorization code not found', 'status' => 'error']);
    //     }
    
    //     try {
    //         // Request access token
    //         $accessToken = $this->provider->getAccessToken('authorization_code', [
    //             'code' => $code,
    //             'redirect_uri' => env('MICROSOFT_REDIRECT_URL'),
    //             'scope' => ['User.Read', 'openid', 'profile', 'Files.ReadWrite']
    //         ]);
    
    //         // Store the access token in the session
    //         session(['access_token' => $accessToken->getToken()]);
    
    //         // Use the access token to make API requests
    //         $client = new Client();
    //         $parser = new Parser();
    //         $accessToken = session('access_token'); // Retrieve token from the session
    
    //         // Check if the OneDrive exists
    //         $driveResponse = $client->request('GET', 'https://graph.microsoft.com/v1.0/me/drive', [
    //             'headers' => [
    //                 'Authorization' => 'Bearer ' . $accessToken,
    //                 'Accept' => 'application/json',
    //             ]
    //         ]);
    
    //         if ($driveResponse->getStatusCode() == 200) {
    //             // Fetch the folder ID for "utility"
    //             $driveResponse = $client->request('GET', 'https://graph.microsoft.com/v1.0/me/drive/root/children', [
    //                 'headers' => [
    //                     'Authorization' => 'Bearer ' . $accessToken,
    //                     'Accept' => 'application/json',
    //                 ]
    //             ]);

    //             $driveData = json_decode((string) $driveResponse->getBody(), true);
    //             $utilityFolder = null;

    //             // Find the "utility" folder ID
    //             foreach ($driveData['value'] as $item) {
    //                 if ($item['name'] === 'utility' && $item['folder']) {
    //                     $utilityFolderId = $item['id'];
    //                     break;
    //                 }
    //             }

    //             if (!$utilityFolderId) {
    //                 return view('auth.callback', ['message' => 'Utility folder not found', 'status' => 'error']);
    //             }

    //             // List items in the "utility" folder
    //             $filesResponse = $client->request('GET', "https://graph.microsoft.com/v1.0/me/drive/items/$utilityFolderId/children", [
    //                 'headers' => [
    //                     'Authorization' => 'Bearer ' . $accessToken,
    //                     'Accept' => 'application/json',
    //                 ]
    //             ]);
               
    //             $filesData = json_decode((string) $filesResponse->getBody(), true);
               
    //             $fi = 0;
    //             $fileInfos = [];
    //             foreach ($filesData['value'] as $file) {
    //                 if (isset($file['file']) && strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) === 'pdf') {
    //                     // Fetch PDF content
    //                     $pdfContent = null;
    //                     $pdfFileResponse = $client->request('GET', "https://graph.microsoft.com/v1.0/me/drive/items/{$file['id']}/content", [
    //                         'headers' => [
    //                             'Authorization' => 'Bearer ' . $accessToken,
    //                             'Accept' => 'application/pdf',
    //                         ]
    //                     ]);
        
    //                     $pdfContentStream = $pdfFileResponse->getBody()->getContents();

    //                     try {
    //                         $pdf = $parser->parseContent($pdfContentStream);
    //                         $pdfContent = $pdf->getText();
    //                         $billing_pattern = '/Billing Address\s*:(.*?)Shipping Address\s*:/s';
  
    //                         if (preg_match($billing_pattern, $pdfContent, $matches)) {
    //                             $billingAddress = trim($matches[1]); 
                                
    //                             // Split the address by line breaks into an array
    //                             $addressLines = preg_split('/\r\n|\r|\n/', $billingAddress);
                            
    //                             // Initialize address columns
    //                             $address1 = isset($addressLines[0]) ? $addressLines[0] : '';
    //                             $address2 = isset($addressLines[1]) ? $addressLines[1] : '';
    //                             $address3 = isset($addressLines[2]) ? $addressLines[2] : '';
                                
    //                             // If there are more than 4 lines, concatenate the remaining lines in the 4th column
    //                             $address4 = isset($addressLines[3]) ? $addressLines[3] : '';
    //                             if (count($addressLines) > 4) {
    //                                 $remainingLines = array_slice($addressLines, 4);
    //                                 $address4 .= ' ' . implode(' ', $remainingLines);
    //                             }
                            
    //                         } 
    //                         $stateut = $this->extractField($pdfContent, 'State/UT Code');
    //                         $gstreg = $this->extractField($pdfContent, 'GST Registration No');
    //                         $placeOfSupply = $this->extractField($pdfContent, 'Place of supply');
    //                         $placeOfdelivery = $this->extractField($pdfContent, 'Place of delivery');
    //                         $invoice_details = $this->extractField($pdfContent, 'Invoice Details');
    //                         $invoice_date = $this->extractField($pdfContent, 'Invoice Date');
    //                         $invoice_number= $this->extractField($pdfContent, 'Invoice number');
    //                         $order_number= $this->extractFieldopt($pdfContent, 'Order Number');
    //                         $order_date= $this->extractFieldopt($pdfContent, 'Order Date');
    //                         $products = $this->extractProducts($pdfContent);
    //                         $productDetail = [];
    //                         foreach ($products as $product) {
    //                             //dd($product);
    //                             $description = $product['Description'];
    //                             $productDetail['sr'][] = $product['SlNo'];
    //                             $productDetail['description'][] = $this->getProduct($description);
    //                             $productDetail['unit_price'][] = $product['UnitPrice'];
    //                             $productDetail['qty'][] = $product['Qty'];
    //                         }
                           
    //                         $combinedProductDetails = [];
    //                         foreach ($productDetail['description'] as $index => $description) {
    //                             $combinedProductDetails[] = [
    //                                 'sr' => $productDetail['sr'][$index],
    //                                 'description' => $description,
    //                                 'unit_price' => $productDetail['unit_price'][$index],
    //                                 'qty' => $productDetail['qty'][$index]
    //                             ];
    //                         }
                            
    //                         $invoiceNo = $this->extractInvoiceNo($pdfContent);
    //                         $buyerSection = $this->extractBuyerSection($pdfContent);
    //                         $gstins = $this->extractGSTINsFromBuyerSection($buyerSection);
                            
    //                     } catch (\Exception $e) {
    //                         \Log::error('PDF parsing failed: ' . $e->getMessage());
    //                     }
        
    //                     $fileInfos[$fi] = [
    //                         'Order Number' => $order_number,
    //                         'Order Date' => $order_date,
    //                         'GST Registration No' => $gstreg,
    //                         'Address Line 1' => $address1,
    //                         'Address Line 2' => $address2,
    //                         'Address Line 3' => $address3,
    //                         'Address Line 4' => $address4,
    //                         'State/UT Code' => $stateut,
    //                         'Place of supply' => $placeOfSupply,
    //                         'Place of delivery' => $placeOfdelivery,
    //                         'Invoice Number' => $invoice_number,
    //                         'Invoice Details' => $invoice_details,
    //                         'Invoice Date' => $invoice_date,
    //                         'Attachment' => $file['@microsoft.graph.downloadUrl'],
    //                     ];
    //                     $p = 0;
                        
    //                     foreach ($combinedProductDetails as $product) {
    //                         $p++;
    //                         if($product['description'] == 'Not found'){
    //                             continue;
    //                          }
    //                         $fileInfos[$fi]['Name P' . $p] = $product['description'];
    //                         $fileInfos[$fi]['Unit Price P' . $p] = $product['unit_price'];
    //                         $fileInfos[$fi]['Qty P' . $p] = $product['qty'];
    //                         //\Log::info('Combined Product Details for File ' . $fi . ': ', $combinedProductDetails);

    //                     }
                       
    //                 }
    //                 $fi++;
    //             }
    //             $excelFileName = 'orders-data.xlsx';
    //             $excelFilePath = "/utility/$excelFileName";
        
    //             // Prepare the Excel file content
    //             $excelContent = Excel::raw(new FileInfoExport($fileInfos), \Maatwebsite\Excel\Excel::XLSX);

    //             // Upload the Excel file to the 'utility' folder
    //             $response = $client->request('PUT', "https://graph.microsoft.com/v1.0/me/drive/root:$excelFilePath:/content", [
    //                 'headers' => [
    //                     'Authorization' => 'Bearer ' . $accessToken,
    //                     'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    //                 ],
    //                 'body' => $excelContent
    //             ]);

    //             \Log::info('Excel file upload response: ' . $response->getBody());
    //             return view('auth.callback', ['message' => 'PDF files found', 'status' => 'success']);


    //         } else {
    //             return view('auth.callback', ['message' => 'OneDrive not found', 'status' => 'error']);
    //         }
    //     } catch (\Exception $e) {
    //         \Log::error('Authentication failed: ' . $e->getMessage());
    //         return view('auth.callback', ['message' => 'Authentication failed: ' . $e->getMessage(), 'status' => 'error']);
    //     }
    // }

    public function handleProviderCallback(Request $request)
    {
        \Log::info('Request state: ' . $request->input('state')); // Log incoming state for debugging
    
        $state = $request->input('state'); 
        $storedState = session('oauth2state'); 
    
        // Now, handle the code
        $code = $request->input('code');
        \Log::info('Request state: ' . $code);
    
        if (empty($code)) {
            return view('auth.callback', ['message' => 'Authorization code not found', 'status' => 'error']);
        }
    
        try {
            // Request access token
            $accessToken = $this->provider->getAccessToken('authorization_code', [
                'code' => $code,
                'redirect_uri' => env('MICROSOFT_REDIRECT_URL'),
                'scope' => ['User.Read', 'openid', 'profile', 'Files.ReadWrite']
            ]);
    
            // Store the access token in the session
            session(['access_token' => $accessToken->getToken()]);
    
            // Use the access token to make API requests
            $client = new Client();
            $parser = new Parser();
            $accessToken = session('access_token'); // Retrieve token from the session
    
            // Check if the OneDrive exists
            $driveResponse = $client->request('GET', 'https://graph.microsoft.com/v1.0/me/drive', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                ]
            ]);
    
            if ($driveResponse->getStatusCode() == 200) {
                // Fetch the folder ID for "invoices"
                $driveResponse = $client->request('GET', 'https://graph.microsoft.com/v1.0/me/drive/root/children', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Accept' => 'application/json',
                    ]
                ]);
            
                $driveData = json_decode((string) $driveResponse->getBody(), true);
            
                // Find the "invoices" folder ID
                foreach ($driveData['value'] as $item) {
                    if ($item['name'] === 'invoices' && $item['folder']) {
                        $utilityFolderId = $item['id'];
                        break;
                    }
                }
            
                if (!$utilityFolderId) {
                    return view('auth.callback', ['message' => 'Invoices folder not found', 'status' => 'error']);
                }
            
                $excelFileName = 'invoice-data.xlsx';
                $excelFilePath = "/invoices/$excelFileName";
            
                // Ensure file path does not contain null bytes
                if (strpos($excelFilePath, "\0") !== false) {
                    return view('auth.callback', ['message' => 'Invalid file path detected', 'status' => 'error']);
                }
            
                // Step 1: Check if the Excel file already exists and download it
                $existingData = [];
                try {
                    $fileResponse = $client->request('GET', "https://graph.microsoft.com/v1.0/me/drive/root:$excelFilePath:/content", [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $accessToken,
                            'Accept' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ]
                    ]);
            
                    $existingExcelContent = $fileResponse->getBody()->getContents();
            
                    // Create a temporary file to handle the content
                    $tempFile = tempnam(sys_get_temp_dir(), 'excel');
                    file_put_contents($tempFile, $existingExcelContent);
            
                    // Load the existing Excel file content
                    $existingData = Excel::toArray([], $tempFile);
                    $existingData = $existingData[0]; 
            
                    // Clean up the temporary file
                    unlink($tempFile);
                    if (!empty($existingData)) {
                        array_shift($existingData); // Remove the first row (header)
                    }
            
                } catch (\Exception $e) {
                    // If the file does not exist, proceed with an empty array
                    \Log::info('No existing Excel file found: ' . $e->getMessage());
                    $existingData = []; // Initialize an empty array if no data found
                }
            
                // Step 2: List items in the "invoices" folder and gather new data
                $filesResponse = $client->request('GET', "https://graph.microsoft.com/v1.0/me/drive/items/$utilityFolderId/children", [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Accept' => 'application/json',
                    ]
                ]);
            
                $filesData = json_decode((string) $filesResponse->getBody(), true);
                $fileInfos = [];
            
                foreach ($filesData['value'] as $file) {
                    // Check if file has been processed
                    $processedFile = DB::table('processed_files')->where('file_id', $file['id'])->first();
                    if ($processedFile) {
                        continue;
                    }
            
                    if (isset($file['file']) && strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) === 'pdf') {
                        $pdfFileResponse = $client->request('GET', "https://graph.microsoft.com/v1.0/me/drive/items/{$file['id']}/content", [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $accessToken,
                                'Accept' => 'application/pdf',
                            ]
                        ]);
            
                        $pdfContentStream = $pdfFileResponse->getBody()->getContents();
            
                        try {
                            $pdf = $parser->parseContent($pdfContentStream);
                            $pdfContent = $pdf->getText();
                            $invoiceNo = $this->extractInvoiceNo($pdfContent);
                            $buyerSection = $this->extractBuyerSection($pdfContent);
                            $gstins = $this->extractGSTINsFromBuyerSection($buyerSection);
            
                            $fileInfos[] = [
                                'Subject' => $invoiceNo,
                                'Bill to GST' => $gstins,
                                'Attachment' => $file['@microsoft.graph.downloadUrl'],
                                'Email Time' => $file['lastModifiedDateTime'],
                            ];
            
                            // Mark file as processed
                            DB::table('processed_files')->insert([
                                'file_id' => $file['id'],
                                'file_name' => $file['name'],
                                'processed_at' => now(),
                            ]);
            
                        } catch (\Exception $e) {
                            \Log::info('No existing Excel file found: ' . $e->getMessage());
                            return response()->json([
                                'status' => 'error',
                                'message' => 'PDF parsing failed:',
                                'statuscode' => 422,
                                'data' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            
                // Step 3: Merge new data with existing data
                $mergedData = array_merge($existingData, $fileInfos);
            
                // Step 4: Prepare the updated Excel file content
                $excelContent = Excel::raw(new FileInfoExport($mergedData), \Maatwebsite\Excel\Excel::XLSX);
            
                // Step 5: Upload the updated Excel file
                try {
                    $response = $client->request('PUT', "https://graph.microsoft.com/v1.0/me/drive/root:$excelFilePath:/content", [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $accessToken,
                            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ],
                        'body' => $excelContent
                    ]);

                    \Log::info('Excel file upload response: ' . $response->getBody());
                } catch (\Exception $e) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Error uploading Excel file',
                        'statuscode' => 422,
                        'data' => $e->getMessage(),
                    ]);
                }
                \Log::info('yoo,PDF files found and processed');

                return response()->json([
                    'status' => 'success',
                    'message' => 'PDF files found and processed',
                    'statuscode' => 422,
                    'data' => '',
                ]);
        
            }
            
             else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'OneDrive not found',
                    'statuscode' => 422,
                    'data' => '',
                ]);

            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication failed',
                'statuscode' => 422,
                'data' => $e->getMessage(),
            ]);
            
        }
    }

    protected function extractInvoiceNo($text)
    {
        // Use regular expressions or string manipulation to find the "Invoice No"
        if (preg_match('/Invoice\s*No[:\s]*([^\s]+)/i', $text, $matches)) {
            return $matches[1];
        }
        return 'Not found';
    }

    protected function extractBuyerSection($text)
    {
        // Define a regex pattern to match the section starting from "Buyer: Sold To/Billed To"
        $pattern = '/Buyer:\s*Sold\s*To\/Billed\s*To\s*\([\d]+\)[\s\S]*?(?=Buyer:|$)/i';
    
        // Initialize a variable to store the section text
        $buyerSection = '';
    
        // Use preg_match to find the buyer section
        if (preg_match($pattern, $text, $matches)) {
            $buyerSection = $matches[0];
        }
    
        return $buyerSection;
    }
    
    protected function extractGSTINsFromBuyerSection($buyerSection)
    {
        // Define a regex pattern to match GSTINs after "GSTN No:"
        $pattern = '/GSTN\s*No\s*:\s*([A-Z0-9]{15})/i';
    
        // Use preg_match_all to find all matches
        if (preg_match($pattern, $buyerSection, $matches)) {
            return $matches[1];
        }
    
    }
    
    protected function extractField($pdfContent, $fieldName) {
        $pattern = '/' . preg_quote($fieldName, '/') . '\s*:\s*(.+)/i';
        if (preg_match($pattern, $pdfContent, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    protected function extractFieldopt($pdfContent, $fieldName) {
        $pattern = '/' . preg_quote($fieldName, '/') . '\s*[:\t]\s*(.+?)(?=\s*\n|\t|$)/i';
    
        if (preg_match($pattern, $pdfContent, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
    
   
    protected function extractProducts($pdfContent) {
        // This regex assumes each product block starts with a number (e.g., 1, 2) and contains HSN, price, etc.
        $pattern = '/
                        (?P<SlNo>\d+)\s+                           # Capture Serial Number
                        (?P<Description>.+?)\s+                    # Capture Description (Non-greedy to stop at Unit Price)
                        ₹(?P<UnitPrice>\d+[\d,.]*)\s*              # Capture Unit Price (with ₹ symbol)
                        (?P<Qty>\d+)\s*                            # Capture Quantity
                /isx';

    // Apply the pattern to the PDF content
    preg_match_all($pattern, $pdfContent, $matches, PREG_SET_ORDER);

    $products = [];

    foreach ($matches as $match) {
        $products[] = [
            'SlNo' => $match['SlNo'],                // Serial number
            'Description' => trim($match['Description']), // Description
            'UnitPrice' => $match['UnitPrice'],      // Unit price
            'Qty' => $match['Qty'],                  // Quantity
        ];
    }

    return $products;
    }

    protected function getProduct($pdfContent) {
        // This regex assumes each product block starts with a number (e.g., 1, 2) and contains HSN, price, etc.
        $pattern = '';

        if (preg_match('/\d+\s+([A-Z0-9\s\w\&\(\)\|,-]+)\|[^\d]+\(/is', $pdfContent, $matches)) {
            return $matches[1];
        }
        return 'Not found';
    }
}
