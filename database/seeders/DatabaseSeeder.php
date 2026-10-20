<?php

namespace Database\Seeders;

use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        UserFactory::new()->owner()->create(['name' => 'Dona da Empresa', 'email' => 'owner@example.com']);
    }
}
