<?php

use App\Jobs\SendContactFormEmail;
use App\Jobs\SendOrderEmail;
use App\Models\Contact;
use App\Models\Order;
use App\Models\User;
use App\Models\Zone;
use App\Services\MailingService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

/*
|--------------------------------------------------------------------------
| Email Job Configuration Tests
|--------------------------------------------------------------------------
| These tests verify the email job configurations without requiring database migrations
*/

it('SendContactFormEmail job has correct retry configuration', function () {
    // Create a mock contact
    $contact = new Contact([
        'id' => 1,
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '1234567890',
        'business_name' => 'Test Business',
    ]);

    $job = new SendContactFormEmail($contact);
    
    expect($job->tries)->toBe(3);
    expect($job->backoff)->toBe([60, 300, 900]);
    expect($job->timeout)->toBe(30);
});

it('SendOrderEmail job has correct retry configuration', function () {
    // Create a mock order
    $order = new Order([
        'id' => 1,
        'user_id' => 1,
        'total' => 100000,
        'discount' => 0,
        'status_id' => Order::STATUS_PENDING,
    ]);

    $job = new SendOrderEmail($order, 'confirmation');
    
    expect($job->tries)->toBe(3);
    expect($job->backoff)->toBe([60, 300, 900]);
    expect($job->timeout)->toBe(30);
});

it('SendOrderEmail job accepts confirmation type', function () {
    $order = new Order([
        'id' => 1,
        'user_id' => 1,
        'total' => 100000,
    ]);

    $job = new SendOrderEmail($order, 'confirmation');
    
    expect($job)->toBeInstanceOf(SendOrderEmail::class);
});

it('SendOrderEmail job accepts status type with status value', function () {
    $order = new Order([
        'id' => 1,
        'user_id' => 1,
        'total' => 100000,
    ]);

    $job = new SendOrderEmail($order, 'status', 'shipped');
    
    expect($job)->toBeInstanceOf(SendOrderEmail::class);
});

it('SendContactFormEmail implements ShouldQueue interface', function () {
    $contact = new Contact(['id' => 1, 'name' => 'Test', 'email' => 'test@test.com']);
    $job = new SendContactFormEmail($contact);
    
    expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

it('SendOrderEmail implements ShouldQueue interface', function () {
    $order = new Order(['id' => 1]);
    $job = new SendOrderEmail($order, 'confirmation');
    
    expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

/*
|--------------------------------------------------------------------------
| Email Job Queue Integration Tests
|--------------------------------------------------------------------------
*/

it('email jobs can be serialized for queuing', function () {
    $contact = new Contact([
        'id' => 1,
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $job = new SendContactFormEmail($contact);
    
    // Should be serializable (uses SerializesModels trait)
    $serialized = serialize($job);
    expect($serialized)->toBeString();
});

it('order email jobs can be serialized for queuing', function () {
    $order = new Order([
        'id' => 1,
        'user_id' => 1,
        'total' => 100000,
    ]);

    $job = new SendOrderEmail($order, 'status', 'processed');
    
    // Should be serializable (uses SerializesModels trait)
    $serialized = serialize($job);
    expect($serialized)->toBeString();
});

/*
|--------------------------------------------------------------------------
| Email Job Constants and Status Tests
|--------------------------------------------------------------------------
*/

it('Order model has correct status constants', function () {
    expect(Order::STATUS_PENDING)->toBe(0);
    expect(Order::STATUS_PROCESSED)->toBe(1);
    expect(Order::STATUS_SHIPPED)->toBe(4);
    expect(Order::STATUS_DELIVERED)->toBe(5);
    expect(Order::STATUS_CANCELLED)->toBe(6);
    expect(Order::STATUS_ERROR)->toBe(2);
    expect(Order::STATUS_ERROR_WEBSERVICE)->toBe(3);
    expect(Order::STATUS_WAITING)->toBe(7);
});

it('Order model has correct delivery method constants', function () {
    expect(Order::DELIVERY_METHOD_EXPRESS)->toBe('express');
    expect(Order::DELIVERY_METHOD_TRONEX)->toBe('tronex');
});

it('Order model can get status slug from status ID', function () {
    expect(Order::getStatusSlug(Order::STATUS_PENDING))->toBe('pending');
    expect(Order::getStatusSlug(Order::STATUS_PROCESSED))->toBe('processed');
    expect(Order::getStatusSlug(Order::STATUS_SHIPPED))->toBe('shipped');
    expect(Order::getStatusSlug(Order::STATUS_DELIVERED))->toBe('delivered');
    expect(Order::getStatusSlug(Order::STATUS_CANCELLED))->toBe('cancelled');
});
