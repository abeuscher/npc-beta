@php
    $form = \App\Models\Form::where('handle', $handle)->where('is_active', true)->first();
    if (! $form) return;

    $successKey = 'form_success_' . $form->handle;
    $submitted  = session()->has($successKey);

    $usStates = [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
        'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
        'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
        'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
        'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
        'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
        'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
        'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
        'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
        'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
        'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
        'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
        'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'DC' => 'District of Columbia',
    ];

    $topCountries = [
        'US' => 'United States',
        'CA' => 'Canada',
        'GB' => 'United Kingdom',
        'AU' => 'Australia',
    ];

    $allCountries = [
        'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AR' => 'Argentina',
        'AM' => 'Armenia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan', 'BH' => 'Bahrain',
        'BD' => 'Bangladesh', 'BE' => 'Belgium', 'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina',
        'BR' => 'Brazil', 'BG' => 'Bulgaria', 'KH' => 'Cambodia', 'CM' => 'Cameroon',
        'CL' => 'Chile', 'CN' => 'China', 'CO' => 'Colombia', 'HR' => 'Croatia',
        'CU' => 'Cuba', 'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DK' => 'Denmark',
        'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'EE' => 'Estonia',
        'ET' => 'Ethiopia', 'FI' => 'Finland', 'FR' => 'France', 'GE' => 'Georgia',
        'DE' => 'Germany', 'GH' => 'Ghana', 'GR' => 'Greece', 'GT' => 'Guatemala',
        'HN' => 'Honduras', 'HU' => 'Hungary', 'IN' => 'India', 'ID' => 'Indonesia',
        'IR' => 'Iran', 'IQ' => 'Iraq', 'IE' => 'Ireland', 'IL' => 'Israel',
        'IT' => 'Italy', 'JM' => 'Jamaica', 'JP' => 'Japan', 'JO' => 'Jordan',
        'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KR' => 'South Korea', 'KW' => 'Kuwait',
        'LV' => 'Latvia', 'LB' => 'Lebanon', 'LT' => 'Lithuania', 'LU' => 'Luxembourg',
        'MY' => 'Malaysia', 'MX' => 'Mexico', 'MA' => 'Morocco', 'NL' => 'Netherlands',
        'NZ' => 'New Zealand', 'NG' => 'Nigeria', 'NO' => 'Norway', 'PK' => 'Pakistan',
        'PE' => 'Peru', 'PH' => 'Philippines', 'PL' => 'Poland', 'PT' => 'Portugal',
        'QA' => 'Qatar', 'RO' => 'Romania', 'RU' => 'Russia', 'SA' => 'Saudi Arabia',
        'RS' => 'Serbia', 'SG' => 'Singapore', 'SK' => 'Slovakia', 'ZA' => 'South Africa',
        'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SE' => 'Sweden', 'CH' => 'Switzerland',
        'TW' => 'Taiwan', 'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TN' => 'Tunisia',
        'TR' => 'Turkey', 'UA' => 'Ukraine', 'AE' => 'United Arab Emirates',
        'UY' => 'Uruguay', 'UZ' => 'Uzbekistan', 'VE' => 'Venezuela', 'VN' => 'Vietnam',
        'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe',
    ];

    asort($allCountries);
    $countries = $topCountries + ['---' => '─────────────'] + $allCountries;
@endphp

@if ($submitted)
    <p class="text-green-700 dark:text-green-300">{{ session($successKey) }}</p>
