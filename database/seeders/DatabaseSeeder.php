<?php

namespace Database\Seeders;

use App\Domain\Billing\InvoiceStatus;
use App\Infrastructure\Persistence\Models\Client;
use App\Infrastructure\Persistence\Models\Company;
use Database\Factories\ClientFactory;
use Database\Factories\InvoiceFactory;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

// duas empresas pra dar pra testar o isolamento na mão. Senha de todo mundo: password
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $acme = Company::create(['name' => 'Acme']);
        UserFactory::new()->owner()->create(['company_id' => $acme->id, 'name' => 'Dona da Acme', 'email' => 'dona@acme.test']);
        UserFactory::new()->create(['company_id' => $acme->id, 'name' => 'Membro da Acme', 'email' => 'membro@acme.test']);
        $this->billing($acme);

        $globex = Company::create(['name' => 'Globex']);
        UserFactory::new()->owner()->create(['company_id' => $globex->id, 'name' => 'Dono da Globex', 'email' => 'dono@globex.test']);
        $this->billing($globex);
    }

    private function billing(Company $company): void
    {
        /** @var Collection<int, Client> $clients */
        $clients = ClientFactory::new()->count(3)->create(['company_id' => $company->id]);

        foreach ($clients as $client) {
            $invoices = InvoiceFactory::new()->state(['company_id' => $company->id, 'client_id' => $client->id]);
            $invoices->create(['status' => InvoiceStatus::Open, 'due_date' => now()->subDays(5)->toDateString()]);
            $invoices->create(['status' => InvoiceStatus::Open, 'due_date' => now()->addDays(15)->toDateString()]);
            $invoices->paid()->create();
            $invoices->draft()->create();
        }
    }
}
