@php $product = $pageContext->product($config['product_slug'] ?? null); @endphp
@isset($product)
    @php
        $isAtCapacity   = $product->isAtCapacity();
        $checkoutStatus = request()->query('checkout');
    @endphp

    <div>
        <h2>{{ $product->name }}</h2>

        @if ($product->description)
            <p class="text-muted" style="margin-bottom: 1rem;">{{ $product->description }}</p>
        @endif

        @if ($checkoutStatus === 'success')
            <div role="status" class="alert alert--success">
                <strong>Purchase complete!</strong>
                <p>Thank you for your purchase. You will receive a receipt by email.</p>
            </div>

        @elseif ($checkoutStatus === 'cancelled')
            <div role="status" class="alert alert--warning">
                <p>Your checkout was cancelled. No payment was taken.</p>
            </div>

        @elseif (session('waitlist_success'))
            <div role="status" class="alert alert--success">
                <strong>You're on the waitlist!</strong>
                <p>We'll be in touch if a spot opens up.</p>
            </div>

        @elseif ($isAtCapacity)
            <p class="text-muted" style="margin-bottom: 1rem;">This item is currently sold out.</p>

            <h3 style="margin-bottom: 0.75rem;">Join the waitlist</h3>

            @if ($errors->has('waitlist'))
                <div role="alert" class="alert alert--error">{{ $errors->first('waitlist') }}</div>
            @endif

            <form method="POST" action="{{ route('products.waitlist') }}" class="form-stack">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                <div>
                    <label for="waitlist_email" class="form-label">Email address <span aria-hidden="true" class="required-star">*</span></label>
                    <input type="email" id="waitlist_email" name="email" required
                           value="{{ old('email') }}" autocomplete="email">
                    @error('email')<span role="alert" class="form-error">{{ $message }}</span>@enderror
                </div>

                <div>
                    <label for="waitlist_name" class="form-label">Name <span class="text-muted-light" style="font-weight: normal;">(optional)</span></label>
                    <input type="text" id="waitlist_name" name="name"
                           value="{{ old('name') }}" autocomplete="name">
                </div>

                <button type="submit" class="btn btn--primary">Join waitlist</button>
            </form>

        @else
            @if ($errors->has('checkout'))
                <div role="alert" class="alert alert--error">{{ $errors->first('checkout') }}</div>
            @endif

            <div>
                @foreach ($product->prices as $price)
                    <div class="price-card">
                        <span class="price-card__label">
                            {{ $price->label }}
                            @if ($price->amount > 0)
                                — <span class="price-card__amount">${{ number_format($price->amount, 2) }}</span>
                            @else
                                — <span class="price-card__free">Free</span>
                            @endif
                        </span>

                        <form method="POST" action="{{ route('products.checkout') }}">
                            @csrf
                            <input type="hidden" name="product_price_id" value="{{ $price->id }}">
                            <button type="submit" class="btn btn--primary text-sm">
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
