<?php

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

uses(TestCase::class);

it('schedules inventory:sync nightly at 02:30 UTC with overlap protection', function () {
    $events = collect(app(Schedule::class)->events());
    $inventory = $events->first(fn ($event) => str_contains($event->command ?? '', 'inventory:sync'));

    expect($inventory)->not->toBeNull()
        ->and($inventory->expression)->toBe('30 2 * * *')
        ->and($inventory->withoutOverlapping)->toBeTrue()
        ->and($inventory->runInBackground)->toBeTrue();
});
