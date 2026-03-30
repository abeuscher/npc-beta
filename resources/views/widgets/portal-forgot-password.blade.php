@if ($errors->any())
    <div role="alert" class="rounded border border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/30 p-4 mb-4">
        <ul class="list-disc pl-5 text-sm text-red-800 dark:text-red-200 space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('portal.password.email') }}" class="space-y-4">
    @csrf

    <div>
        <label for="fp_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email address</label>
        <input type="email" id="fp_email" name="email" required
               value="{{ old('email') }}" autocomplete="email"
               class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
    </div>

    <button type="submit" class="px-5 py-2 bg-primary text-white rounded font-medium hover:opacity-80 cursor-pointer">Send reset link</button>
</form>

<p class="mt-4 text-sm text-gray-600 dark:text-gray-400"><a href="{{ route('portal.login') }}" class="text-primary hover:opacity-80">Back to log in</a></p>
