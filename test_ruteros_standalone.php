<?php

// Standalone script to test getRuteros SOAP endpoint
// Run with: php test_ruteros_standalone.php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$document = $argv[1] ?? '12345678'; // Default test document
$zone = $argv[2] ?? ''; // Optional zone
$token = $argv[3] ?? null; // Optional token

echo "Testing getRuteros for document: {$document}" . ($zone ? " with zone: {$zone}" : '') . "\n";

if (!$token) {
    echo "No token provided. Please provide a Microsoft token as the 3rd argument.\n";
    echo "Usage: php test_ruteros_standalone.php [document] [zone] [token]\n";
    echo "\n";
    echo "You can get a token by running the Laravel command: php artisan app:get-token\n";
    echo "Then extract the token from the output and pass it here.\n";
    exit(1);
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

echo "SOAP Request Body:\n";
echo $body . "\n\n";

$resourceUrl = getenv('MICROSOFT_RESOURCE_URL') ?: 'https://your-dynamics-instance.com'; // Replace with actual URL

if (empty($resourceUrl) || $resourceUrl === 'https://your-dynamics-instance.com') {
    echo "Please set MICROSOFT_RESOURCE_URL environment variable or edit the script with the correct URL.\n";
    exit(1);
}

echo "Making request to: {$resourceUrl}/soap/services/DIITDWSSalesForceGroup\n";

try {
    $response = Http::withHeaders([
        'Content-Type' => 'text/xml;charset=UTF-8',
        'SOAPAction' => 'http://tempuri.org/DWSSalesForce/getRuteros',
        'Authorization' => "Bearer {$token}"
    ])->send('POST', $resourceUrl . '/soap/services/DIITDWSSalesForceGroup', [
        'body' => $body
    ]);

    echo "Response Status: {$response->status()}\n";

    if ($response->successful()) {
        $data = $response->body();

        echo "Raw XML Response:\n";
        echo $data . "\n\n";

        // Parse XML
        $xmlString = preg_replace('/<(\/)?(s|a):/', '<$1$2', $data);
        $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml) {
            echo "Parsed XML Structure:\n";
            displayXmlStructure($xml);
            echo "\n";

            // Try to extract the data like the current implementation
            try {
                $addresses = $xml->sBody->getRuterosResponse->result->agetRuterosResult;

                if ($addresses) {
                    $json = json_encode($addresses);
                    $array = json_decode($json, TRUE);

                    echo "JSON Array Structure:\n";
                    displayArrayStructure($array);
                    echo "\n";

                    if (isset($array['aListRuteros'])) {
                        echo "Current Implementation Data Extraction:\n";

                        if (isset($array['aListRuteros']['aDetail']['aListDetailsRuteros'])) {
                            $details = $array['aListRuteros']['aDetail']['aListDetailsRuteros'];

                            // Handle both single item and array of items
                            if (isset($details[0])) {
                                foreach ($details as $index => $detail) {
                                    echo "Item #{$index}:\n";
                                    displayAvailableFields($detail);
                                }
                            } else {
                                echo "Single Item:\n";
                                displayAvailableFields($details);
                            }
                        } else {
                            echo "No aDetail/aListDetailsRuteros found in response\n";
                        }
                    } else {
                        echo "No aListRuteros found in response\n";
                    }
                } else {
                    echo "Could not extract addresses from XML\n";
                }
            } catch (\Throwable $th) {
                echo "Error parsing response: " . $th->getMessage() . "\n";
            }
        } else {
            echo "Failed to parse XML response\n";
        }
    } else {
        echo "Request failed with status {$response->status()}\n";
        echo "Response: " . $response->body() . "\n";
    }
} catch (\Throwable $th) {
    echo "Request failed: " . $th->getMessage() . "\n";
}

function displayXmlStructure($xml, $level = 0)
{
    $indent = str_repeat('  ', $level);

    if ($xml->count() > 0) {
        foreach ($xml as $key => $value) {
            if ($value->count() > 0 || (string)$value !== '') {
                echo "{$indent}{$key}:\n";
                displayXmlStructure($value, $level + 1);
            } else {
                echo "{$indent}{$key}: " . (string)$value . "\n";
            }
        }
    } else {
        echo "{$indent}(value): " . (string)$xml . "\n";
    }
}

function displayArrayStructure($array, $level = 0)
{
    $indent = str_repeat('  ', $level);

    foreach ($array as $key => $value) {
        if (is_array($value)) {
            echo "{$indent}{$key}:\n";
            displayArrayStructure($value, $level + 1);
        } else {
            echo "{$indent}{$key}: {$value}\n";
        }
    }
}

function displayAvailableFields($detail)
{
    echo "Available fields in aListDetailsRuteros:\n";

    foreach ($detail as $key => $value) {
        echo "  {$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
    }

    echo "\n";

    // Show current mapping
    echo "Current field mapping:\n";
    echo "  aCustRuteroID -> code: " . ($detail['aCustRuteroID'] ?? 'N/A') . "\n";
    echo "  aAddress -> address: " . ($detail['aAddress'] ?? 'N/A') . "\n";
    echo "  aName -> name: " . ($detail['aName'] ?? 'N/A') . "\n";
    echo "\n";
}
