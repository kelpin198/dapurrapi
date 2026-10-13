@extends('layouts.app')

@section('title', 'Buat Akun')
@section('body-class', 'auth-page')

@section('content')
<main class="auth-layout">
    <section class="auth-story auth-story-register" aria-label="Cerita Dapur Rapi">
        <a class="brand brand-light" href="{{ url('/') }}">
            <span class="brand-mark" aria-hidden="true">DR</span>
            <span>Dapur Rapi</span>
        </a>
        <div class="story-copy">
            <span class="eyebrow eyebrow-light"><span class="eyebrow-dot"></span> Awali dari dapurmu</span>
            <h1>Rapi di dapur,<br><em>lega</em> di kepala.</h1>
            <p>Catat menu, pantau stok, dan beri ruang untuk hal yang paling penting: masakan yang enak.</p>
        </div>
        <div class="story-foot"><span>02 / 03</span><span>Mulai langkah kecil</span></div>
    </section>

    <section class="auth-panel">
        <div class="mobile-brand"><a class="brand" href="{{ url('/') }}"><span class="brand-mark" aria-hidden="true">DR</span><span>Dapur Rapi</span></a></div>
        <div class="auth-form-wrap">
            <div class="auth-heading">
                <span class="eyebrow">AKUN BARU</span>
                <h2>Mulai dengan<br>yang sederhana.</h2>
                <p>Buat akun untuk menata operasional dapurmu.</p>
            </div>
            <div class="form-notice" data-notice role="status" aria-live="polite" hidden></div>
            <form class="auth-form" data-auth-form="register">
                <label class="field-label" for="name">Nama lengkap</label>
                <input class="text-field" id="name" name="name" type="text" autocomplete="name" placeholder="Contoh: Naya Putri" maxlength="100" required>
                <label class="field-label" for="email">Alamat email</label>
                <input class="text-field" id="email" name="email" type="email" autocomplete="email" placeholder="nama@email.com" required>
                <label class="field-label" for="password">Kata sandi <span class="field-hint">minimal 8 karakter</span></label>
                <input class="text-field" id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="Buat kata sandi" required>
                <label class="field-label" for="password_confirmation">Ulangi kata sandi</label>
                <input class="text-field" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="Masukkan sekali lagi" required>
                <button class="button button-primary button-wide" type="submit"><span>Buat akun</span><span aria-hidden="true">↗</span></button>
            </form>
            <p class="auth-switch">Sudah punya akun? <a href="{{ route('login') }}">Masuk di sini</a></p>
        </div>
        <footer class="auth-footer"><span>© {{ date('Y') }} Dapur Rapi</span><span>Dibuat untuk ritme dapurmu.</span></footer>
    </section>
</main>
@endsection
