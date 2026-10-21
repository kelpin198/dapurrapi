@extends('layouts.app')

@section('title', 'Atur Ulang Kata Sandi')
@section('body-class', 'auth-page')

@section('content')
<main class="auth-layout">
    <section class="auth-story auth-story-register" aria-label="Atur ulang akun Dapur Rapi">
        <a class="brand brand-light" href="{{ route('login') }}">
            <span class="brand-mark login-brand-mark" aria-hidden="true">DR</span>
            <span>Dapur Rapi</span>
        </a>
        <div class="story-copy">
            <span class="eyebrow eyebrow-light"><span class="eyebrow-dot"></span> AKUN AMAN</span>
            <h1>Kata sandi<br><em>baru.</em></h1>
            <p>Buat kata sandi baru yang mudah kamu ingat dan sulit ditebak.</p>
        </div>
        <div class="story-foot"><span>03 / 03</span><span>Keamanan akunmu</span></div>
    </section>

    <section class="auth-panel">
        <div class="mobile-brand"><a class="brand" href="{{ route('login') }}"><span class="brand-mark login-brand-mark" aria-hidden="true">DR</span><span>Dapur Rapi</span></a></div>
        <div class="auth-form-wrap">
            <div class="auth-heading">
                <span class="eyebrow">RESET KATA SANDI</span>
                <h2>Buat kata<br>sandi baru.</h2>
                <p>Gunakan minimal 8 karakter.</p>
            </div>
            <div class="form-notice" data-notice role="status" aria-live="polite" hidden></div>
            <form class="auth-form" data-auth-form="reset-password">
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">
                <label class="field-label" for="password">Kata sandi baru</label>
                <div class="password-field-wrap">
                    <input class="text-field" id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="Masukkan kata sandi baru" data-reset-password required>
                    <button class="password-visibility-toggle" type="button" data-password-visibility data-password-target="reset-password" data-show-label="Tampilkan kata sandi" data-hide-label="Sembunyikan kata sandi" aria-label="Tampilkan kata sandi" aria-pressed="false" title="Tampilkan kata sandi">
                        <svg data-password-eye-open viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg data-password-eye-closed viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12c2.3 3.2 5.6 5 10 5s7.7-1.8 10-5"/><path d="m6 15-1 2m13-2 1 2"/></svg>
                    </button>
                </div>
                <label class="field-label" for="password_confirmation">Ulangi kata sandi baru</label>
                <div class="password-field-wrap">
                    <input class="text-field" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="72" placeholder="Masukkan sekali lagi" data-reset-password-confirmation required>
                    <button class="password-visibility-toggle" type="button" data-password-visibility data-password-target="reset-password-confirmation" data-show-label="Tampilkan konfirmasi kata sandi" data-hide-label="Sembunyikan konfirmasi kata sandi" aria-label="Tampilkan konfirmasi kata sandi" aria-pressed="false" title="Tampilkan konfirmasi kata sandi">
                        <svg data-password-eye-open viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg data-password-eye-closed viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12c2.3 3.2 5.6 5 10 5s7.7-1.8 10-5"/><path d="m6 15-1 2m13-2 1 2"/></svg>
                    </button>
                </div>
                <button class="button button-primary button-wide" type="submit"><span>Simpan kata sandi baru</span><span aria-hidden="true">↗</span></button>
            </form>
            <p class="auth-switch"><a href="{{ route('login') }}">Kembali ke halaman masuk</a></p>
        </div>
        <footer class="auth-footer"><span>© {{ date('Y') }} Dapur Rapi</span><span>Dibuat untuk ritme dapurmu.</span></footer>
    </section>
</main>
@endsection