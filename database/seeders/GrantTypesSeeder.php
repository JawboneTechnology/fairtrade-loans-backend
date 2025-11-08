<?php

namespace Database\Seeders;

use App\Models\GrantType;
use App\Helpers\GrantHelper;
use Illuminate\Database\Seeder;

class GrantTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        GrantHelper::resetCounter();

        $grantTypes = [
            [
                'name' => 'COMMUNITY BURSARY SCHEME',
                'grant_code' => 'G001',
                'description' => 'Financial support for community education initiatives and bursaries',
                'requires_dependent' => false,
                'max_amount' => 10000.00,
                'is_active' => true,
            ],
            [
                'name' => 'PARTIAL SPONSORSHIP SCHEME',
                'grant_code' => 'G002',
                'description' => 'Partial funding support for approved educational programs and courses',
                'requires_dependent' => false,
                'max_amount' => 7500.00,
                'is_active' => true,
            ],
            [
                'name' => 'WORKERS CAPACITY BUILDING SCHEME',
                'grant_code' => 'G003',
                'description' => 'Funding for employee training, skills development and capacity building',
                'requires_dependent' => false,
                'max_amount' => 8000.00,
                'is_active' => true,
            ],
            [
                'name' => 'SPECIAL GROUP SUPPORT SCHEME - BURSARY',
                'grant_code' => 'G004',
                'description' => 'Educational bursary support for special groups and disadvantaged members',
                'requires_dependent' => true,
                'max_amount' => 12000.00,
                'is_active' => true,
            ],
            [
                'name' => 'MEDICAL GRANT SCHEME',
                'grant_code' => 'G005',
                'description' => 'Financial assistance for medical expenses and healthcare needs',
                'requires_dependent' => true,
                'max_amount' => 15000.00,
                'is_active' => true,
            ],
            [
                'name' => 'STAFF FUNERAL GRANT SCHEME',
                'grant_code' => 'G006',
                'description' => 'Support for funeral expenses of deceased staff members',
                'requires_dependent' => false,
                'max_amount' => 5000.00,
                'is_active' => true,
            ],
            [
                'name' => 'FIRE DISASTER SCHEME',
                'grant_code' => 'G007',
                'description' => 'Assistance for victims of fire disasters and related emergencies',
                'requires_dependent' => false,
                'max_amount' => 20000.00,
                'is_active' => true,
            ],
            [
                'name' => 'COMMUNITY SUPPORT SCHEME',
                'grant_code' => 'G008',
                'description' => 'General community development funding and support programs',
                'requires_dependent' => false,
                'max_amount' => 10000.00,
                'is_active' => true,
            ],
            [
                'name' => 'SPECIAL GROUP SUPPORT SCHEME - AID',
                'grant_code' => 'G009',
                'description' => 'Financial aid and support for special groups and vulnerable members',
                'requires_dependent' => true,
                'max_amount' => 10000.00,
                'is_active' => true,
            ],
            [
                'name' => 'COMMUNITY AID SCHEME',
                'grant_code' => 'G010',
                'description' => 'General community financial assistance and welfare support',
                'requires_dependent' => false,
                'max_amount' => 8000.00,
                'is_active' => true,
            ],
            [
                'name' => 'CLEAN ENERGY GRANT',
                'grant_code' => 'G011',
                'description' => 'Funding for clean energy initiatives and sustainable projects',
                'requires_dependent' => false,
                'max_amount' => 15000.00,
                'is_active' => true,
            ],
        ];

        foreach ($grantTypes as $grantType) {
            GrantType::query()->create($grantType);
        }
    }
}
