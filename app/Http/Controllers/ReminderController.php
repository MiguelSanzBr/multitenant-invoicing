<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Persistence\Models\Reminder;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReminderController extends Controller
{
    public function __invoke(): Response
    {
        Gate::authorize('viewAny', Reminder::class);

        return Inertia::render('reminders/index', [
            'reminders' => Reminder::with('invoice:id,client_id,description', 'invoice.client:id,name')
                ->latest('id')
                ->paginate(20),
        ]);
    }
}
