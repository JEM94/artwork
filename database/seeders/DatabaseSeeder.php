<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
//            RolesAndPermissionsSeeder::class,
//            SettingsSeeder::class,
//            ContentSeeder::class,
//            AuthUserSeeder::class,
//            FreelanceSeeder::class,
//            ServiceProviderSeeder::class,
//            CraftSeed::class,
            WalidRaadSeeder::class
        ]);
    }
}
