<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SANDSTAR_PHOTO = '/images/machines/1000015229.jpg';

    private const SANDSTAR_SPECS = '/images/machines/1000015236.jpg';

    /**
     * Tables whose `image` column may still point at a Vendera picture.
     *
     * @var list<string>
     */
    private const IMAGE_TABLES = [
        'machines',
        'location_types',
        'products',
        'articles',
        'coffee_machines',
    ];

    /**
     * Hero pictures that were Vendera files but described another machine; these get that machine back.
     *
     * @var array<string, string>
     */
    private const HERO_REPLACEMENTS_BY_ALT = [
        'Snack and drink combo machine' => '/images/machines/snack-drink-combo.png',
        'Alpine snack and beverage machine' => '/images/machines/Alpine-ST5000.png',
    ];

    /**
     * Take the Vendera pictures off the site: stored rows that still use one get the Sandstar AI photo,
     * and the Sandstar AI cooler gets its specifications image. Admin-uploaded pictures are never touched.
     */
    public function up(): void
    {
        foreach (self::IMAGE_TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'image')) {
                DB::table($table)
                    ->where('image', 'like', '%Vendera%')
                    ->update(['image' => self::SANDSTAR_PHOTO]);
            }
        }

        $this->replaceHeroPictures();
        $this->addSandstarSpecsImage();
    }

    /**
     * The old pictures are removed from the site on purpose, so this is not reversed.
     */
    public function down(): void
    {
        //
    }

    private function replaceHeroPictures(): void
    {
        if (! Schema::hasTable('hero_images')) {
            return;
        }

        DB::table('hero_images')
            ->where('image', 'like', '%Vendera%')
            ->get(['id', 'alt'])
            ->each(function (object $row): void {
                DB::table('hero_images')
                    ->where('id', $row->id)
                    ->update(['image' => self::HERO_REPLACEMENTS_BY_ALT[$row->alt] ?? self::SANDSTAR_PHOTO]);
            });
    }

    private function addSandstarSpecsImage(): void
    {
        if (! Schema::hasTable('machines') || ! Schema::hasColumn('machines', 'spec_image')) {
            return;
        }

        DB::table('machines')
            ->where('slug', 'ai-cooler')
            ->where(fn ($query) => $query->whereNull('spec_image')->orWhere('spec_image', ''))
            ->update(['spec_image' => self::SANDSTAR_SPECS]);
    }
};
