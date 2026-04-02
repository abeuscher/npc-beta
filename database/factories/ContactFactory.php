<?php

namespace Database\Factories;

use App\Data\SampleLibrary;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        $firstName = fake()->randomElement(SampleLibrary::firstNames());
        $lastName  = fake()->randomElement(SampleLibrary::lastNames());

        // Strip characters that would trigger quoted-string local parts in RFC 2822.
        $safeFirst = preg_replace('/[^a-z0-9._-]/', '', strtolower($firstName));
        $safeLast  = preg_replace('/[^a-z0-9._-]/', '', strtolower($lastName));

        $email = fake()->boolean(80)
            ? $safeFirst . '.' . $safeLast . fake()->unique()->numerify('.###') . '@' . fake()->randomElement(SampleLibrary::emailDomains())
            : null;

        return [
            'prefix'         => fake()->optional(0.3)->randomElement(['Mr', 'Ms', 'Mrs', 'Dr', 'Prof']),
            'first_name'     => $firstName,
            'last_name'      => $lastName,
            'email'          => $email,
            'phone'          => fake()->boolean(70) ? fake()->numerify('(###) 555-####') : null,
            'address_line_1' => fake()->boolean(60) ? fake()->randomElement(SampleLibrary::streetAddresses()) : null,
            'address_line_2' => fake()->optional(0.1)->secondaryAddress(),
            'city'           => fake()->boolean(60) ? fake()->randomElement(SampleLibrary::cities()) : null,
            'state'          => fake()->boolean(60) ? fake()->randomElement(SampleLibrary::states()) : null,
            'postal_code'    => fake()->optional(0.6)->postcode(),
            'country'        => 'US',
            'custom_data'    => null,
            'do_not_contact' => false,
            'source'         => fake()->randomElement(['manual', 'manual', 'manual', 'import', 'form', 'api']),
        ];
    }

    public function doNotContact(): static
    {
        return $this->state(fn () => ['do_not_contact' => true]);
    }

    public function withGmailBase(string $base): static
    {
        $localPart = str_contains($base, '@') ? explode('@', $base)[0] : $base;

        return $this->state(function (array $attributes) use ($localPart) {
            $first = preg_replace('/[^a-z0-9._-]/', '', strtolower($attributes['first_name']));
            $last  = preg_replace('/[^a-z0-9._-]/', '', strtolower($attributes['last_name']));

            return ['email' => "{$localPart}+{$first}_{$last}@gmail.com"];
        });
    }
}
