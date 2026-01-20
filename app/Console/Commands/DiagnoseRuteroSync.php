<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Setting;
use Illuminate\Support\Facades\Artisan;

class DiagnoseRuteroSync extends Command
{
    protected $signature = 'diagnose:rutero {document}';
    protected $description = 'Diagnose rutero sync for a specific client document';

    public function handle()
    {
        $document = $this->argument('document');
        
        $this->info("╔════════════════════════════════════════════════════════════════════════════════╗");
        $this->info("║  RUTERO SYNC DIAGNOSTIC FOR DOCUMENT: {$document}");
        $this->info("╚════════════════════════════════════════════════════════════════════════════════╝");
        $this->newLine();

        // 1. Check if user exists in Tuti
        $user = User::where('document', $document)->first();
        
        if ($user) {
            $this->info("✓ User found in Tuti database");
            $this->info("  User ID: {$user->id}");
            $this->info("  Name: {$user->name}");
            $this->info("  Email: {$user->email}");
            $this->newLine();
            
            $this->info("Current zones in Tuti:");
            $zones = $user->zones()->orderBy('id')->get();
            if ($zones->count() > 0) {
                foreach ($zones as $zone) {
                    $this->line("  - Zone ID {$zone->id}: Zone {$zone->zone}, Route {$zone->route}, Day {$zone->day}");
                    $this->line("    Address: {$zone->address}");
                    $this->line("    Code: {$zone->code}");
                }
            } else {
                $this->warn("  No zones found for this user");
            }
        } else {
            $this->warn("✗ User not found in Tuti database");
        }
        
        $this->newLine();
        $this->info("─────────────────────────────────────────────────────────────────────────────────");
        $this->info("Fetching raw SOAP response from Dynamics...");
        $this->info("─────────────────────────────────────────────────────────────────────────────────");
        $this->newLine();

        // 2. Fetch raw SOAP response
        $token = Setting::where('key', 'microsoft_token')->first();
        
        if ($token->updated_at->diffInMinutes(now()) > 25) {
            Artisan::call('app:get-token');
            $token = Setting::where('key', 'microsoft_token')->first();
        }
        
        $token = $token->value;
        
        $body = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dat="http://schemas.microsoft.com/dynamics/2013/01/datacontracts" xmlns:tem="http://tempuri.org" xmlns:dyn="http://schemas.datacontract.org/2004/07/Dynamics.AX.Application">
            <soapenv:Header>
                <dat:CallContext>
                    <dat:Company>TRX</dat:Company>
                </dat:CallContext>
            </soapenv:Header>
            <soapenv:Body>
                <tem:getRuteros>
                    <tem:_getRuteros>
                        <dyn:IdentificationNum>' . $document . '</dyn:IdentificationNum>
                        <dyn:ruteroId></dyn:ruteroId>
                        <dyn:zona></dyn:zona>
                    </tem:_getRuteros>
                </tem:getRuteros>
            </soapenv:Body>
            </soapenv:Envelope>';

        $resourceUrl = config('microsoft.resource');
        $response = Http::withHeaders([
            'Content-Type' => 'text/xml;charset=UTF-8',
            'SOAPAction' => 'http://tempuri.org/DWSSalesForce/getRuteros',
            'Authorization' => "Bearer {$token}"
        ])->send('POST', $resourceUrl . '/soap/services/DIITDWSSalesForceGroup', [
            'body' => $body
        ]);

        $data = $response->body();
        $xmlString = preg_replace('/<(\/)?(s|a):/', '<$1$2', $data);
        $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

        try {
            $addresses = $xml->sBody->getRuterosResponse->result->agetRuterosResult;
            $json = json_encode($addresses);
            $array = json_decode($json, TRUE);
            
            $this->info("Raw SOAP response structure:");
            $this->line(json_encode($array, JSON_PRETTY_PRINT));
            $this->newLine();
            
            $aListRuteros = $array['aListRuteros'];
            
            $this->info("─────────────────────────────────────────────────────────────────────────────────");
            $this->info("Analyzing aListRuteros structure:");
            $this->info("─────────────────────────────────────────────────────────────────────────────────");
            $this->newLine();
            
            // Check if aListRuteros is an array of routes or a single route
            $isMultipleRoutes = isset($aListRuteros[0]);
            
            if ($isMultipleRoutes) {
                $this->info("✓ Structure: MULTIPLE routes detected (indexed array)");
                $this->info("  Number of routes: " . count($aListRuteros));
                $this->newLine();
                
                foreach ($aListRuteros as $index => $rutero) {
                    $this->info("Route #{$index}:");
                    $this->line("  Zone: " . ($rutero['aZona'] ?? 'N/A'));
                    $this->line("  Route: " . ($rutero['aRoute'] ?? 'N/A'));
                    $this->line("  Day: " . ($rutero['aDiaRecorrido'] ?? 'N/A'));
                    
                    if (isset($rutero['aDetail']['aListDetailsRuteros'])) {
                        $details = $rutero['aDetail']['aListDetailsRuteros'];
                        if (isset($details['aAddress'])) {
                            $this->line("  Address: " . $details['aAddress']);
                        }
                        if (isset($details['aCustRuteroID'])) {
                            $this->line("  Code: " . $details['aCustRuteroID']);
                        }
                    }
                    $this->newLine();
                }
            } else {
                $this->warn("✗ Structure: SINGLE route detected (associative array)");
                $this->line("  Zone: " . ($aListRuteros['aZona'] ?? 'N/A'));
                $this->line("  Route: " . ($aListRuteros['aRoute'] ?? 'N/A'));
                $this->line("  Day: " . ($aListRuteros['aDiaRecorrido'] ?? 'N/A'));
                $this->newLine();
            }
            
        } catch (\Throwable $th) {
            $this->error("Error parsing SOAP response: " . $th->getMessage());
            return 1;
        }

        $this->newLine();
        $this->info("─────────────────────────────────────────────────────────────────────────────────");
        $this->info("Testing getCustomRuteroId() method:");
        $this->info("─────────────────────────────────────────────────────────────────────────────────");
        $this->newLine();

        $ruteroData = UserRepository::getCustomRuteroId($document);
        
        if ($ruteroData) {
            $this->info("✓ getCustomRuteroId returned data");
            $this->info("  Number of routes returned: " . count($ruteroData['routes']));
            $this->newLine();
            
            foreach ($ruteroData['routes'] as $index => $route) {
                $this->info("Processed Route #{$index}:");
                $this->line("  Zone: " . ($route['zone'] ?? 'N/A'));
                $this->line("  Route: " . ($route['route'] ?? 'N/A'));
                $this->line("  Day: " . ($route['day'] ?? 'N/A'));
                $this->line("  Address: " . ($route['address'] ?? 'N/A'));
                $this->line("  Code: " . ($route['code'] ?? 'N/A'));
                $this->newLine();
            }
        } else {
            $this->error("✗ getCustomRuteroId returned null");
        }

        return 0;
    }
}
