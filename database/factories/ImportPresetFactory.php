<?php

namespace Database\Factories;

use App\Models\ImportPreset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportPreset>
 */
class ImportPresetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type'     => 'strongs',
            'name'     => 'Preset ' . bin2hex(random_bytes(4)),
            'settings' => [
                'first_row_data' => 2,
                'col_A' => 'number',
                'col_B' => 'definition',
            ],
        ];
    }

    /**
     * A preset for the Bible importers
     */
    public function bible(): static
    {
        return $this->state(fn() => [
            'type'     => ImportPreset::TYPE_BIBLE,
            'settings' => ['first_row_data' => 2, 'col_a' => 'b', 'col_b' => 'c', 'col_c' => 'v', 'col_d' => 't'],
        ]);
    }
}
