<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SupermarketSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $supermarkets = [
            [
                'identifier' => 'ah',
                'name' => 'Albert Heijn',
                'base_url' => 'https://api.ah.nl',
                'requires_auth' => true,
                'enabled' => true,
            ],
            [
                'identifier' => 'jumbo',
                'name' => 'Jumbo',
                'base_url' => 'https://mobileapi.jumbo.com/v17',
                'requires_auth' => false,
                'enabled' => true,
            ],
        ];

        foreach ($supermarkets as $supermarket) {
            \DB::table('supermarkets')->updateOrInsert(
                ['identifier' => $supermarket['identifier']],
                $supermarket
            );
        }
    }
}
