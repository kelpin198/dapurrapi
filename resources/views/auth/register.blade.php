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
                <div class="password-field-wrap">
                    <input class="text-field" id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="Buat kata sandi" data-register-password required>
                    <button class="password-visibility-toggle" type="button" data-password-visibility data-password-target="register-password" data-show-label="Tampilkan kata sandi" data-hide-label="Sembunyikan kata sandi" aria-label="Tampilkan kata sandi" aria-pressed="false" title="Tampilkan kata sandi">
                        <svg data-password-eye-open viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg data-password-eye-closed viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12c2.3 3.2 5.6 5 10 5s7.7-1.8 10-5"/><path d="m6 15-1 2m13-2 1 2"/></svg>
                    </button>
                </div>
                <label class="field-label" for="password_confirmation">Ulangi kata sandi</label>
                <div class="password-field-wrap">
                    <input class="text-field" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="Masukkan sekali lagi" data-register-password-confirmation required>
                    <button class="password-visibility-toggle" type="button" data-password-visibility data-password-target="register-password-confirmation" data-show-label="Tampilkan konfirmasi kata sandi" data-hide-label="Sembunyikan konfirmasi kata sandi" aria-label="Tampilkan konfirmasi kata sandi" aria-pressed="false" title="Tampilkan konfirmasi kata sandi">
                        <svg data-password-eye-open viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg data-password-eye-closed viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12c2.3 3.2 5.6 5 10 5s7.7-1.8 10-5"/><path d="m6 15-1 2m13-2 1 2"/></svg>
                    </button>
                </div>
                <button class="button button-primary button-wide" type="submit"><span>Buat akun</span><span aria-hidden="true">↗</span></button>
            </form>
            <p class="auth-switch">Sudah punya akun? <a href="{{ route('login') }}">Masuk di sini</a></p>
        </div>
        <footer class="auth-footer"><span>© {{ date('Y') }} Dapur Rapi</span><span>Dibuat untuk ritme dapurmu.</span></footer>
    </section>
</main>
@endsection
