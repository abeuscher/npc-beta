@if (auth('portal')->check())

    @if (session('success'))
        <div role="status" class="rounded border border-green-300 bg-green-50 dark:border-green-700 dark:bg-green-900/30 p-4 mb-4 text-green-800 dark:text-green-200">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div role="alert" class="rounded border border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/30 p-4 mb-4">
            <ul class="list-disc pl-5 text-sm text-red-800 dark:text-red-200 space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('portal.account.update-password') }}" id="portal-pw-form" class="space-y-4">
        @csrf
        @method('PATCH')

        <div>
            <label for="ppw_current" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Password</label>
            <input type="password" id="ppw_current" name="current_password" required autocomplete="current-password"
                   class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
        </div>

        <div>
            <label for="ppw_new" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Password <small class="text-gray-400 font-normal">(minimum 12 characters)</small></label>
            <input type="password" id="ppw_new" name="password" required minlength="12" autocomplete="new-password"
                   class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
        </div>

        <div>
            <label for="ppw_confirm" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm New Password</label>
            <input type="password" id="ppw_confirm" name="password_confirmation" required minlength="12" autocomplete="new-password"
                   class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
        </div>

        <div id="ppw-match-error" style="display:none;" role="alert" class="text-sm text-red-600 dark:text-red-400">Passwords do not match.</div>

        <button type="submit" class="px-5 py-2 bg-primary text-white rounded font-medium hover:opacity-80 cursor-pointer">Update password</button>
    </form>

    <script>
    (function () {
        var form = document.getElementById('portal-pw-form');
        var pw   = document.getElementById('ppw_new');
        var conf = document.getElementById('ppw_confirm');
        var err  = document.getElementById('ppw-match-error');
        function check() { err.style.display = (conf.value && pw.value !== conf.value) ? '' : 'none'; }
        pw.addEventListener('input', check);
        conf.addEventListener('input', check);
        form.addEventListener('submit', function (e) { if (pw.value !== conf.value) { e.preventDefault(); err.style.display = ''; } });
    }());
    </script>

@endif
