<?php

namespace App\Repositories;

use App\Models\Holiday;
use App\Models\Order;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class OrderRepository
{
    public static function presalesOrder($order)
    {
        self::sendData(order: $order, products: $order->products, bonification: 0);
        if ($order->bonifications->count()) {
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

        $user = $order->user;
        $zone = $order->zone;


        $order->load('products.product.brand.vendor');
        $order->update([
            'request' => $zone,
        ]);
        $delivery_date = $order->delivery_date;


        $day = $zone->day;
        $route = $zone->route;
        $code = $zone->code;
        $zone = $zone->zone;


        $productList = '';


        foreach ($products as $product) {
            $vendor_type = $product->product->brand->vendor->vendor_type;
            $unitPrice = parseCurrency($product->price);

            if ($bonification) {
                $unitPrice = 0;

            }
            ;

            $sku = $product->product->sku;
            if ($product->variationItem) {
                $sku = DB::table('product_item_variation')->where('id', $product->variation_item_id)->value('sku');
            }
            $productList .= '<dyn:listDetails>
                            <dyn:discount>' . (int) $product->percentage . '</dyn:discount>
                            <dyn:itemId>' . $sku . '</dyn:itemId>
                            <dyn:qty>' . $product->quantity . '</dyn:qty>
                            <dyn:qtyCust>' . $product->quantity . '</dyn:qtyCust>
                            <dyn:um>Unidad</dyn:um>
                            <dyn:umCust>None</dyn:umCust>
                            <dyn:unitPrice>' . $unitPrice . '</dyn:unitPrice>
                            <dyn:vendorType>' . $vendor_type . '</dyn:vendorType>
                        </dyn:listDetails>';
        }

        $order_id = $order->id;

        $transactionDate = $order->created_at->format('Y-m-d');



        // $transactionDate = '2024-07-08';
        // $delivery_date = '2024-07-09';


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
                            <dyn:vendorType>' . $vendor_type . '</dyn:vendorType>
                            <dyn:zona>' . $zone . '</dyn:zona> 
                        </dyn:preSalesOrder>
                    </tem:ArrayOfPreSalesOrder>
                </tem:PreSaslesProcess>
            </soapenv:Body>
        </soapenv:Envelope>';



        $token = Setting::getByKey('microsoft_token');

        $resource_url = config('microsoft.resource');



        $response = Http::withHeaders([
            'Content-Type' => 'text/xml;charset=UTF-8',
            'SOAPAction' => 'http://tempuri.org/DWSSalesForce/PreSaslesProcess',
            'Authorization' => "Bearer {$token}"
        ])->send('POST', $resource_url . '/soap/services/DIITDWSSalesForceGroup?=null', [
                    'body' => $body
                ]);


        $data = $response->body();
        $xmlString = preg_replace('/<(\/)?(s|a):/', '<$1$2', $data);
        $xml = simplexml_load_string($xmlString);

        try {

            $response = $xml->sBody->PreSaslesProcessResponse->result->aPreSaslesProcessResult;
            if ($response == 'OK') {
                $order->update([
                    'status_id' => Order::STATUS_PROCESED,
                    'request' => $body,
                    'response' => $response
                ]);
            } else {
                $order->update([
                    'status_id' => Order::STATUS_ERROR,
                    'request' => $body,
                    'response' => $response
                ]);
            }
        } catch (\Exception $e) {
            $order->update([
                'status_id' => Order::STATUS_ERROR_WEBSERVICE,
                'request' => $body,
                'response' => $response
            ]);
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

    public static function getBusinessDay()
    {

        $now = now();
        $hour = $now->subHours(5)->hour;
        $closing_time = (int) Setting::getByKey('closing_time');


        if ($closing_time <= $hour) {
            $now = now()->addDay();
        }



        //while 10 times
        $i = 0;
        while (True):
            $now = $now->addDay();

            $i++;
            if ($i > 10) {
                break;
            }

            if (self::isBussinessDay($now)) {
                break;
            }


        endwhile;

        return $now->format('Y-m-d');



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
}
