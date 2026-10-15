@extends('layouts.app')

@section('title', 'Ringkasan')
@section('body-class', 'dashboard-page')

@section('content')
<div class="dashboard-shell" data-dashboard>
    <aside class="sidebar" id="dashboard-sidebar">
        <a class="brand sidebar-brand" href="{{ url('/dashboard') }}" data-restaurant-brand aria-haspopup="dialog"><span class="brand-mark" aria-hidden="true"><span data-brand-mark>DR</span><img data-brand-logo alt="" hidden></span><span data-restaurant-name>Dapur Rapi</span></a>
        <div class="workspace-label">RUANG KERJA</div>
        <nav class="side-nav" aria-label="Navigasi utama">
            <button class="nav-item is-active" type="button" data-page="overview"><span class="nav-icon" aria-hidden="true">⌂</span><span>Ringkasan</span></button>
            <button class="nav-item" type="button" data-page="foods"><span class="nav-icon" aria-hidden="true">◫</span><span>Daftar makanan</span><span class="nav-count" data-count="foods">0</span></button>
            <button class="nav-item" type="button" data-page="drinks"><span class="nav-icon" aria-hidden="true">◉</span><span>Daftar minuman</span><span class="nav-count" data-count="drinks">0</span></button>
            <button class="nav-item" type="button" data-page="categories"><span class="nav-icon" aria-hidden="true">▦</span><span>Kategori</span></button>
            <button class="nav-item" type="button" data-page="transactions"><span class="nav-icon" aria-hidden="true">↗</span><span>Transaksi</span></button>
            <button class="nav-item" type="button" data-page="login-activity" data-login-activity-nav hidden><span class="nav-icon" aria-hidden="true">◷</span><span>Aktivitas login</span></button>
        </nav>
        <div class="sidebar-bottom">
            <div class="sidebar-tip"><span class="tip-spark" aria-hidden="true">✳</span><p>Restoran ramai, perut kenyang, hati kami pun senang!</p></div>
            <div class="profile-row"><span class="avatar" data-avatar>DR</span><span class="profile-copy"><strong data-user-name>Dapur</strong><small data-user-email>Memuat profil...</small><small class="role-label" data-user-role-label></small></span><button class="icon-button" type="button" data-profile-open aria-label="Ubah profil" title="Ubah profil" hidden><span aria-hidden="true">✎</span></button><button class="icon-button logout-button" type="button" data-logout aria-label="Keluar" title="Keluar"><span aria-hidden="true">↗</span></button></div>
        </div>
    </aside>

    <main class="dashboard-main">
        <header class="topbar">
            <a class="brand topbar-brand" href="{{ url('/dashboard') }}" data-restaurant-brand aria-haspopup="dialog"><span class="brand-mark" aria-hidden="true"><span data-brand-mark>DR</span><img data-brand-logo alt="" hidden></span><span data-restaurant-name>Dapur Rapi</span></a>
            <div class="breadcrumb"><span>Ruang kerja</span><span class="breadcrumb-slash">/</span><strong data-breadcrumb>Ringkasan</strong></div>
            <div class="topbar-actions"><span class="today-label" data-date></span><button class="mobile-menu-button" type="button" data-menu-toggle aria-label="Buka menu" aria-controls="dashboard-sidebar" aria-expanded="false">☰</button></div>
        </header>

        <button class="mobile-menu-backdrop" type="button" data-menu-backdrop aria-label="Tutup menu"></button>

        <div class="dashboard-content">
            <section class="page-panel is-visible" data-panel="overview">
                <div class="page-heading">
                    <div><span class="eyebrow">DASHBOARD</span><h1><span data-greeting-label>Selamat pagi</span>, <span data-greeting-name>Dapur</span><span class="heading-period">.</span></h1><p>Berikut gambaran kecil tentang dapurmu hari ini.</p></div>
                    <button class="button button-primary" type="button" data-switch-page="foods"><span>Kelola makanan</span><span aria-hidden="true">↗</span></button>
                </div>
                <div class="low-stock-alert" data-low-stock-alert data-low-stock-threshold="{{ config('inventory.low_stock_threshold') }}" role="alert" hidden><span class="low-stock-alert-mark" aria-hidden="true">!</span><p data-low-stock-alert-text></p><button class="text-action" type="button" data-switch-page="foods">Lihat stok <span aria-hidden="true">→</span></button></div>
                <div class="overview-grid">
                    <article class="metric metric-main"><div class="metric-top"><span>Total menu aktif</span><span class="metric-icon metric-icon-green" aria-hidden="true">◫</span></div><strong class="metric-number" data-metric="foods">—</strong><span class="metric-note">makanan dan minuman</span><div class="metric-rule"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div></article>
                    <article class="metric"><div class="metric-top"><span>Kategori</span><span class="metric-icon metric-icon-yellow" aria-hidden="true">▦</span></div><strong class="metric-number" data-metric="categories">—</strong><span class="metric-note">kelompok menu</span></article>
                    <article class="metric"><div class="metric-top"><span data-metric-label="low-stock">Stok menipis</span><span class="metric-icon metric-icon-coral" data-metric-icon="low-stock" aria-hidden="true">⌁</span></div><strong class="metric-number" data-metric="low-stock">—</strong><span class="metric-note" data-metric-note="low-stock">perlu dicek hari ini</span></article>
                    <article class="metric"><div class="metric-top"><span>Transaksi</span><span class="metric-icon metric-icon-blue" aria-hidden="true">↗</span></div><strong class="metric-number" data-metric="transactions">—</strong><span class="metric-note">riwayat pemesanan</span></article>
                </div>
                <section class="content-section overview-lower" data-admin-stock-panel hidden>
                    <div class="section-heading"><div><span class="eyebrow">PANTAUAN</span><h2>Stok perlu perhatian</h2></div><button class="text-action" type="button" data-switch-page="foods">Lihat semua <span aria-hidden="true">→</span></button></div>
                    <div class="auto-delete-notice"><span class="auto-delete-notice-mark" aria-hidden="true">!</span><p><strong>Penghapusan stok otomatis:</strong> menu dengan stok {{ config('inventory.auto_drop_threshold') }} atau kurang akan dikeluarkan dari daftar saat pemeriksaan setiap jam. Setelah masuk tempat sampah, data dihapus permanen dalam 2 menit.</p></div>
                    <div class="table-wrap"><table class="data-table"><thead><tr><th>Nama makanan</th><th>Kategori</th><th>Harga</th><th>Stok tersisa</th><th>Status</th></tr></thead><tbody data-low-stock-rows><tr><td class="table-loading" colspan="5">Mengambil data dapur...</td></tr></tbody></table></div>
                </section>
                <section class="content-section recent-section">
                    <div class="section-heading"><div><span class="eyebrow">INVENTORI</span><h2>Menu dari daftar ini</h2></div><button class="text-action" type="button" data-switch-page="foods">Buka daftar <span aria-hidden="true">→</span></button></div>
                    <div class="recent-grid" data-recent-foods><div class="empty-inline">Memuat menu...</div></div>
                    <div class="recent-carousel-controls" data-recent-controls hidden>
                        <input class="recent-carousel-slider" type="range" min="0" max="0" step="1" value="0" data-recent-slider aria-label="Geser menu">
                    </div>
                </section>
            </section>

            <section class="page-panel" data-panel="foods">
                <div class="page-heading"><div><span class="eyebrow">INVENTORI</span><h1>Daftar makanan</h1><p>Menu untuk dipilih dan dipesan dari dapurmu.</p></div><div class="page-tools"><label class="search-field"><span aria-hidden="true">⌕</span><input type="search" placeholder="Cari makanan..." data-food-search aria-label="Cari makanan"></label><button class="button button-primary" type="button" data-create-food hidden><span>Tambah makanan</span><span aria-hidden="true">＋</span></button></div></div>
                <section class="content-section standalone-section"><div class="section-heading"><div><span class="eyebrow">MENU AKTIF</span><h2><span data-foods-heading>0</span> makanan</h2></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Nama makanan</th><th>Kategori</th><th>Deskripsi</th><th>Harga</th><th>Stok</th><th>Aksi</th></tr></thead><tbody data-food-rows><tr><td class="table-loading" colspan="6">Mengambil data dapur...</td></tr></tbody></table></div></section>
            </section>

            <section class="page-panel" data-panel="drinks">
                <div class="page-heading"><div><span class="eyebrow">KATALOG TERPISAH</span><h1>Daftar minuman</h1><p>Data minuman tersimpan terpisah dari tabel makanan.</p></div><div class="page-tools"><label class="search-field"><span aria-hidden="true">⌕</span><input type="search" placeholder="Cari minuman..." data-drink-search aria-label="Cari minuman"></label><button class="button button-primary" type="button" data-create-drink hidden><span>Tambah minuman</span><span aria-hidden="true">＋</span></button></div></div>
                <section class="content-section standalone-section"><div class="section-heading"><div><span class="eyebrow">MINUMAN AKTIF</span><h2><span data-drinks-heading>0</span> minuman</h2></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Nama minuman</th><th>Deskripsi</th><th>Harga</th><th>Stok</th><th>Aksi</th></tr></thead><tbody data-drink-rows><tr><td class="table-loading" colspan="5">Mengambil data minuman...</td></tr></tbody></table></div></section>
            </section>

            <section class="page-panel" data-panel="categories">
                <div class="page-heading"><div><span class="eyebrow">PENGELOMPOKAN</span><h1>Kategori menu</h1><p>Kelompok menu yang membantu semuanya mudah ditemukan.</p></div><button class="button button-primary" type="button" data-create-category hidden><span>Tambah kategori</span><span aria-hidden="true">＋</span></button></div>
                <div class="category-grid" data-category-grid><div class="empty-inline">Memuat kategori...</div></div>
            </section>

            <section class="page-panel" data-panel="transactions">
                <div class="page-heading"><div><span class="eyebrow">AKTIVITAS</span><h1>Transaksi</h1><p data-transactions-description>Daftar transaksi seluruh pengguna.</p></div></div>
                <section class="content-section standalone-section"><div class="section-heading"><div><span class="eyebrow">RIWAYAT</span><h2 data-transactions-heading>Semua transaksi</h2></div><div class="transaction-tools" data-admin-transaction-tools hidden><label class="search-field"><span aria-hidden="true">⌕</span><input type="search" placeholder="Cari nomor atau pemesan..." data-transaction-search aria-label="Cari transaksi"></label><select class="text-field transaction-filter-select" data-transaction-status-filter aria-label="Filter status transaksi"><option value="">Semua status</option><option value="pending">Menunggu</option><option value="diproses">Diproses</option><option value="selesai">Selesai</option><option value="dibatalkan">Dibatalkan</option></select><small class="transaction-result-count" data-transaction-result-count aria-live="polite"></small></div></div><div class="table-wrap"><table class="data-table"><thead><tr data-transaction-head><th>Nomor</th><th>Pemesan</th><th>Makanan</th><th>Tanggal</th><th>Total</th><th>Status pesanan</th><th>Pembayaran</th></tr></thead><tbody data-transaction-rows><tr><td class="table-loading" colspan="7">Mengambil transaksi...</td></tr></tbody></table></div><div class="transaction-pagination" data-transaction-pagination hidden><button class="table-action" type="button" data-transaction-previous disabled>‹ Sebelumnya</button><small data-transaction-page-label aria-live="polite"></small><button class="table-action" type="button" data-transaction-next disabled>Berikutnya ›</button></div></section>
            </section>
            <section class="page-panel" data-panel="login-activity">
                <div class="page-heading"><div><span class="eyebrow">AKUN</span><h1>Aktivitas login</h1><p>Pengguna dan waktu terakhir mereka masuk ke web.</p></div></div>
                <section class="content-section standalone-section"><div class="section-heading"><div><span class="eyebrow">PENGGUNA</span><h2>Login terakhir</h2></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Login terakhir</th></tr></thead><tbody data-login-activity-rows><tr><td class="table-loading" colspan="4">Mengambil aktivitas login...</td></tr></tbody></table></div></section>
            </section>
            <dialog class="app-dialog restaurant-profile-dialog" data-restaurant-dialog aria-label="Profil restoran">
                <section class="restaurant-profile-view" data-restaurant-profile-view hidden>
                    <div class="restaurant-profile-heading">
                        <div class="restaurant-profile-logo"><span data-profile-logo-mark>DR</span><img data-restaurant-view-logo data-logo-zoom alt="Logo restoran, klik untuk memperbesar" tabindex="0" role="button" title="Perbesar logo" hidden></div>
                        <div><span class="eyebrow">PROFIL RESTORAN</span><h2 id="restaurant-view-title" data-restaurant-view-name>Dapur Rapi</h2></div>
                    </div>
                    <p class="restaurant-profile-history" data-restaurant-view-history></p>
                    <div class="dialog-actions"><button class="button button-quiet" type="button" data-close-dialog>Tutup</button></div>
                </section>
                <form class="dialog-form" data-restaurant-form aria-labelledby="restaurant-edit-title" enctype="multipart/form-data" hidden>
                    <span class="eyebrow">PROFIL RESTORAN</span><h2 id="restaurant-edit-title">Kelola profil restoran</h2>
                    <div class="restaurant-logo-editor">
                        <div class="restaurant-profile-logo"><span data-editor-logo-mark>DR</span><img data-restaurant-editor-logo data-logo-zoom alt="Pratinjau logo restoran, klik untuk memperbesar" tabindex="0" role="button" title="Perbesar logo" hidden></div>
                        <label class="field-label" for="restaurant-logo">Logo restoran</label>
                        <input class="text-field" id="restaurant-logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" data-restaurant-logo-input>
                        <small class="field-hint">PNG, JPG, atau WebP. Gambar besar diperkecil otomatis.</small>
                    </div>
                    <label class="field-label" for="restaurant-name">Nama restoran</label><input class="text-field" id="restaurant-name" name="name" maxlength="100" data-restaurant-name-input required>
                    <label class="field-label" for="restaurant-history">Sejarah toko</label><textarea class="text-field text-area" id="restaurant-history" name="history" maxlength="5000" rows="5" data-restaurant-history-input placeholder="Ceritakan awal mula dan perjalanan restoran..."></textarea>
                    <p class="restaurant-form-error" data-restaurant-form-error role="alert" hidden></p>
                    <div class="dialog-actions"><button class="button button-quiet" type="button" data-close-dialog>Tutup</button><button class="button button-primary" type="submit"><span>Simpan profil</span></button></div>
                </form>
            </dialog>
            <dialog class="restaurant-logo-lightbox" data-logo-lightbox aria-label="Logo restoran diperbesar">
                <button class="icon-button restaurant-logo-lightbox-close" type="button" data-logo-lightbox-close aria-label="Tutup gambar" title="Tutup gambar">×</button>
                <img data-logo-lightbox-image alt="Logo restoran diperbesar">
            </dialog>
            <dialog class="app-dialog" data-food-dialog aria-labelledby="food-dialog-title">
                <form class="dialog-form" data-food-form>
                    <span class="eyebrow">KATALOG</span><h2 id="food-dialog-title" data-food-dialog-title>Tambah makanan</h2>
                    <input type="hidden" name="id" data-food-id>
                    <label class="field-label" for="food-name">Nama makanan</label><input class="text-field" id="food-name" name="nama_makanan" maxlength="150" required>
                    <label class="field-label" for="food-category">Kategori</label><select class="text-field" id="food-category" name="kategori_id" data-food-category required></select>
                    <div class="form-grid"><div><label class="field-label" for="food-price">Harga</label><input class="text-field" id="food-price" name="harga" type="number" min="0" step="100" required></div><div><label class="field-label" for="food-stock">Stok</label><input class="text-field" id="food-stock" name="stok" type="number" min="0" step="1" required></div></div>
                    <label class="field-label" for="food-description">Deskripsi</label><textarea class="text-field text-area" id="food-description" name="deskripsi" rows="3"></textarea>
                    <div class="dialog-actions"><button class="button button-quiet" type="button" data-close-dialog>Tutup</button><button class="button button-primary" type="submit"><span>Simpan makanan</span></button></div>
                </form>
            </dialog>
            <dialog class="app-dialog app-dialog-compact" data-drink-dialog aria-labelledby="drink-dialog-title">
                <form class="dialog-form" data-drink-form>
                    <span class="eyebrow">TABEL MINUMANS</span><h2 id="drink-dialog-title" data-drink-dialog-title>Tambah minuman</h2>
                    <input type="hidden" name="id" data-drink-id>
                    <label class="field-label" for="drink-name">Nama minuman</label><input class="text-field" id="drink-name" name="nama_minuman" maxlength="150" required>
                    <label class="field-label" for="drink-price">Harga</label><input class="text-field" id="drink-price" name="harga" type="number" min="0" step="100" required>
                    <label class="field-label" for="drink-stock">Stok</label><input class="text-field" id="drink-stock" name="stok" type="number" min="0" step="1" required>
                    <label class="field-label" for="drink-description">Deskripsi</label><textarea class="text-field text-area" id="drink-description" name="deskripsi" rows="3"></textarea>
                    <div class="dialog-actions"><button class="button button-quiet" type="button" data-close-dialog>Tutup</button><button class="button button-primary" type="submit"><span>Simpan minuman</span></button></div>
                </form>
            </dialog>
            <dialog class="app-dialog app-dialog-compact" data-category-dialog aria-labelledby="category-dialog-title">
                <form class="dialog-form" data-category-form>
                    <span class="eyebrow">PENGELOMPOKAN</span><h2 id="category-dialog-title" data-category-dialog-title>Tambah kategori</h2>
                    <input type="hidden" name="id" data-category-id>
                    <label class="field-label" for="category-name">Nama kategori</label><input class="text-field" id="category-name" name="nama_kategori" maxlength="100" required>
                    <div class="dialog-actions"><button class="button button-quiet" type="button" data-close-dialog>Tutup</button><button class="button button-primary" type="submit"><span>Simpan kategori</span></button></div>
                </form>
            </dialog>
            <dialog class="app-dialog app-dialog-compact" data-purchase-dialog aria-labelledby="purchase-dialog-title">
                <form class="dialog-form" data-purchase-form>
                    <span class="eyebrow" data-purchase-eyebrow>LANGKAH 1 DARI 2</span><h2 id="purchase-dialog-title" data-purchase-title>Pesanan</h2>
                    <input type="hidden" name="makanan_id" data-purchase-id>
                    <input type="hidden" name="minuman_id" data-purchase-minuman-id>
                    <section class="purchase-step" data-purchase-step="details">
                        <p class="payment-item-name" data-purchase-name>Pilih makanan</p>
                        <p class="dialog-description" data-purchase-price></p>
                        <label class="field-label" for="purchase-quantity">Jumlah pesanan</label><input class="text-field" id="purchase-quantity" name="qty" type="number" min="1" value="1" step="1" required>
                        <div class="dialog-actions"><button class="button button-quiet" type="button" data-close-dialog>Batal</button><button class="button button-primary" type="button" data-payment-next><span>Lanjut ke pembayaran</span><span aria-hidden="true">→</span></button></div>
                    </section>
                    <section class="purchase-step" data-purchase-step="payment" hidden>
                        @php
                            $paymentSettings = [
                                 'banks' => config('payments.banks', []),
                                'qrisPayload' => config('payments.qris_payload'),
                                'qrisImageUrl' => config('payments.qris_image_url'),
                            ];
                        @endphp
                        <script type="application/json" data-payment-settings>@json($paymentSettings)</script>
                        <div class="payment-summary" aria-label="Ringkasan pembayaran">
                            <div class="payment-summary-row"><span data-payment-item-name>Makanan</span><span data-payment-item-quantity>1 × Rp 0</span></div>
                            <div class="payment-summary-row payment-summary-total"><strong>Total pembayaran</strong><strong data-payment-total>Rp 0</strong></div>
                        </div>
                        <fieldset class="payment-methods">
                            <legend class="field-label">Pilih metode pembayaran</legend>
                            <label class="payment-method-option"><input type="radio" name="payment_method" value="bank_transfer"><span class="payment-method-copy"><strong>Transfer bank</strong><small data-bank-method-hint>Rekening akan ditampilkan setelah metode dipilih</small></span><span class="payment-method-check" aria-hidden="true"></span></label>
                            <label class="payment-method-option"><input type="radio" name="payment_method" value="qris"><span class="payment-method-copy"><strong>QRIS</strong><small data-qris-method-hint>Barcode QRIS merchant</small></span><span class="payment-method-check" aria-hidden="true"></span></label>
                            <label class="payment-method-option"><input type="radio" name="payment_method" value="cash_on_pickup"><span class="payment-method-copy"><strong>Tunai saat ambil</strong><small>Bayar langsung ketika pesanan diambil</small></span><span class="payment-method-check" aria-hidden="true"></span></label>
                        </fieldset>
                        <section class="payment-provider-panel" data-bank-payment-panel hidden>
                            <p class="payment-account-heading">Rekening tujuan transfer</p>
                            <p class="payment-unavailable" data-bank-unavailable hidden>Rekening tujuan belum tersedia.</p>
                            <dl class="payment-account-details" data-payment-account-details hidden>
                                <div><dt>Bank</dt><dd data-payment-bank-name></dd></div>
                                <div><dt>Nomor rekening</dt><dd data-payment-account-number></dd></div>
                                <div><dt>Atas nama</dt><dd data-payment-account-name></dd></div>
                            </dl>
                        </section>
                        <section class="payment-provider-panel qris-payment-panel" data-qris-payment-panel hidden>
                            <img class="qris-code-image" data-qris-code alt="Barcode QRIS pembayaran" hidden referrerpolicy="no-referrer">
                            <p class="payment-unavailable qris-unavailable" data-qris-unavailable hidden>QRIS belum dikonfigurasi pengelola.</p>
                            <p class="qris-caption">Pindai menggunakan aplikasi pembayaran yang mendukung QRIS.</p>
                        </section>
                        <div class="payment-instructions"><span class="payment-instructions-mark" aria-hidden="true">i</span><p data-payment-instructions>Pesanan dicatat menunggu pembayaran. Minta nomor rekening kepada pengelola; pembayaran belum diverifikasi otomatis.</p></div>
                        <div class="dialog-actions"><button class="button button-quiet" type="button" data-payment-back>Ubah pesanan</button><button class="button button-primary" type="submit" data-payment-confirm><span>Konfirmasi pesanan</span><span aria-hidden="true">↗</span></button></div>
                    </section>
                </form>
            </dialog>
            <dialog class="app-dialog app-dialog-compact" data-profile-dialog aria-labelledby="profile-dialog-title">
                <form class="dialog-form" data-profile-form>
                    <span class="eyebrow">PENGATURAN AKUN</span><h2 id="profile-dialog-title">Ubah nama profil</h2>
                    <p class="dialog-description">Nama ini akan tampil di dashboard akunmu.</p>
                    <label class="field-label" for="profile-name">Nama tampilan</label><input class="text-field" id="profile-name" name="name" data-profile-name maxlength="100" autocomplete="name" required>
                    <div class="dialog-actions"><button class="button button-quiet" type="button" data-close-dialog>Tutup</button><button class="button button-primary" type="submit"><span>Simpan nama</span></button></div>
                </form>
            </dialog>
            <div class="admin-order-notifications" data-admin-order-notifications role="region" aria-label="Pesanan baru" aria-live="polite" hidden></div>
            <div class="dashboard-notice" data-dashboard-notice role="status" aria-live="polite" hidden></div>
            <footer class="dashboard-footer"><span><span data-restaurant-name>Dapur Rapi</span> <span aria-hidden="true">·</span> ruang kecil untuk dapur yang teratur.</span><span>Semoga harimu lezat.</span></footer>
        </div>
    </main>
</div>
@endsection
