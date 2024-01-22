<?php

namespace Database\Seeders;

use Artwork\Modules\ShiftQualification\Models\ShiftQualification;
use Illuminate\Database\Seeder;

class ShiftQualificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        ShiftQualification::create([
            'icon' => 'academic-cap-icon',
            'name' => 'Meister',
            'available' => true
        ]);

        //need some more to test with? do not commit uncommented
        //uncomment the next line and run "sail artisan db:seed --class=ShiftQualificationSeeder"
        //ShiftQualification::factory(50)->create();
    }
}
