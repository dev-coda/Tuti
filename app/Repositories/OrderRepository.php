<?php

namespace App\Repositories;

use App\Models\Holiday;
use App\Models\Order;
use App\Models\Setting;
use App\Models\RouteCycle;
use App\Models\DeliveryCalendar;
use App\Models\Zone;
use App\Jobs\SendOrderEmail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OrderRepository
{
    public static function presalesOrder($order)
    {
        Log::channel('soap')->info('Starting presalesOrder process', [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'total' => $order->total,
            'zone_id' => $order->zone_id
        ]);

        self::sendData(order: $order, products: $order->products, bonification: 0);
        if ($order->bonifications->count()) {
            Log::channel('soap')->info('Processing bonifications for order', [
                'order_id' => $order->id,
                'bonifications_count' => $order->bonifications->count()
            ]);

            $orderBonification = Order::create([
                'user_id' => $order->user_id,
                'total' => 0,
                'discount' => 0,
                'zone_id' => $order->zone_id,
                'seller_id' => $order->seller_id,
                'delivery_date' => $order->delivery_date,
                'observations' => 'Bonificaciones',
            ]);
            self::sendData(order: $orderBonification, products: $order->bonifications, bonification: 1);
        }
    }

    private static function sendData($order, $products, $bonification = 0)
    {
        $startTime = microtime(true);
        Log::channel('soap')->info('Starting SOAP request', [
            'order_id' => $order->id,
            'bonification' => $bonification,
            'products_count' => $products->count()
        ]);

        // Load only necessary relationships
        $user = $order->user;
        $zone = $order->zone;

        // Remove the heavy eager loading that was causing performance issues
        // $order->load('products.product.brand.vendor');

        $order->update([
            'request' => $zone,
        ]);

        $delivery_date = $order->delivery_date;
        $observations = $order->observations;

        $day = $zone->day;
        $route = $zone->route;
        $code = $zone->code;
        $zone = $zone->zone;

        $productList = '';

        // Optimize product loading - load all necessary data in one query
        $productIds = $products->pluck('product_id')->toArray();
        $variationIds = $products->pluck('variation_item_id')->filter()->toArray();

        // Load all products with their relationships in one query
        $productsData = \App\Models\Product::whereIn('id', $productIds)
            ->with('brand.vendor')
            ->get()
            ->keyBy('id');

        // Load all variation SKUs in one query if needed
        $variationSkus = [];
        if (!empty($variationIds)) {
            // Create a collection of product-variation combinations from the order
            $productVariationCombinations = $products->filter(function ($product) {
                return !is_null($product->variation_item_id);
            })->map(function ($product) {
                return [
                    'product_id' => $product->product_id,
                    'variation_item_id' => $product->variation_item_id
                ];
            });

            // Get all unique product IDs that have variations
            $productIdsWithVariations = $productVariationCombinations->pluck('product_id')->unique()->toArray();

            // Load variation SKUs for all product-variation combinations
            $variationSkuData = DB::table('product_item_variation')
                ->whereIn('variation_item_id', $variationIds)
                ->whereIn('product_id', $productIdsWithVariations)
                ->select('product_id', 'variation_item_id', 'sku')
                ->get();

            // Create a lookup array using composite key: product_id-variation_item_id
            foreach ($variationSkuData as $item) {
                $compositeKey = $item->product_id . '-' . $item->variation_item_id;
                $variationSkus[$compositeKey] = $item->sku;
            }

            Log::channel('soap')->info('Loaded variation SKUs', [
                'variation_ids' => $variationIds,
                'product_ids_with_variations' => $productIdsWithVariations,
                'variation_skus' => $variationSkus,
                'count' => count($variationSkus)
            ]);
        }

        foreach ($products as $product) {
            $productData = $productsData[$product->product_id] ?? null;

            if (!$productData) {
                Log::channel('soap')->warning('Product not found', [
                    'product_id' => $product->product_id,
                    'order_id' => $order->id
                ]);
                continue;
            }

            $vendor_type = $productData->brand->vendor->vendor_type;

            // Handle package calculation differently for bonifications vs regular products
            if ($bonification) {
                // For bonifications: always send the exact quantity specified, never multiply by package_quantity
                // Bonifications are always individual items, not package units
                $effectivePackageQuantity = 1;
                $unitPrice = 0; // Bonifications always have 0 price
            } else {
                // For regular products, use the order product's package quantity
                $effectivePackageQuantity = $productData->calculate_package_price ? $product->package_quantity : 1;
                $unitPrice = $effectivePackageQuantity ? parseCurrency($product->price / $effectivePackageQuantity) : parseCurrency($product->price);
            }

            // Use cached data instead of making individual queries
            $sku = $productData->sku;
            if ($product->variation_item_id && isset($variationSkus[$product->product_id . '-' . $product->variation_item_id])) {
                $sku = $variationSkus[$product->product_id . '-' . $product->variation_item_id];
                Log::channel('soap')->info('Using variation SKU', [
                    'product_id' => $product->product_id,
                    'variation_item_id' => $product->variation_item_id,
                    'variation_sku' => $sku,
                    'original_product_sku' => $productData->sku
                ]);
            } else {
                Log::channel('soap')->info('Using product SKU', [
                    'product_id' => $product->product_id,
                    'variation_item_id' => $product->variation_item_id,
                    'product_sku' => $sku,
                    'has_variation_id' => !empty($product->variation_item_id),
                    'variation_sku_exists' => $product->variation_item_id ? isset($variationSkus[$product->product_id . '-' . $product->variation_item_id]) : false
                ]);
            }

            // Calculate quantity with proper fallback handling
            // For bonifications, qty is always the exact number specified (not multiplied by package_quantity)
            $qty = $bonification ? $product->quantity : ($effectivePackageQuantity ? $product->quantity * $effectivePackageQuantity : $product->quantity);

            // Add logging for bonification quantity debugging
            if ($bonification) {
                Log::channel('soap')->info('Bonification quantity calculation', [
                    'order_id' => $order->id,
                    'product_id' => $product->product_id,
                    'bonification_quantity' => $product->quantity,
                    'effective_package_quantity' => $effectivePackageQuantity,
                    'final_qty' => $qty,
                    'product_calculate_package_price' => $productData->calculate_package_price,
                    'product_package_quantity' => $productData->package_quantity ?? 'null'
                ]);
            }
            $productList .= '<dyn:listDetails>
                            <dyn:discount>' . (int) $product->percentage . '</dyn:discount>
                            <dyn:itemId>' . $sku . '</dyn:itemId>
                            <dyn:qty>' . $qty . '</dyn:qty>
                            <dyn:qtyCust>' . $qty . '</dyn:qtyCust>
                            <dyn:um>Unidad</dyn:um>
                            <dyn:umCust>None</dyn:umCust>
                            <dyn:unitPrice>' . $unitPrice . '</dyn:unitPrice>
                            <dyn:vendorType>' . $vendor_type . '</dyn:vendorType>
                        </dyn:listDetails>';
        }

        $order_id = $order->id;
        $transactionDate = $order->created_at->format('Y-m-d');

        $body = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dat="http://schemas.microsoft.com/dynamics/2013/01/datacontracts" xmlns:tem="http://tempuri.org" xmlns:dyn="http://schemas.datacontract.org/2004/07/Dynamics.AX.Application">
            <soapenv:Header>
                <dat:CallContext>
                    <dat:Company>trx</dat:Company>
                    
                    <dat:Language/>
                    <dat:MessageId/>
                    <dat:PartitionKey/>
                </dat:CallContext>
            </soapenv:Header>
            <soapenv:Body>
                <tem:PreSaslesProcess>
                    <tem:ArrayOfPreSalesOrder>
                        <dyn:preSalesOrder>
                            <dyn:TRO_E_obsequio>' . $bonification . '</dyn:TRO_E_obsequio> 
                            <dyn:codCustomer>' . $code . '</dyn:codCustomer> 
                            <dyn:deliveryDate>' . $delivery_date . '</dyn:deliveryDate>
                            <dyn:diaRecorrido>' . $day . '</dyn:diaRecorrido>
                            <dyn:listDetails>
                                <!--Zero or more repetitions:-->
                                ' . $productList . '
                            </dyn:listDetails>
                            <dyn:orderSales>' . $order_id . '</dyn:orderSales>
                            <dyn:ruta>' . $route . '</dyn:ruta> 
                            <dyn:salesCons>' . $zone . '-' . $order_id . '</dyn:salesCons> 
                            <dyn:transactionDate>' . $transactionDate . '</dyn:transactionDate>
                            <dyn:tutiObservation>' . $observations . '</dyn:tutiObservation>
                            <dyn:vendorType>' . $vendor_type . '</dyn:vendorType>
                            <dyn:zona>' . $zone . '</dyn:zona>                    
                        </dyn:preSalesOrder>
                    </tem:ArrayOfPreSalesOrder>
                </tem:PreSaslesProcess>
            </soapenv:Body>
        </soapenv:Envelope>';

        $token = Setting::getByKey('microsoft_token');
        $resource_url = config('microsoft.resource');

        Log::channel('soap')->info('Sending SOAP request', [
            'order_id' => $order_id,
            'url' => $resource_url . '/soap/services/DIITDWSSalesForceGroup?=null',
            'zone' => $zone,
            'code' => $code,
            'delivery_date' => $delivery_date
        ]);

        try {
            // Add more detailed timing logs
            $httpStartTime = microtime(true);

            $response = Http::withHeaders([
                'Content-Type' => 'text/xml;charset=UTF-8',
                'SOAPAction' => 'http://tempuri.org/DWSSalesForce/PreSaslesProcess',
                'Authorization' => "Bearer {$token}"
            ])
                ->timeout(30) // Set timeout to 30 seconds
                ->connectTimeout(5) // Add connection timeout
                ->withOptions([
                    'verify' => false, // Disable SSL verification if not needed (for internal APIs)
                    'http_errors' => false,
                    'connect_timeout' => 5,
                    'timeout' => 30,
                ])
                ->send('POST', $resource_url . '/soap/services/DIITDWSSalesForceGroup?=null', [
                    'body' => $body
                ]);

            $httpEndTime = microtime(true);
            $httpTime = round($httpEndTime - $httpStartTime, 2);

            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);

            Log::channel('soap')->info('SOAP request completed', [
                'order_id' => $order_id,
                'total_execution_time' => $executionTime . ' seconds',
                'http_request_time' => $httpTime . ' seconds',
                'preparation_time' => round($httpStartTime - $startTime, 2) . ' seconds',
                'status_code' => $response->status(),
                'successful' => $response->successful()
            ]);

            $data = $response->body();
            $xmlString = preg_replace('/<(\/)?(s|a):/', '<$1$2', $data);
            $xml = simplexml_load_string($xmlString);

            try {
                $response = $xml->sBody->PreSaslesProcessResponse->result->aPreSaslesProcessResult;

                if ($response == 'OK') {
                    Log::channel('soap')->info('SOAP request successful - Order processed', [
                        'order_id' => $order_id,
                        'response' => 'OK',
                        'execution_time' => $executionTime . ' seconds'
                    ]);

                    // Update order status without triggering email events during XML transmission
                    $order->withoutEvents(function () use ($order, $body, $response) {
                        $order->update([
                            'status_id' => Order::STATUS_PROCESSED,
                            'request' => $body,
                            'response' => $response
                        ]);
                    });

                    // Email dispatching is handled in the controller after successful response
                    // This ensures emails don't block the XML transmission process
                } else {
                    Log::channel('soap')->warning('SOAP request returned error response', [
                        'order_id' => $order_id,
                        'response' => (string)$response,
                        'execution_time' => $executionTime . ' seconds'
                    ]);

                    // Update order status without triggering email events during XML transmission
                    $order->withoutEvents(function () use ($order, $body, $response) {
                        $order->update([
                            'status_id' => Order::STATUS_ERROR,
                            'request' => $body,
                            'response' => $response
                        ]);
                    });
                }
            } catch (\Exception $e) {
                Log::channel('soap')->error('Error parsing SOAP response', [
                    'order_id' => $order_id,
                    'error' => $e->getMessage(),
                    'response_body' => substr($data, 0, 1000), // Log first 1000 chars of response
                    'execution_time' => $executionTime . ' seconds'
                ]);

                // Update order status without triggering email events during XML transmission
                $order->withoutEvents(function () use ($order, $body, $data) {
                    $order->update([
                        'status_id' => Order::STATUS_ERROR_WEBSERVICE,
                        'request' => $body,
                        'response' => $data
                    ]);
                });
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);

            Log::channel('soap')->error('SOAP request timeout or connection error', [
                'order_id' => $order_id,
                'error' => $e->getMessage(),
                'execution_time' => $executionTime . ' seconds',
                'timeout' => true
            ]);

            // Update order status without triggering email events during XML transmission
            $order->withoutEvents(function () use ($order, $body, $e) {
                $order->update([
                    'status_id' => Order::STATUS_ERROR_WEBSERVICE,
                    'request' => $body,
                    'response' => 'Timeout or connection error: ' . $e->getMessage()
                ]);
            });

            throw $e;
        } catch (\Exception $e) {
            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);

            Log::channel('soap')->error('Unexpected error during SOAP request', [
                'order_id' => $order_id,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'execution_time' => $executionTime . ' seconds'
            ]);

            $order->update([
                'status_id' => Order::STATUS_ERROR_WEBSERVICE,
                'request' => $body,
                'response' => 'Error: ' . $e->getMessage()
            ]);

            throw $e;
        }
    }

    public static function isBussinessDay($date)
    {


        //if is sunday or saturday
        $response = false;

        //check if holiday 
        $holiday = Holiday::whereDate('date', $date)->whereTypeId(Holiday::HOLIDAY)->exists();
        if ($holiday) {
            return false;
        }

        // //check if saturday
        $saturday = Holiday::whereDate('date', $date)->whereTypeId(Holiday::SATURDAY)->exists();
        if ($saturday) {
            $response = true;
        }

        //if is weekday
        if ($date->isWeekday()) {
            $response = true;
        }
        return $response;
    }

    /**
     * Calculate a business day N days ahead from today
     * 
     * @param int $daysAhead Number of business days ahead (0 = next business day, 1 = 2 business days ahead, etc.)
     * @return string Date in Y-m-d format
     */
    public static function getBusinessDay($daysAhead = 0)
    {
        // Get current time adjusted for timezone (subtract 5 hours for Colombia timezone)
        $now = now();
        $hour = $now->copy()->subHours(5)->hour;
        $closing_time = (int) Setting::getByKey('closing_time');

        // If current hour is after closing time, start from tomorrow
        if ($closing_time <= $hour) {
            $now = now()->addDay();
        } else {
            $now = now();
        }

        // Find the required number of business days ahead
        $i = 0;
        $businessDaysFound = 0;
        while (true) {
            $now = $now->addDay();

            $i++;
            if ($i > 30) { // Safety limit to prevent infinite loops
                break;
            }

            if (self::isBussinessDay($now)) {
                $businessDaysFound++;
                // If we've found enough business days (daysAhead + 1), we're done
                // daysAhead=0 means we want the next business day (1 business day found)
                // daysAhead=1 means we want 2 business days ahead (2 business days found)
                if ($businessDaysFound > $daysAhead) {
                    break;
                }
            }
        }

        return $now->format('Y-m-d');
    }

    /**
     * OLD IMPLEMENTATION - REMOVED (dead code)
     * This code was unreachable and has been removed.
     * The current implementation uses getBusinessDay() above.
     */
    /*
        //     $now = now();

        //     //current hour
        //     $hour = $now->hour;
        //     $closing_time = (int)Setting::getByKey('closing_time');

        //     if($closing_time >= $hour){
        //         //add day to $now
        //         $now = now()->addDay();
        //     }
        // //    $now = now()->addDays(2);

        //   //dd($now->isSaturday());   
        //     //check if this date is saturday
        //     if($now->isSaturday()){
        //         //add 2 days to $now
        //         //check is saturday is in holidays
        //         $saturday = Holiday::whereDate('date', $now)->first();            
        //         if($saturday){
        //             $now = $saturday->date->addDays(2);
        //         }
        //     }

        //     //cecck if this date is holiday
        //     $holiday = Holiday::whereDate('date', $now)->first();
        //     if($holiday){
        //         $now = $holiday->date->addDay();
        //     }

        //     dd($now);

        //     return $now;



        $closing_time = (int) Setting::getByKey('closing_time');

        $next_business_day = now(); // Inicialmente, sumamos un día

        // Si la hora actual es mayor a la hora de cierre, sumamos otro día
        if ($closing_time >= $next_business_day->hour) {
            $next_business_day->addDay();
        }

        // Si el próximo día es un día festivo, sumamos otro día
        while (Holiday::whereDate('date', $next_business_day)->exists()) {
            $next_business_day->addDay();
        }



        if ($next_business_day->isWeekend()) {
            //$next_business_day->next(Carbon::MONDAY);

            //valido si es sabado 
            if ($next_business_day->isSaturday()) {
                //valido in holidays

                while (Holiday::whereDate('date', $next_business_day)->first()) {
                    $next_business_day->next(Carbon::MONDAY);
                }
            }

            // Si el próximo lunes es un día festivo, sumamos otro día
            while (Holiday::whereDate('date', $next_business_day)->exists()) {
                $next_business_day->addDay();
            }
        }

        // Si el próximo día no es un día de semana (de lunes a viernes), lo cambiamos al siguiente lunes
        if ($next_business_day->isWeekend()) {
            $next_business_day->next(Carbon::MONDAY);

            // Si el próximo lunes es un día festivo, sumamos otro día
            while (Holiday::whereDate('date', $next_business_day)->exists()) {
                $next_business_day->addDay();
            }
        }

        return $next_business_day;
    }

    /**
     * Send order status email manually (after XML transmission)
     * Dispatches email job asynchronously
     */
    private static function sendOrderStatusEmail($order, $status)
    {
        try {
            // Determine queue connection
            $queueConnection = config('queue.default');
            if ($queueConnection === 'sync') {
                $queueConnection = 'database';
            }

            // Dispatch email job asynchronously
            \App\Jobs\SendOrderEmail::dispatch($order, 'status', $status)
                ->onConnection($queueConnection)
                ->onQueue('emails');

            Log::info("Order status email job dispatched for order {$order->id}", [
                'status' => $status,
                'queue_connection' => $queueConnection,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to dispatch order status email for order {$order->id}: " . $e->getMessage());
        }
    }

    /**
     * Retry XML transmission for an order
     */
    public static function retryXmlTransmission($order)
    {
        Log::channel('soap')->info('Retrying XML transmission', [
            'order_id' => $order->id,
            'current_status' => $order->status_id
        ]);

        try {
            // Forcefully refresh the Microsoft token before retry
            self::refreshMicrosoftToken();

            // Retry the XML transmission
            self::presalesOrder($order);

            return ['success' => true, 'message' => 'Transmisión XML exitosa'];
        } catch (\Exception $e) {
            Log::error("XML transmission retry failed for order {$order->id}: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error en transmisión XML: ' . $e->getMessage()];
        }
    }

    /**
     * Get the seller visit date for Tronex method
     * 
     * @param Zone|null $zone The user's zone
     * @return Carbon|null Seller visit date, or null if cannot be determined
     */
    public static function getTronexSellerVisitDate(?Zone $zone = null): ?Carbon
    {
        // If no zone provided, try to get from session
        if (!$zone && auth()->check()) {
            $zoneId = session()->get('zone_id');
            if ($zoneId) {
                $zone = Zone::find($zoneId);
            }
        }

        // If still no zone, return null
        if (!$zone || !$zone->route) {
            return null;
        }

        $route = $zone->route;
        $travelDays = $zone->day; // Format: "5-Viernes" or just "5"

        // Step 1: Get cycle for route
        $cycle = RouteCycle::getCycleForRoute($route);
        if (!$cycle) {
            return null;
        }

        // Step 2: Find next available week for this cycle
        $nextWeek = DeliveryCalendar::getNextAvailableWeek($cycle);
        if (!$nextWeek) {
            return null;
        }

        // Step 3: Parse TravelDays to get weekday
        $weekdayName = null;
        if (strpos($travelDays, '-') !== false) {
            $parts = explode('-', $travelDays);
            $weekdayName = trim($parts[1] ?? '');
        }

        // Map Spanish weekday names to Carbon dayOfWeek
        $weekdayMap = [
            'domingo' => Carbon::SUNDAY,
            'lunes' => Carbon::MONDAY,
            'martes' => Carbon::TUESDAY,
            'miercoles' => Carbon::WEDNESDAY,
            'miércoles' => Carbon::WEDNESDAY,
            'jueves' => Carbon::THURSDAY,
            'viernes' => Carbon::FRIDAY,
            'sabado' => Carbon::SATURDAY,
            'sábado' => Carbon::SATURDAY,
        ];

        $targetDayOfWeek = null;
        if ($weekdayName) {
            $weekdayNameLower = strtolower($weekdayName);
            $targetDayOfWeek = $weekdayMap[$weekdayNameLower] ?? null;
        }

        // If we couldn't parse weekday, try to use the day number as day of week
        if (!$targetDayOfWeek && is_numeric($travelDays)) {
            $dayNum = (int) $travelDays;
            if ($dayNum >= 0 && $dayNum <= 6) {
                $targetDayOfWeek = $dayNum;
            }
        }

        // Step 4: Find the matching weekday in the week range
        if ($targetDayOfWeek !== null) {
            $startDate = Carbon::parse($nextWeek->start_date);
            $endDate = Carbon::parse($nextWeek->end_date);
            
            $currentDate = $startDate->copy();
            while ($currentDate->lte($endDate)) {
                if ($currentDate->dayOfWeek === $targetDayOfWeek) {
                    return $currentDate;
                }
                $currentDate->addDay();
            }
        }

        // If we couldn't find the seller visit date, return start of week
        return Carbon::parse($nextWeek->start_date);
    }

    /**
     * Calculate delivery date for Tronex method (next available route date)
     * 
     * Logic:
     * 1. Get user's route from their zone
     * 2. Map route to cycle (A/B/C)
     * 3. Find next available week for that cycle
     * 4. Match TravelDays weekday with weekday in that week
     * 5. Seller visit day = matched weekday
     * 6. Delivery date = seller visit day + 1 business day
     * 
     * @param Zone|null $zone The user's zone (optional, will try to get from session if not provided)
     * @return string Delivery date in Y-m-d format
     */
    public static function getTronexDeliveryDate(?Zone $zone = null): string
    {
        // If no zone provided, try to get from session
        if (!$zone && auth()->check()) {
            $zoneId = session()->get('zone_id');
            if ($zoneId) {
                $zone = Zone::find($zoneId);
            }
        }

        // If still no zone, fallback to next business day
        if (!$zone || !$zone->route) {
            Log::warning('No zone or route found for Tronex delivery date calculation, using fallback');
            return self::getBusinessDay(0);
        }

        // Use the helper method to get seller visit date
        $sellerVisitDate = self::getTronexSellerVisitDate($zone);
        
        if (!$sellerVisitDate) {
            Log::warning('Could not determine seller visit date, using fallback');
            return self::getBusinessDay(0);
        }

        // Step 5: Delivery date = seller visit date + 1 business day
        // But first check if today is the seller visit day
        $today = now();
        $isTodaySellerVisitDay = $today->format('Y-m-d') === $sellerVisitDate->format('Y-m-d');
        
        if ($isTodaySellerVisitDay) {
            // If order is placed on seller visit day, delivery is next business day
            return self::getBusinessDay(0);
        } else {
            // If order is placed before seller visit day, delivery is seller visit day + 1 business day
            // We need to calculate business day from seller visit date
            $deliveryDate = self::getBusinessDayFromDate($sellerVisitDate, 0);
            return $deliveryDate;
        }
    }

    /**
     * Calculate business day from a specific date
     * 
     * @param Carbon $fromDate Starting date
     * @param int $daysAhead Number of business days ahead
     * @return string Date in Y-m-d format
     */
    private static function getBusinessDayFromDate(Carbon $fromDate, int $daysAhead = 0): string
    {
        $now = $fromDate->copy();
        
        // Find the required number of business days ahead
        $i = 0;
        $businessDaysFound = 0;
        while (true) {
            $now = $now->addDay();

            $i++;
            if ($i > 30) {
                break;
            }

            if (self::isBussinessDay($now)) {
                $businessDaysFound++;
                if ($businessDaysFound > $daysAhead) {
                    break;
                }
            }
        }

        return $now->format('Y-m-d');
    }

    /**
     * Calculate delivery date for Express method (orden express)
     * Promises delivery in 2 business days from order date
     * Considers holidays, Sundays, and working Saturdays
     */
    public static function getExpressDeliveryDate()
    {
        // Get 2 business days ahead (daysAhead=1 means 2 business days ahead)
        // daysAhead=0 → 1 business day ahead
        // daysAhead=1 → 2 business days ahead
        return self::getBusinessDay(1);
    }

    /**
     * Calculate delivery date based on delivery method
     * 
     * @param string $method Delivery method ('express' or 'tronex')
     * @param Zone|null $zone User's zone (optional, for Tronex calculation)
     * @return string Delivery date in Y-m-d format
     */
    public static function getDeliveryDateByMethod(string $method, ?Zone $zone = null): string
    {
        if ($method === 'express') {
            return self::getExpressDeliveryDate();
        }
        
        return self::getTronexDeliveryDate($zone);
    }

    /**
     * Refresh Microsoft token for XML transmission
     */
    private static function refreshMicrosoftToken()
    {
        $client_id = config('microsoft.client_id');
        $client_secret = config('microsoft.client_secret');
        $resource = config('microsoft.resource');
        $url = config('microsoft.url_token');

        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'resource' => $resource,
        ];

        $response = Http::asForm()->post($url, $data);

        if (!$response->successful()) {
            throw new \Exception('No se pudo actualizar el token de autenticación');
        }

        $json = $response->json();
        $token = $json['access_token'] ?? null;

        if (!$token) {
            throw new \Exception('Token de autenticación no válido');
        }

        Setting::where('key', 'microsoft_token')->update(['value' => $token]);
    }
}
