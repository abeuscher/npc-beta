<?php

namespace App\Services\Import\Fixtures\Importers;

use App\Services\Import\Fixtures\FixtureBuilder;
use Faker\Generator as Faker;

class NotesFixtureBuilder extends FixtureBuilder
{
    public function importer(): string
    {
        return 'notes';
    }

    public function supportedPresets(): array
    {
        return ['generic'];
    }

    public function customFieldSentinel(): ?string
    {
        return '__custom_note__';
    }

    public function columnMap(string $preset): array
    {
        return [
            'Note Type'                => 'note:type',
            'Note Subject'             => 'note:subject',
            'Note Status'              => 'note:status',
            'Note Body'                => 'note:body',
            'Note Occurred At'         => 'note:occurred_at',
            'Note Follow-up At'        => 'note:follow_up_at',
            'Note Outcome'             => 'note:outcome',
            'Note Duration (minutes)'  => 'note:duration_minutes',
            'Note External ID'         => 'note:external_id',
            'Email'                    => 'contact:email',
            'User ID'                  => 'contact:external_id',
            'Phone'                    => 'contact:phone',
        ];
    }

    public function headers(string $preset): array
    {
        $headers = [
            'Note Type',
            'Note Subject',
            'Note Status',
            'Note Body',
            'Note Occurred At',
            'Note Follow-up At',
            'Note Outcome',
            'Note Duration (minutes)',
            'Note External ID',
            'Email',
            'User ID',
            'Phone',
        ];

        foreach ($this->customFieldColumns($preset) as $cf) {
            $headers[] = $cf['header'];
        }

        return $headers;
    }

    public function customFieldColumns(string $preset): array
    {
        return [
            ['header' => 'Channel',     'handle' => 'channel',     'type' => 'text'],
            ['header' => 'Sentiment',   'handle' => 'sentiment',   'type' => 'text'],
        ];
    }

    public function cleanRow(int $rowIndex, string $preset, Faker $faker): array
    {
        $first    = $faker->firstName();
        $last     = $faker->lastName();
        $occurred = $faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d H:i:s');
        $followUp = date('Y-m-d H:i:s', strtotime($occurred . ' +30 days'));

        return [
            'Note Type'                => $faker->randomElement(['call', 'email', 'meeting', 'note']),
            'Note Subject'             => ucfirst($faker->words(4, true)),
            'Note Status'              => 'completed',
            'Note Body'                => $faker->paragraph(),
            'Note Occurred At'         => $occurred,
            'Note Follow-up At'        => $faker->boolean(40) ? $followUp : '',
            'Note Outcome'             => $faker->sentence(5),
            'Note Duration (minutes)'  => (string) $faker->numberBetween(5, 120),
            'Note External ID'         => 'NOTE-FIX-' . $rowIndex,
            'Email'                    => strtolower("{$first}.{$last}+{$rowIndex}@example.org"),
            'User ID'                  => '',
            'Phone'                    => $faker->phoneNumber(),
            'Channel'                  => $faker->randomElement(['phone', 'email', 'in-person']),
            'Sentiment'                => $faker->randomElement(['positive', 'neutral', 'concerned']),
        ];
    }
}
