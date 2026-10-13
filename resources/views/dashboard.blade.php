@extends('layouts.app')

@section('title', 'Ringkasan')
@section('body-class', 'dashboard-page')

@section('content')
<div class="dashboard-shell" data-dashboard>
    <aside class="sidebar" id="dashboard-sidebar">
        <a class="brand sidebar-brand" href="{{ url('/dashboard') }}"><span class="brand-mark" aria-hidden="true">DR</span><span>Dapur Rapi</span></a>
        <div class="workspace-label">RUANG KERJA</div>
        <nav class="side-nav" aria-label="Navigasi utama">
            <button class="nav-item is-active" type="button" data-page="overview"><span class="nav-icon" aria-hidden="true">⌂</span><span>Ringkasan</span></button>
            <button class="nav-item" type="button" data-page="foods"><span class="nav-icon" aria-hidden="true">◫</span><span>Daftar makanan</span><span class="nav-count" data-count="foods">0</span></button>
            <button class="nav-item" type="button" data-page="categories"><span class="nav-icon" aria-hidden="true">▦</span><span>Kategori</span></button>
            <button class="nav-item" type="button" data-page="transactions"><span class="nav-icon" aria-hidden="true">↗</span><span>Transaksi</span></button>
            <button class="nav-item" type="button" data-page="login-activity" data-login-activity-nav hidden><span class="nav-icon" aria-hidden="true">◷</span><span>Aktivitas login</span></button>
        </nav>
        <div class="sidebar-bottom">
            <div class="sidebar-tip"><span class="tip-spark" aria-hidden="true">✳</span><p>Stok yang terpantau membuat dapur lebih siap.</p></div>
            <div class="profile-row"><span class="avatar" data-avatar>DR</span><span class="profile-copy"><strong data-user-name>Dapur</strong><small data-user-email>Memuat profil...</small><small class="role-label" data-user-role-label></small></span><button class="icon-button" type="button" data-profile-open aria-label="Ubah profil" title="Ubah profil" hidden><span aria-hidden="true">✎</span></button><button class="icon-button logout-button" type="button" data-logout aria-label="Keluar" title="Keluar"><span aria-hidden="true">↗</span></button></div>
        </div>
    </aside>

    <main class="dashboard-main">
        <header class="topbar">
            <div class="breadcrumb"><span>Ruang kerja</span><span class="breadcrumb-slash">/</span><strong data-breadcrumb>Ringkasan</strong></div>
            <div class="topbar-actions"><span class="today-label" data-date></span><button class="mobile-menu-button" type="button" data-menu-toggle aria-label="Buka menu" aria-controls="dashboard-sidebar" aria-expanded="false">☰</button></div>
        </header>

        <button class="mobile-menu-backdrop" type="button" data-menu-backdrop aria-label="Tutup menu"></button>

        <div class="dashboard-content">
            <section class="page-panel is-visible" data-panel="overview">
                <div class="page-heading">
                    <div><span class="eyebrow">DASHBOARD</span><h1>Selamat pagi, <span data-greeting-name>Dapur</span><span class="heading-period">.</span></h1><p>Berikut gambaran kecil tentang dapurmu hari ini.</p></div>
                    <button class="button button-primary" type="button" data-switch-page="foods"><span>Kelola makanan</span><span aria-hidden="true">↗</span></button>
                </div>
                <div class="low-stock-alert" data-low-stock-alert data-low-stock-threshold="{{ config('inventory.low_stock_threshold') }}" role="alert" hidden><span class="low-stock-alert-mark" aria-hidden="true">!</span><p data-low-stock-alert-text></p><button class="text-action" type="button" data-switch-page="foods">Lihat stok <span aria-hidden="true">→</span></button></div>
                <div class="overview-grid">
                    <article class="metric metric-main"><div class="metric-top"><span>Total menu aktif</span><span class="metric-icon metric-icon-green" aria-hidden="true">◫</span></div><strong class="metric-number" data-metric="foods">—</strong><span class="metric-note">makanan dalam daftar</span><div class="metric-rule"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div></article>
                    <article class="metric"><div class="metric-top"><span>Kategori</span><span class="metric-icon metric-icon-yellow" aria-hidden="true">▦</span></div><strong class="metric-number" data-metric="categories">—</strong><span class="metric-note">kelompok menu</span></article>
                    <article class="metric"><div class="metric-top"><span>Stok menipis</span><span class="metric-icon metric-icon-coral" aria-hidden="true">⌁</span></div><strong class="metric-number" data-metric="low-stock">—</strong><span class="metric-note" data-metric-note="low-stock">perlu dicek hari ini</span></article>
                    <article class="metric"><div class="metric-top"><span>Transaksi</span><span class="metric-icon metric-icon-blue" aria-hidden="true">↗</span></div><strong class="metric-number" data-metric="transactions">—</strong><span class="metric-note">riwayat akunmu</span></article>
                </div>
                <section class="content-section overview-lower" data-admin-stock-panel hidden>
                    <div class="section-heading"><div><span class="eyebrow">PANTAUAN</span><h2>Stok perlu perhatian</h2></div><button class="text-action" type="button" data-switch-page="foods">Lihat semua <span aria-hidden="true">→</span></button></div>
                    <div class="table-wrap"><table class="data-table"><thead><tr><th>Nama makanan</th><th>Kategori</th><th>Harga</th><th>Stok tersisa</th><th>Status</th></tr></thead><tbody data-low-stock-rows><tr><td class="table-loading" colspan="5">Mengambil data dapur...</td></tr></tbody></table></div>
                </section>
                <section class="content-section recent-section">
                    <div class="section-heading"><div><span class="eyebrow">INVENTORI</span><h2>Menu dari daftar ini</h2></div><button class="text-action" type="button" data-switch-page="foods">Buka daftar <span aria-hidden="true">→</span></button></div>
                    <div class="recent-grid" data-recent-foods><div class="empty-inline">Memuat menu...</div></div>
                </section>
            </section>

            <section class="page-panel" data-panel="foods">
                <div class="page-heading"><div><span class="eyebrow">INVENTORI</span><h1>Daftar makanan</h1><p>Menu untuk dipilih dan dipesan dari dapurmu.</p></div><div class="page-tools"><label class="search-field"><span aria-hidden="true">⌕</span><input type="search" placeholder="Cari makanan..." data-food-search aria-label="Cari makanan"></label><button class="button button-primary" type="button" data-create-food hidden><span>Tambah makanan</span><span aria-hidden="true">＋</span></button></div></div>
                <section class="content-section standalone-section"><div class="section-heading"><div><span class="eyebrow">MENU AKTIF</span><h2><span data-foods-heading>0</span> makanan</h2></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Nama makanan</th><th>Kategori</th><th>Deskripsi</th><th>Harga</th><th>Stok</th><th>Aksi</th></tr></thead><tbody data-food-rows><tr><td class="table-loading" colspan="6">Mengambil data dapur...</td></tr></tbody></table></div></section>
            </section>

            <section class="page-panel" data-panel="categories">
                <div class="page-heading"><div><span class="eyebrow">PENGELOMPOKAN</span><h1>Kategori menu</h1><p>Kelompok menu yang membantu semuanya mudah ditemukan.</p></div><button class="button button-primary" type="button" data-create-category hidden><span>Tambah kategori</span><span aria-hidden="true">＋</span></button></div>
                <div class="category-grid" data-category-grid><div class="empty-inline">Memuat kategori...</div></div>
            </section>

            <section class="page-panel" data-panel="transactions">
                <div class="page-heading"><div><span class="eyebrow">AKTIVITAS</span><h1>Transaksi</h1><p>Riwayat transaksi yang tercatat di akunmu.</p></div></div>
                <section class="content-section standalone-section"><div class="section-heading"><div><span class="eyebrow">RIWAYAT</span><h2>Aktivitas terbaru</h2></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Nomor</th><th>Tanggal</th><th>Total</th><th>Status</th></tr></thead><tbody data-transaction-rows><tr><td class="table-loading" colspan="4">Mengambil transaksi...</td></tr></tbody></table></div></section>
            </section>
            <section class="page-panel" data-panel="login-activity">
                <div class="page-heading"><div><span class="eyebrow">AKUN</span><h1>Aktivitas login</h1><p>Pengguna dan waktu terakhir mereka masuk ke web.</p></div></div>
                <section class="content-section standalone-section"><div class="section-heading"><div><span class="eyebrow">PENGGUNA</span><h2>Login terakhir</h2></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Nama</th><th>Email</th><th>Peran</th><th>Login terakhir</th></tr></thead><tbody data-login-activity-rows><tr><td class="table-loading" colspan="4">Mengambil aktivitas login...</td></tr></tbody></table></div></section>
            </section>
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
                    <span class="eyebrow">PESANAN BARU</span><h2 id="purchase-dialog-title" data-purchase-name>Pilih makanan</h2>
                    <p class="dialog-description" data-purchase-price></p>
                    <input type="hidden" name="makanan_id" data-purchase-id>
                    <label class="field-label" for="purchase-quantity">Jumlah pesanan</label><input class="text-field" id="purchase-quantity" name="qty" type="number" min="1" value="1" step="1" required>
                    <div class="dialog-actions"><button class="button button-quiet" type="button" data-close-dialog>Batal</button><button class="button button-primary" type="submit"><span>Buat pesanan</span><span aria-hidden="true">↗</span></button></div>
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
            <div class="dashboard-notice" data-dashboard-notice role="status" aria-live="polite" hidden></div>
            <footer class="dashboard-footer"><span>Dapur Rapi <span aria-hidden="true">·</span> ruang kecil untuk dapur yang teratur.</span><span>Semoga harimu lezat.</span></footer>
        </div>
    </main>
</div>
@endsection
