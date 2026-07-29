<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Build a getRuteros SOAP response in the exact shape UserRepository::fetchRuteroData parses.
 *
 * Each sucursal requires code/zone/route/day/address/name; optional keys map to
 * extra Dynamics detail fields (phone, mobile_phone, whatsapp, email, balance,
 * quota_value, business_name, price_group).
 *
 * Pass phone/mobile_phone/whatsapp as empty string to emit self-closing tags that
 * json_encode(SimpleXML) turns into [] — the real Dynamics empty-field shape.
 *
 * @param  array<int, array<string, string>>  $sucursales
 */
function fakeGetRuterosSoap(array $sucursales): string
{
    $optionalDetailTags = [
        'phone' => 'aPhone',
        'mobile_phone' => 'aPhoneMobile',
        'whatsapp' => 'aWhatsapp',
        'email' => 'aEmail',
        'balance' => 'aBalance',
        'quota_value' => 'aQuotaValue',
        'business_name' => 'aRazonSocial',
        'price_group' => 'aPriceGroup',
        'document' => 'aIdentificationNum',
    ];

    $ruteros = '';
    foreach ($sucursales as $s) {
        $extraXml = '';
        foreach ($optionalDetailTags as $key => $tag) {
            if (! array_key_exists($key, $s)) {
                continue;
            }
            // Empty string → self-closing tag (Dynamics empty node → [] after json_encode).
            if ($s[$key] === '') {
                $extraXml .= '<'.$tag.'/>';
                continue;
            }
            $extraXml .= '<'.$tag.'>'.$s[$key].'</'.$tag.'>';
        }

        $ruteros .= '<aListRuteros>'
            .'<aDiaRecorrido>'.$s['day'].'</aDiaRecorrido>'
            .'<aRoute>'.$s['route'].'</aRoute>'
            .'<aZona>'.$s['zone'].'</aZona>'
            .'<aDetail><aListDetailsRuteros>'
                .'<aCustRuteroID>'.$s['code'].'</aCustRuteroID>'
                .'<aAddress>'.$s['address'].'</aAddress>'
                .'<aName>'.$s['name'].'</aName>'
                .$extraXml
            .'</aListDetailsRuteros></aDetail>'
        .'</aListRuteros>';
    }

    return '<sEnvelope><sBody><getRuterosResponse><result><agetRuterosResult>'
        .$ruteros
        .'</agetRuterosResult></result></getRuterosResponse></sBody></sEnvelope>';
}

/** Empty getRuteros payload (no ListRuteros children). */
function fakeEmptyGetRuterosSoap(): string
{
    return '<sEnvelope><sBody><getRuterosResponse><result><agetRuterosResult>'
        .'</agetRuterosResult></result></getRuterosResponse></sBody></sEnvelope>';
}

/** Nil ListRuteros — historically crashed array_key_exists(0, null). */
function fakeNilGetRuterosSoap(): string
{
    return '<sEnvelope><sBody><getRuterosResponse><result><agetRuterosResult>'
        .'<aListRuteros/>'
        .'</agetRuterosResult></result></getRuterosResponse></sBody></sEnvelope>';
}
