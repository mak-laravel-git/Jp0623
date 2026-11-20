<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables whose `image` column may point at one of the pictures below.
     *
     * @var list<string>
     */
    private const IMAGE_TABLES = [
        'machines',
        'hero_images',
        'location_types',
        'products',
        'articles',
        'coffee_machines',
    ];

    /**
     * Pictures that show the Vendera logo although their file name does not say so, and the clean
     * picture of the same kind of machine that replaces each one.
     *
     * @var array<string, string>
     */
    private const REPLACEMENTS = [
        '/images/machines/Alpine-ST5000.png' => '/images/machines/Alpine-ST50000.jpg',
        '/images/machines/healthy-vending.png' => '/images/machines/healthy-vending1.png',
        '/images/machines/coffee.png' => '/images/machines/coffee1.jpg',
    ];

    /**
     * Take the remaining Vendera pictures off the site. Admin-uploaded pictures are never touched.
     */
    public function up(): void
    {
        foreach (self::IMAGE_TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'image')) {
                continue;
            }

            foreach (self::REPLACEMENTS as $old => $new) {
                DB::table($table)->where('image', $old)->update(['image' => $new]);
            }
        }
    }

    /**
     * The old pictures are removed from the site on purpose, so this is not reversed.
     */
    public function down(): void
    {
        //
    }
};
