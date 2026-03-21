<?php

namespace Database\Seeders;

use App\Models\Form;
use Illuminate\Database\Seeder;

class FormSeeder extends Seeder
{
    public function run(): void
    {
        Form::firstOrCreate(
            ['handle' => 'contact-signup'],
            [
                'title'       => 'Contact Sign-Up',
                'description' => 'Template form for collecting contact information. Developers may download the JSON, adjust field order or widths, and re-import. The contact_field mappings drive session-048 contact creation.',
                'is_active'   => true,
                'settings'    => [
                    'submit_label'    => 'Sign Up',
                    'success_message' => 'Thank you for signing up. We will be in touch soon.',
                    'honeypot'        => true,
                    'form_type'       => 'contact',
                ],
                'fields' => [
                    [
                        'handle'             => 'first_name',
                        'type'               => 'text',
                        'label'              => 'First Name',
                        'placeholder'        => '',
                        'required'           => true,
                        'width'              => 6,
                        'validation'         => 'none',
                        'validation_regex'   => null,
                        'validation_message' => null,
                        'options'            => [],
                        'contact_field'      => 'first_name',
                    ],
                    [
                        'handle'             => 'last_name',
                        'type'               => 'text',
                        'label'              => 'Last Name',
                        'placeholder'        => '',
                        'required'           => true,
                        'width'              => 6,
                        'validation'         => 'none',
                        'validation_regex'   => null,
                        'validation_message' => null,
                        'options'            => [],
                        'contact_field'      => 'last_name',
                    ],
                    [
                        'handle'             => 'email',
                        'type'               => 'email',
                        'label'              => 'Email Address',
                        'placeholder'        => '',
                        'required'           => true,
                        'width'              => 12,
                        'validation'         => 'email',
                        'validation_regex'   => null,
                        'validation_message' => null,
                        'options'            => [],
                        'contact_field'      => 'email',
                    ],
                    [
                        'handle'             => 'phone',
                        'type'               => 'tel',
                        'label'              => 'Phone',
                        'placeholder'        => '',
                        'required'           => false,
                        'width'              => 6,
                        'validation'         => 'phone',
                        'validation_regex'   => null,
                        'validation_message' => null,
                        'options'            => [],
                        'contact_field'      => 'phone',
                    ],
                    [
                        'handle'             => 'mailing_list_opt_in',
                        'type'               => 'checkbox',
                        'label'              => 'Subscribe to our newsletter',
                        'placeholder'        => '',
                        'required'           => false,
                        'width'              => 12,
                        'validation'         => 'none',
                        'validation_regex'   => null,
                        'validation_message' => null,
                        'options'            => [],
                        'contact_field'      => 'mailing_list_opt_in',
                    ],
                ],
            ]
        );
    }
}
