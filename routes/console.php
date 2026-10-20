<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('invoices:mark-overdue')->dailyAt('09:00')->withoutOverlapping();