@else
    @if ($errors->has('_form'))
        <p role="alert" class="text-red-600 dark:text-red-400">{{ $errors->first('_form') }}</p>
    @endif

    <form method="POST" action="{{ route('forms.submit', $form->handle) }}">
        @csrf

        @if ($form->settings['honeypot'] ?? true)
            <div style="display:none" aria-hidden="true">
                <input type="text" name="_hp" value="" tabindex="-1" autocomplete="off">
            </div>
        @endif

        <div class="grid grid-cols-12 gap-4">
            @foreach ($form->fields ?? [] as $field)
                @php
                    $handle      = $field['handle'] ?? '';
                    $type        = $field['type'] ?? 'text';
                    $label       = $field['label'] ?? '';
                    $placeholder = $field['placeholder'] ?? '';
                    $required    = ! empty($field['required']);
                    $width       = $field['width'] ?? 12;
                    $pattern     = (! empty($field['validation_regex'])) ? $field['validation_regex'] : null;
                    $errMsg      = $field['validation_message'] ?? '';
                    $hint        = $field['hint'] ?? '';
                    $old         = old($handle);

                    $inputClasses = 'block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary';
                @endphp

                @if ($type === 'hidden')
                    <input type="hidden" name="{{ $handle }}" value="{{ $field['default_value'] ?? '' }}">
                @else
                    <div class="col-span-{{ $width }} max-md:col-span-12">
                        @if ($type === 'radio')
                            <fieldset>
                                <legend class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $label }}@if($required) <span aria-hidden="true" class="text-red-500">*</span>@endif</legend>
                                <div class="space-y-1">
                                    @foreach ($field['options'] ?? [] as $option)
                                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                            <input
                                                type="radio"
                                                name="{{ $handle }}"
                                                value="{{ $option['value'] }}"
                                                {{ $old === $option['value'] ? 'checked' : '' }}
                                                {{ $required ? 'required' : '' }}
                                                class="border-gray-300 dark:border-gray-600 text-primary focus:ring-primary"
                                            >
                                            {{ $option['label'] }}
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                            @if($hint)<small class="text-xs text-gray-500 dark:text-gray-400 mt-1 block">{{ $hint }}</small>@endif

                        @elseif ($type === 'checkbox')
                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    name="{{ $handle }}"
                                    value="1"
                                    {{ $old ? 'checked' : '' }}
                                    class="rounded border-gray-300 dark:border-gray-600 text-primary focus:ring-primary"
                                >
                                {{ $label }}
                            </label>
                            @if($hint)<small class="text-xs text-gray-500 dark:text-gray-400 mt-1 block">{{ $hint }}</small>@endif

                        @elseif ($type === 'textarea')
                            <label for="field_{{ $handle }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $label }}@if($required) <span aria-hidden="true" class="text-red-500">*</span>@endif</label>
                            <textarea
                                id="field_{{ $handle }}"
                                name="{{ $handle }}"
                                placeholder="{{ $placeholder }}"
                                {{ $required ? 'required' : '' }}
                                class="{{ $inputClasses }} min-h-[6rem]"
                            >{{ $old }}</textarea>
                            @if($hint)<small class="text-xs text-gray-500 dark:text-gray-400 mt-1 block">{{ $hint }}</small>@endif
                            @error($handle)<small class="text-sm text-red-600 dark:text-red-400 mt-1 block">{{ $message }}</small>@enderror

                        @elseif ($type === 'select')
                            <label for="field_{{ $handle }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $label }}@if($required) <span aria-hidden="true" class="text-red-500">*</span>@endif</label>
                            <select
                                id="field_{{ $handle }}"
                                name="{{ $handle }}"
                                {{ $required ? 'required' : '' }}
                                class="{{ $inputClasses }}"
                            >
                                <option value="">— Select —</option>
                                @foreach ($field['options'] ?? [] as $option)
                                    <option value="{{ $option['value'] }}" {{ $old === $option['value'] ? 'selected' : '' }}>
                                        {{ $option['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            @if($hint)<small class="text-xs text-gray-500 dark:text-gray-400 mt-1 block">{{ $hint }}</small>@endif
                            @error($handle)<small class="text-sm text-red-600 dark:text-red-400 mt-1 block">{{ $message }}</small>@enderror

                        @elseif ($type === 'state')
                            <label for="field_{{ $handle }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $label }}@if($required) <span aria-hidden="true" class="text-red-500">*</span>@endif</label>
                            <select
                                id="field_{{ $handle }}"
                                name="{{ $handle }}"
                                {{ $required ? 'required' : '' }}
                                class="{{ $inputClasses }}"
                            >
                                <option value="">— Select state —</option>
                                @foreach ($usStates as $abbr => $name)
                                    <option value="{{ $abbr }}" {{ $old === $abbr ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                            @if($hint)<small class="text-xs text-gray-500 dark:text-gray-400 mt-1 block">{{ $hint }}</small>@endif
                            @error($handle)<small class="text-sm text-red-600 dark:text-red-400 mt-1 block">{{ $message }}</small>@enderror

                        @elseif ($type === 'country')
                            <label for="field_{{ $handle }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $label }}@if($required) <span aria-hidden="true" class="text-red-500">*</span>@endif</label>
                            <select
                                id="field_{{ $handle }}"
                                name="{{ $handle }}"
                                {{ $required ? 'required' : '' }}
                                class="{{ $inputClasses }}"
                            >
                                <option value="">— Select country —</option>
                                @foreach ($countries as $code => $name)
                                    @if ($code === '---')
                                        <option disabled>{{ $name }}</option>
                                    @else
                                        <option value="{{ $code }}" {{ $old === $code ? 'selected' : '' }}>{{ $name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @if($hint)<small class="text-xs text-gray-500 dark:text-gray-400 mt-1 block">{{ $hint }}</small>@endif
                            @error($handle)<small class="text-sm text-red-600 dark:text-red-400 mt-1 block">{{ $message }}</small>@enderror

                        @else
                            {{-- text | email | tel | number --}}
                            <label for="field_{{ $handle }}" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $label }}@if($required) <span aria-hidden="true" class="text-red-500">*</span>@endif</label>
                            <input
                                type="{{ $type }}"
                                id="field_{{ $handle }}"
                                name="{{ $handle }}"
                                value="{{ $old }}"
                                placeholder="{{ $placeholder }}"
                                {{ $required ? 'required' : '' }}
                                @if ($pattern) pattern="{{ $pattern }}" title="{{ $errMsg }}" @endif
                                class="{{ $inputClasses }}"
                            >
                            @if($hint)<small class="text-xs text-gray-500 dark:text-gray-400 mt-1 block">{{ $hint }}</small>@endif
                            @error($handle)<small class="text-sm text-red-600 dark:text-red-400 mt-1 block">{{ $message }}</small>@enderror
                        @endif
                    </div>
                @endif
            @endforeach
        </div>

        <button type="submit" class="mt-4 px-6 py-2 bg-primary text-white rounded font-medium hover:opacity-80 cursor-pointer">{{ $form->settings['submit_label'] ?? 'Submit' }}</button>
    </form>
@endif
