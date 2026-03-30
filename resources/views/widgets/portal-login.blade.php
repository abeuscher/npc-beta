@if ($errors->any())
    <div role="alert" class="rounded border border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/30 p-4 mb-4">
        <ul class="list-disc pl-5 text-sm text-red-800 dark:text-red-200 space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('portal.login.post') }}" class="space-y-4">
    @csrf

    <div>
        <label for="lw_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email address</label>
        <input type="email" id="lw_email" name="email" required
               value="{{ old('email') }}" autocomplete="email"
               class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
    </div>

    <div>
        <label for="lw_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
        <input type="password" id="lw_password" name="password" required
               autocomplete="current-password"
               class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
    </div>

    <div>
        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}
                   class="rounded border-gray-300 dark:border-gray-600 text-primary focus:ring-primary">
            Remember me
        </label>
    </div>

    <button type="submit" class="px-5 py-2 bg-primary text-white rounded font-medium hover:opacity-80 cursor-pointer">Log in</button>
</form>

<p class="mt-4 text-sm text-gray-600 dark:text-gray-400"><a href="{{ route('portal.password.request') }}" class="text-primary hover:opacity-80">Forgot your password?</a></p>
<p class="text-sm text-gray-600 dark:text-gray-400"><a href="{{ route('portal.signup') }}" class="text-primary hover:opacity-80">Don't have an account? Sign up</a></p>
