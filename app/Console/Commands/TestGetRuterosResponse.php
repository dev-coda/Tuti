<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class TestGetRuterosResponse extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:get-ruteros-response {document} {zone?} {--token= : Microsoft token (optional, will try to get from DB)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the getRuteros SOAP endpoint and display the full response structure';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $document = $this->argument('document');
        $zone = $this->argument('zone') ?? '';

        $this->info("Testing getRuteros for document: {$document}" . ($zone ? " with zone: {$zone}" : ''));

        // Get token
        $token = $this->option('token');

        if (!$token) {
            try {
                $tokenSetting = Setting::where('key', 'microsoft_token')->first();

                if (!$tokenSetting || $tokenSetting->updated_at->diffInMinutes(now()) > 25) {
                    $this->info('Refreshing Microsoft token...');
                    Artisan::call('app:get-token');
                    $tokenSetting = Setting::where('key', 'microsoft_token')->first();
                }

                if (!$tokenSetting) {
                    $this->error('No Microsoft token available in database. Please provide --token option or ensure database is configured.');
                    return 1;
                }

                $token = $tokenSetting->value;
            } catch (\Exception $e) {
                $this->error('Database connection failed. Please provide --token option with a valid Microsoft token.');
                $this->error('Error: ' . $e->getMessage());
                return 1;
            }
        }

        // Build SOAP request
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
                        <dyn:zona>' . $zone . '</dyn:zona>
                    </tem:_getRuteros>
                </tem:getRuteros>
            </soapenv:Body>
            </soapenv:Envelope>';

        $this->info('SOAP Request Body:');
        $this->line($body);
        $this->newLine();

        $resourceUrl = config('microsoft.resource');

        if (empty($resourceUrl)) {
            $this->error('Microsoft resource URL is not configured');
            return 1;
        }

        $this->info("Making request to: {$resourceUrl}/soap/services/DIITDWSSalesForceGroup");

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml;charset=UTF-8',
                'SOAPAction' => 'http://tempuri.org/DWSSalesForce/getRuteros',
                'Authorization' => "Bearer {$token}"
            ])->send('POST', $resourceUrl . '/soap/services/DIITDWSSalesForceGroup', [
                'body' => $body
            ]);

            $this->info("Response Status: {$response->status()}");

            if ($response->successful()) {
                $data = $response->body();

                $this->info('Raw XML Response:');
                $this->line($data);
                $this->newLine();

                // Parse XML
                $xmlString = preg_replace('/<(\/)?(s|a):/', '<$1$2', $data);
                $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

                if ($xml) {
                    $this->info('Parsed XML Structure:');
                    $this->displayXmlStructure($xml);
                    $this->newLine();

                    // Try to extract the data like the current implementation
                    try {
                        $addresses = $xml->sBody->getRuterosResponse->result->agetRuterosResult;

                        if ($addresses) {
                            $json = json_encode($addresses);
                            $array = json_decode($json, TRUE);

                            $this->info('JSON Array Structure:');
                            $this->displayArrayStructure($array);
                            $this->newLine();

                            if (isset($array['aListRuteros'])) {
                                $this->info('Current Implementation Data Extraction:');

                                if (isset($array['aListRuteros']['aDetail']['aListDetailsRuteros'])) {
                                    $details = $array['aListRuteros']['aDetail']['aListDetailsRuteros'];

                                    // Handle both single item and array of items
                                    if (isset($details[0])) {
                                        foreach ($details as $index => $detail) {
                                            $this->info("Item #{$index}:");
                                            $this->displayAvailableFields($detail);
                                        }
                                    } else {
                                        $this->info('Single Item:');
                                        $this->displayAvailableFields($details);
                                    }
                                } else {
                                    $this->warn('No aDetail/aListDetailsRuteros found in response');
                                }
                            } else {
                                $this->warn('No aListRuteros found in response');
                            }
                        } else {
                            $this->warn('Could not extract addresses from XML');
                        }
                    } catch (\Throwable $th) {
                        $this->error('Error parsing response: ' . $th->getMessage());
                    }
                } else {
                    $this->error('Failed to parse XML response');
                }
            } else {
                $this->error("Request failed with status {$response->status()}");
                $this->error('Response: ' . $response->body());
            }
        } catch (\Throwable $th) {
            $this->error('Request failed: ' . $th->getMessage());
        }

        return 0;
    }

    private function displayXmlStructure($xml, $level = 0)
    {
        $indent = str_repeat('  ', $level);

        if ($xml->count() > 0) {
            foreach ($xml as $key => $value) {
                if ($value->count() > 0 || (string)$value !== '') {
                    $this->line("{$indent}{$key}:");
                    $this->displayXmlStructure($value, $level + 1);
                } else {
                    $this->line("{$indent}{$key}: " . (string)$value);
                }
            }
        } else {
            $this->line("{$indent}(value): " . (string)$xml);
        }
    }

    private function displayArrayStructure($array, $level = 0)
    {
        $indent = str_repeat('  ', $level);

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $this->line("{$indent}{$key}:");
                $this->displayArrayStructure($value, $level + 1);
            } else {
                $this->line("{$indent}{$key}: {$value}");
            }
        }
    }

    private function displayAvailableFields($detail)
    {
        $this->info('Available fields in aListDetailsRuteros:');

        foreach ($detail as $key => $value) {
            $this->line("  {$key}: " . (is_array($value) ? json_encode($value) : $value));
        }

        $this->newLine();

        // Show current mapping
        $this->info('Current field mapping:');
        $this->line("  aCustRuteroID -> code: " . ($detail['aCustRuteroID'] ?? 'N/A'));
        $this->line("  aAddress -> address: " . ($detail['aAddress'] ?? 'N/A'));
        $this->line("  aName -> name: " . ($detail['aName'] ?? 'N/A'));
    }
}
