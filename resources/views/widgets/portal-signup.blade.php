@if ($errors->any())
    <div role="alert">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('portal.signup.post') }}">
    @csrf

    {{-- Honeypot --}}
    <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;opacity:0;pointer-events:none;">
        <label for="_hp_name_sw">Leave this empty</label>
        <input type="text" id="_hp_name_sw" name="_hp_name" tabindex="-1" autocomplete="off">
    </div>
    <input type="hidden" name="_form_start" value="{{ time() }}">

    <div>
        <label for="sw_first_name">First name <span aria-hidden="true">*</span></label>
        <input type="text" id="sw_first_name" name="first_name" required
               value="{{ old('first_name') }}" autocomplete="given-name">
        @error('first_name')<span role="alert">{{ $message }}</span>@enderror
    </div>

    <div>
        <label for="sw_last_name">Last name <span aria-hidden="true">*</span></label>
        <input type="text" id="sw_last_name" name="last_name" required
               value="{{ old('last_name') }}" autocomplete="family-name">
        @error('last_name')<span role="alert">{{ $message }}</span>@enderror
    </div>

    <div>
        <label for="sw_email">Email address <span aria-hidden="true">*</span></label>
        <input type="email" id="sw_email" name="email" required
               value="{{ old('email') }}" autocomplete="email">
        @error('email')<span role="alert">{{ $message }}</span>@enderror
    </div>

    <div>
        <label for="sw_password">Password <span aria-hidden="true">*</span></label>
        <input type="password" id="sw_password" name="password" required
               autocomplete="new-password" minlength="12">
        <small>Minimum 12 characters.</small>
        @error('password')<span role="alert">{{ $message }}</span>@enderror
    </div>

    <div>
        <label for="sw_password_confirmation">Confirm password <span aria-hidden="true">*</span></label>
        <input type="password" id="sw_password_confirmation" name="password_confirmation" required
               autocomplete="new-password" minlength="12">
    </div>

    <button type="submit">Create account</button>
</form>
