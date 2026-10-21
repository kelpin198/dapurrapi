@extends('layouts.app')

@section('title', 'Lupa Kata Sandi')
@section('body-class', 'auth-page')

@section('content')
<main class="auth-layout">
    <section class="auth-story auth-story-login" aria-label="Bantuan akun Dapur Rapi">
        <a class="brand brand-light" href="{{ route('login') }}">
            <span class="brand-mark login-brand-mark" aria-hidden="true">@if($hasRestaurantLogo)<img class="login-brand-logo" src="{{ url('/restaurant-logo') }}" alt="">@else DR @endif</span>
            <span>Dapur Rapi</span>
        </a>
        <div class="story-copy">
            <span class="eyebrow eyebrow-light"><span class="eyebrow-dot"></span> AKSES AKUN</span>
            <h1>Kembali ke<br><em>dapurmu.</em></h1>
            <p>Kami akan mengirim tautan untuk membuat kata sandi baru ke email akunmu.</p>
        </div>
        <div class="story-foot"><span>03 / 03</span><span>Keamanan akunmu</span></div>
    </section>

    <section class="auth-panel">
        <div class="mobile-brand"><a class="brand" href="{{ route('login') }}"><span class="brand-mark login-brand-mark" aria-hidden="true">@if($hasRestaurantLogo)<img class="login-brand-logo" src="{{ url('/restaurant-logo') }}" alt="">@else DR @endif</span><span>Dapur Rapi</span></a></div>
        <div class="auth-form-wrap">
            <div class="auth-heading">
                <span class="eyebrow">PEMULIHAN AKUN</span>
                <h2>Lupa kata<br>sandi?</h2>
                <p>Masukkan email akunmu. Jika terdaftar, kami akan mengirim tautan reset.</p>
            </div>
            <div class="form-notice" data-notice role="status" aria-live="polite" hidden></div>
            <form class="auth-form" data-auth-form="forgot-password">
                <label class="field-label" for="email">Alamat email</label>
                <input class="text-field" id="email" name="email" type="email" autocomplete="email" placeholder="nama@email.com" value="{{ request()->query('email') }}" required>
                <button class="button button-primary button-wide" type="submit"><span>Kirim tautan reset</span><span aria-hidden="true">↗</span></button>
            </form>
            <p class="auth-switch"><a href="{{ route('login') }}">Kembali ke halaman masuk</a></p>
        </div>
        <footer class="auth-footer"><span>© {{ date('Y') }} Dapur Rapi</span><span>Dibuat untuk ritme dapurmu.</span></footer>
    </section>
</main>
@endsection