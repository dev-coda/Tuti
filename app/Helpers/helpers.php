<?php
if (! function_exists('asset_url')) {
    function asset_url($image, $path)
    {

        $asset_url =   env('DO_ASSET_URL');

        // if($size == null){
        //     return "{$asset_url}/{$image}.jpg";
        // }

        return "{$asset_url}/{$path}/{$image}";

        return;
    }
}


if (! function_exists('public_asset')) {
    function public_asset($file)
    {
        $path = config('app.url');
        return "{$path}/{$file}";
    }
}



if (! function_exists('currency')) {
    function currency($price)
    {

        $price = number_format($price, 0, ',', '.');
        return $price;
    }
}

if (! function_exists('parseCurrency')) {
    function parseCurrency($price)
    {

        $price = number_format($price, 2, '.', '');
        return $price;
    }
}

if (! function_exists('amount_with_tax')) {
    /**
     * Apply a tax percentage to a tax-exclusive amount (e.g. lista + IVA).
     */
    function amount_with_tax(float|int|string $amount, float|int|string|null $taxPercent): float
    {
        $amount = (float) $amount;
        $taxPercent = (float) ($taxPercent ?? 0);

        if ($taxPercent <= 0) {
            return $amount;
        }

        return $amount * (1 + ($taxPercent / 100));
    }
}
