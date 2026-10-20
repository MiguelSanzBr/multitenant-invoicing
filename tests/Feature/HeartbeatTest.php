<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Queue\Events\Looping;

// healthcheck do compose olha a idade desses arquivos: worker ou scheduler travado fica unhealthy
it('worker da fila deixa sinal de vida a cada volta do loop', function () {
    @unlink(storage_path('framework/queue-heartbeat'));

    event(new Looping('database', 'default'));

    expect(storage_path('framework/queue-heartbeat'))->toBeFile();
});

it('scheduler deixa sinal de vida todo minuto', function () {
    @unlink(storage_path('framework/schedule-heartbeat'));
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => $event->description === 'heartbeat do scheduler');

    $event?->run(app());

    expect($event?->expression)->toBe('* * * * *')
        ->and(storage_path('framework/schedule-heartbeat'))->toBeFile();
});
