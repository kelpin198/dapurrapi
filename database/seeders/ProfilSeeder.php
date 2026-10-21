<?php

namespace Database\Seeders;

use App\Models\RestaurantProfile;
use Illuminate\Database\Seeder;

class ProfilSeeder extends Seeder
{
    public function run()
    {
        RestaurantProfile::updateOrCreate(
            ['id' => 1],
            [
                'name' => RestaurantProfile::DEFAULT_NAME,
                'history' => RestaurantProfile::DEFAULT_HISTORY,
            ]
        );
    }
}
