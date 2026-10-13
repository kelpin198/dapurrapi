@extends('layouts.app')

@section('title', 'Masuk')
@section('body-class', 'auth-page')

@section('content')
<main class="auth-layout">
    <section class="auth-story auth-story-login" aria-label="Cerita Dapur Rapi">
        <a class="brand brand-light" href="{{ url('/') }}">
            <span class="brand-mark" aria-hidden="true">DR</span>
            <span>Dapur Rapi</span>
        </a>
        <div class="story-copy">
            <span class="eyebrow eyebrow-light"><span class="eyebrow-dot"></span> Dapur tenang, usaha berkembang</span>
            <h1>Semua stok,<br>lebih <em>tertata.</em></h1>
            <p>Kelola bahan dan menu harian dalam satu ruang yang terasa ringan, bahkan saat dapur sedang ramai.</p>
        </div>
        <div class="story-foot"><span>01 / 03</span><span>Kelola dengan rasa</span></div>
    </section>

    <section class="auth-panel">
        <div class="mobile-brand"><a class="brand" href="{{ url('/') }}"><span class="brand-mark" aria-hidden="true">DR</span><span>Dapur Rapi</span></a></div>
        <div class="auth-form-wrap">
            <div class="auth-heading">
                <span class="eyebrow">SELAMAT DATANG KEMBALI</span>
                <h2>Senang melihatmu<br>lagi.</h2>
                <p>Masuk untuk melihat kabar dapur hari ini.</p>
            </div>
            <div class="form-notice" data-notice role="status" aria-live="polite" hidden></div>
            <form class="auth-form" data-auth-form="login">
                <label class="field-label" for="email">Alamat email</label>
                <input class="text-field" id="email" name="email" type="email" autocomplete="email" placeholder="nama@email.com" required>
                <div class="label-row"><label class="field-label" for="password">Kata sandi</label><a class="text-link" href="#" data-unavailable-link>Butuh bantuan?</a></div>
                <input class="text-field" id="password" name="password" type="password" autocomplete="current-password" placeholder="Masukkan kata sandi" required>
                <button class="button button-primary button-wide" type="submit"><span>Masuk ke dashboard</span><span aria-hidden="true">↗</span></button>
            </form>
            <p class="auth-switch">Belum punya akun? <a href="{{ route('register') }}">Buat akun baru</a></p>
        </div>
        <footer class="auth-footer"><span>© {{ date('Y') }} Dapur Rapi</span><span>Dibuat untuk ritme dapurmu.</span></footer>
    </section>
</main>
@endsection
