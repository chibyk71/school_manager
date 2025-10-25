<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Country;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 2)->unique(); // ISO 3166-1 alpha-2 code
            $table->timestamps();
            $table->softDeletes();
        });

        // Seed initial data with African countries
        $countries = [
            ['name' => 'Nigeria', 'code' => 'NG'],
            ['name' => 'Kenya', 'code' => 'KE'],
            ['name' => 'Ghana', 'code' => 'GH'],
            ['name' => 'South Africa', 'code' => 'ZA'],
            ['name' => 'Ethiopia', 'code' => 'ET'],
            ['name' => 'Algeria', 'code' => 'DZ'],
            ['name' => 'Morocco', 'code' => 'MA'],
            ['name' => 'Egypt', 'code' => 'EG'],
            ['name' => 'Angola', 'code' => 'AO'],
            ['name' => 'Cameroon', 'code' => 'CM'],
            ['name' => 'Côte d\'Ivoire', 'code' => 'CI'],
            ['name' => 'Democratic Republic of the Congo', 'code' => 'CD'],
            ['name' => 'Uganda', 'code' => 'UG'],
            ['name' => 'Sudan', 'code' => 'SD'],
            ['name' => 'Tunisia', 'code' => 'TN'],
            // Add more African countries as needed
        ];

        foreach ($countries as $country) {
            Country::create($country);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
