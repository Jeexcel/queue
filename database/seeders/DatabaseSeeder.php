<?php

namespace Database\Seeders;

use App\Models\ServiceDesk;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (['Desk 1', 'Desk 2', 'Desk 3', 'Desk 4'] as $name) {
            ServiceDesk::query()->firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
