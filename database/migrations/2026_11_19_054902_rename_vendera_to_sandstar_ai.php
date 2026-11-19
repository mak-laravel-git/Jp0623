<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Text columns (JSON columns included, they are replaced as text) that may mention the old name.
     * Image columns are intentionally left out: image files keep their current names.
     *
     * @var array<string, list<string>>
     */
    private const COLUMNS = [
        'machine_categories' => ['name', 'description'],
        'machines' => ['name', 'description', 'tags', 'capacity', 'best_for'],
        'products' => ['name', 'description', 'tags'],
        'articles' => ['title', 'excerpt', 'body'],
        'reviews' => ['quote', 'role', 'org'],
        'home_faqs' => ['question', 'answer'],
        'about_faqs' => ['question', 'answer'],
        'machine_faqs' => ['question', 'answer'],
        'office_coffee_faqs' => ['question', 'answer'],
        'coffee_machines' => ['title', 'text', 'tag'],
        'location_types' => ['name', 'description'],
        'sms_templates' => ['body'],
        'legal_pages' => ['title', 'sections'],
        'operator_profiles' => ['machine_types'],
        'vending_requests' => ['machine_interests'],
    ];

    /**
     * Rename the "Vendera" product/category to "Sandstar AI" in already-stored content.
     */
    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $this->renameInColumn($table, $column);
                }
            }
        }

        $this->renameCategorySlug();
    }

    /**
     * The rename cannot be reversed safely: "Sandstar AI" text may also have been entered by hand.
     */
    public function down(): void
    {
        //
    }

    private function renameInColumn(string $table, string $column): void
    {
        DB::table($table)
            ->where($column, 'like', '%Vendera%')
            ->get(['id', $column])
            ->each(function (object $row) use ($table, $column): void {
                $updated = self::rename((string) $row->{$column});

                if ($updated !== (string) $row->{$column}) {
                    DB::table($table)->where('id', $row->id)->update([$column => $updated]);
                }
            });
    }

    private function renameCategorySlug(): void
    {
        if (! Schema::hasTable('machine_categories')) {
            return;
        }

        $targetTaken = DB::table('machine_categories')->where('slug', 'sandstar-ai-cooler')->exists();

        if (! $targetTaken) {
            DB::table('machine_categories')
                ->where('slug', 'vendera-cooler')
                ->update(['slug' => 'sandstar-ai-cooler']);
        }
    }

    /**
     * Longest phrases first so "Vendera smart coolers" never ends up as "Sandstar AI smart coolers".
     */
    private static function rename(string $value): string
    {
        $value = str_replace(
            [
                'Vendera smart coolers',
                'Vendera Smart Cooler',
                'Vendera smart cooler',
                'Vendera coolers',
                'Vendera cooler',
            ],
            [
                'Sandstar AI coolers',
                'Sandstar AI Cooler',
                'Sandstar AI cooler',
                'Sandstar AI coolers',
                'Sandstar AI cooler',
            ],
            $value,
        );

        // Any remaining mention, but never an image file name such as "Vendera-Tap-Grab-Go-Cabinet.png".
        return (string) preg_replace('/Vendera(?!-)/', 'Sandstar AI', $value);
    }
};
