@php $product = $pageContext->product($config['product_slug'] ?? null); @endphp
@isset($product)
    @php
        $isAtCapacity   = $product->isAtCapacity();
        $checkoutStatus = request()->query('checkout');
    @endphp

    <div>
        <h2>{{ $product->name }}</h2>

        @if ($product->description)
            <p>{{ $product->description }}</p>
        @endif

        @if ($checkoutStatus === 'success')
            <div role="status">
                <strong>Purchase complete!</strong>
                <p>Thank you for your purchase. You will receive a receipt by email.</p>
            </div>

        @elseif ($checkoutStatus === 'cancelled')
            <div role="status">
                <p>Your checkout was cancelled. No payment was taken.</p>
            </div>

        @elseif (session('waitlist_success'))
            <div role="status">
                <strong>You're on the waitlist!</strong>
                <p>We'll be in touch if a spot opens up.</p>
            </div>

        @elseif ($isAtCapacity)
            <p>This item is currently sold out.</p>

            <h3>Join the waitlist</h3>

            @if ($errors->has('waitlist'))
                <div role="alert">{{ $errors->first('waitlist') }}</div>
            @endif

            <form method="POST" action="{{ route('products.waitlist') }}">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                <div>
                    <label for="waitlist_email">Email address <span aria-hidden="true">*</span></label>
                    <input type="email" id="waitlist_email" name="email" required
                           value="{{ old('email') }}" autocomplete="email">
                    @error('email')<span role="alert">{{ $message }}</span>@enderror
                </div>

                <div>
                    <label for="waitlist_name">Name <span>(optional)</span></label>
                    <input type="text" id="waitlist_name" name="name"
                           value="{{ old('name') }}" autocomplete="name">
                </div>

                <button type="submit">Join waitlist</button>
            </form>

        @else
            @if ($errors->has('checkout'))
                <div role="alert">{{ $errors->first('checkout') }}</div>
            @endif

            @foreach ($product->prices as $price)
                <div>
                    <strong>{{ $price->label }}</strong>
                    @if ($price->amount > 0)
                        — ${{ number_format($price->amount, 2) }}
                    @else
                        — Free
                    @endif

                    <form method="POST" action="{{ route('products.checkout') }}">
                        @csrf
                        <input type="hidden" name="product_price_id" value="{{ $price->id }}">
                        <button type="submit">
                            @if ($price->amount > 0)
                                Buy — ${{ number_format($price->amount, 2) }}
                            @else
                                Get for free
                            @endif
                        </button>
                    </form>
                </div>
            @endforeach
        @endif
    </div>
@endisset
