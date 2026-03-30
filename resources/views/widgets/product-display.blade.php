@php $product = $pageContext->product($config['product_slug'] ?? null); @endphp
@isset($product)
    @php
        $isAtCapacity   = $product->isAtCapacity();
        $checkoutStatus = request()->query('checkout');
    @endphp

    <div>
        <h2 class="text-2xl font-heading font-bold mb-2 text-gray-900 dark:text-gray-100">{{ $product->name }}</h2>

        @if ($product->description)
            <p class="text-gray-600 dark:text-gray-400 mb-4">{{ $product->description }}</p>
        @endif

        @if ($checkoutStatus === 'success')
            <div role="status" class="rounded border border-green-300 bg-green-50 dark:border-green-700 dark:bg-green-900/30 p-4 mb-4">
                <strong class="text-green-800 dark:text-green-200">Purchase complete!</strong>
                <p class="text-green-700 dark:text-green-300 mt-1">Thank you for your purchase. You will receive a receipt by email.</p>
            </div>

        @elseif ($checkoutStatus === 'cancelled')
            <div role="status" class="rounded border border-yellow-300 bg-yellow-50 dark:border-yellow-700 dark:bg-yellow-900/30 p-4 mb-4">
                <p class="text-yellow-800 dark:text-yellow-200">Your checkout was cancelled. No payment was taken.</p>
            </div>

        @elseif (session('waitlist_success'))
            <div role="status" class="rounded border border-green-300 bg-green-50 dark:border-green-700 dark:bg-green-900/30 p-4 mb-4">
                <strong class="text-green-800 dark:text-green-200">You're on the waitlist!</strong>
                <p class="text-green-700 dark:text-green-300 mt-1">We'll be in touch if a spot opens up.</p>
            </div>

        @elseif ($isAtCapacity)
            <p class="text-gray-600 dark:text-gray-400 mb-4">This item is currently sold out.</p>

            <h3 class="text-lg font-heading font-semibold mb-3 text-gray-900 dark:text-gray-100">Join the waitlist</h3>

            @if ($errors->has('waitlist'))
                <div role="alert" class="rounded border border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/30 p-4 mb-4 text-red-800 dark:text-red-200">{{ $errors->first('waitlist') }}</div>
            @endif

            <form method="POST" action="{{ route('products.waitlist') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                <div>
                    <label for="waitlist_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email address <span aria-hidden="true" class="text-red-500">*</span></label>
                    <input type="email" id="waitlist_email" name="email" required
                           value="{{ old('email') }}" autocomplete="email"
                           class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
                    @error('email')<span role="alert" class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</span>@enderror
                </div>

                <div>
                    <label for="waitlist_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="text" id="waitlist_name" name="name"
                           value="{{ old('name') }}" autocomplete="name"
                           class="block w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 focus:border-primary focus:ring-primary">
                </div>

                <button type="submit" class="px-5 py-2 bg-primary text-white rounded font-medium hover:opacity-80 cursor-pointer">Join waitlist</button>
            </form>

        @else
            @if ($errors->has('checkout'))
                <div role="alert" class="rounded border border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/30 p-4 mb-4 text-red-800 dark:text-red-200">{{ $errors->first('checkout') }}</div>
            @endif

            <div class="space-y-4">
                @foreach ($product->prices as $price)
                    <div class="flex items-center justify-between border border-gray-200 dark:border-gray-700 rounded p-4">
                        <span class="font-medium text-gray-900 dark:text-gray-100">
                            {{ $price->label }}
                            @if ($price->amount > 0)
                                — <span class="text-primary">${{ number_format($price->amount, 2) }}</span>
                            @else
                                — <span class="text-green-600 dark:text-green-400">Free</span>
                            @endif
                        </span>

                        <form method="POST" action="{{ route('products.checkout') }}">
                            @csrf
                            <input type="hidden" name="product_price_id" value="{{ $price->id }}">
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded font-medium hover:opacity-80 cursor-pointer text-sm">
                                @if ($price->amount > 0)
                                    Buy — ${{ number_format($price->amount, 2) }}
                                @else
                                    Get for free
                                @endif
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endisset
