<?php

use App\Models\EmailTemplate;

it('replaces order_products in body only and strips placeholder from subject', function () {
    $template = new EmailTemplate();
    $template->subject = 'Pedido {order_id} {order_products}';
    $template->body = '<table><tbody>{order_products}</tbody></table>';

    $out = $template->replaceVariables([
        'order_id' => '99',
        'order_products' => [
            ['name' => 'Item A', 'quantity' => 2, 'price' => '$1.000'],
        ],
    ]);

    expect($out['subject'])->toBe('Pedido 99');
    expect($out['body'])->toContain('<tr>');
    expect($out['body'])->toContain('Item A');
    expect($out['body'])->not->toContain('$$');
});

it('strips leading dollar from price before formatting order product cells', function () {
    $html = EmailTemplate::formatOrderProductsHtmlRows([
        ['name' => 'X', 'quantity' => 1, 'price' => '$ 5.000'],
    ]);

    expect($html)->toContain('$5.000');
    expect(substr_count($html, '$'))->toBe(1);
});
