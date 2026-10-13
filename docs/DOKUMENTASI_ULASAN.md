# Dokumentasi Fitur Ulasan

Dokumen ini menjelaskan rancangan penambahan tabel `ulasans` pada aplikasi Laravel `apk-sederhana`, termasuk struktur data, relasi, endpoint API, validasi, keamanan, pengujian, dan batasan arsitektur.

## 1. Tujuan

Fitur ulasan digunakan untuk menyimpan penilaian pengguna terhadap makanan yang tersedia. Satu ulasan terkait dengan:

- satu pengguna (`users`), dan
- satu makanan (`makanans`).

Setiap pengguna hanya boleh memberikan satu ulasan untuk satu makanan. Pengguna dapat membuat, melihat, memperbarui, dan menghapus ulasannya sendiri.

## 2. Batasan Scope

Implementasi fitur ini sengaja menggunakan proses sederhana dan sinkron.

### Termasuk

- migration tabel `ulasans`;
- model `Ulasan`;
- relasi `User`, `Makanans`, dan `Ulasan`;
- validasi input melalui `FormRequest`;
- endpoint CRUD satu data per request;
- autentikasi Sanctum;
- response JSON melalui API Resource;
- validasi kepemilikan ulasan;
- pengujian feature dan validation.

### Tidak termasuk

- batch processing atau bulk endpoint;
- job scheduling untuk ulasan;
- message queue atau dispatch job;
- caching response ulasan;
- monitoring khusus ulasan;
- notifikasi email ketika ulasan dibuat;
- import atau export massal.

Setiap operasi ulasan diproses langsung selama request HTTP berlangsung dan hasilnya dikembalikan pada request yang sama.

## 3. Struktur Tabel `ulasans`

Kolom yang disarankan:

| Kolom        | Tipe                  | Aturan                   | Keterangan              |
| ------------ | --------------------- | ------------------------ | ----------------------- |
| `id`         | bigint                | primary key              | ID ulasan               |
| `user_id`    | foreign key           | required, cascade delete | Pengguna pemberi ulasan |
| `makanan_id` | foreign key           | required, cascade delete | Makanan yang diulas     |
| `rating`     | unsigned tiny integer | required, 1 sampai 5     | Nilai penilaian         |
| `komentar`   | text                  | nullable                 | Isi ulasan              |
| `created_at` | timestamp             | otomatis                 | Waktu dibuat            |
| `updated_at` | timestamp             | otomatis                 | Waktu diperbarui        |

### Constraint penting

Gunakan unique index gabungan:

```php
$table->unique(['user_id', 'makanan_id']);
```

Constraint ini mencegah satu pengguna membuat ulasan ganda untuk makanan yang sama, termasuk jika validasi aplikasi terlewati.

## 4. Migration

Buat migration:

```bash
php artisan make:migration create_ulasans_table
```

Contoh isi migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ulasans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('makanan_id')
                ->constrained('makanans')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('komentar')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'makanan_id']);
            $table->index('makanan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ulasans');
    }
};
```

Jalankan migration:

```bash
php artisan migrate
```

Periksa status migration:

```bash
php artisan migrate:status
```

## 5. Model `Ulasan`

Buat model:

```bash
php artisan make:model Ulasan
```

Contoh model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ulasan extends Model
{
    protected $fillable = [
        'user_id',
        'makanan_id',
        'rating',
        'komentar',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function makanan()
    {
        return $this->belongsTo(Makanans::class);
    }
}
```

`user_id` sebaiknya diambil dari `auth()->id()`, bukan dari request client. Dengan demikian client tidak dapat membuat ulasan seolah-olah milik pengguna lain.

## 6. Relasi Model

Tambahkan relasi pada `User`:

```php
public function ulasans()
{
    return $this->hasMany(Ulasan::class);
}
```

Tambahkan relasi pada `Makanans`:

```php
public function ulasans()
{
    return $this->hasMany(Ulasan::class, 'makanan_id');
}
```

Relasi ini memungkinkan query seperti:

```php
$makanan->ulasans;
$user->ulasans;
$ulasan->makanan;
$ulasan->user;
```

## 7. Form Request

Buat request:

```bash
php artisan make:request StoreUlasanRequest
php artisan make:request UpdateUlasanRequest
```

### `StoreUlasanRequest`

```php
public function rules(): array
{
    return [
        'makanan_id' => ['required', 'integer', 'exists:makanans,id'],
        'rating' => ['required', 'integer', 'between:1,5'],
        'komentar' => ['nullable', 'string', 'max:2000'],
    ];
}
```

Tambahkan validasi duplikat di service atau controller dengan query berdasarkan user yang sedang login:

```php
$alreadyReviewed = Ulasan::where('user_id', auth()->id())
    ->where('makanan_id', $request->makanan_id)
    ->exists();
```

Jika hasilnya `true`, kembalikan HTTP `422` dengan pesan:

```json
{
    "status": false,
    "message": "Anda sudah memberikan ulasan untuk makanan ini"
}
```

### `UpdateUlasanRequest`

```php
public function rules(): array
{
    return [
        'rating' => ['required', 'integer', 'between:1,5'],
        'komentar' => ['nullable', 'string', 'max:2000'],
    ];
}
```

Pada update, `makanan_id` tidak perlu dikirim agar ulasan tidak dipindahkan ke makanan lain.

## 8. Endpoint API

Semua endpoint ulasan berada di middleware `auth:sanctum`.

| Method   | Endpoint                          | Fungsi                         |
| -------- | --------------------------------- | ------------------------------ |
| `GET`    | `/api/makanans/{makanan}/ulasans` | Melihat ulasan satu makanan    |
| `POST`   | `/api/ulasans`                    | Membuat satu ulasan            |
| `GET`    | `/api/ulasans/{ulasan}`           | Melihat detail ulasan          |
| `PUT`    | `/api/ulasans/{ulasan}`           | Mengubah ulasan milik sendiri  |
| `DELETE` | `/api/ulasans/{ulasan}`           | Menghapus ulasan milik sendiri |

Tidak ada endpoint berikut:

```text
POST /api/ulasans/bulk
POST /api/makanans/{makanan}/ulasans/bulk
```

## 9. Contoh Request Postman

### Membuat ulasan

```http
POST http://apk-sederhana.test/api/ulasans
Accept: application/json
Content-Type: application/json
Authorization: Bearer TOKEN_LOGIN
```

Body:

```json
{
    "makanan_id": 5,
    "rating": 5,
    "komentar": "Rasanya enak dan porsinya cukup."
}
```

Response sukses:

```json
{
    "status": true,
    "message": "Ulasan berhasil ditambahkan",
    "data": {
        "id": 1,
        "user_id": 1,
        "makanan_id": 5,
        "rating": 5,
        "komentar": "Rasanya enak dan porsinya cukup.",
        "created_at": "2026-09-16T10:00:00.000000Z",
        "updated_at": "2026-09-16T10:00:00.000000Z"
    }
}
```

### Melihat ulasan makanan

```http
GET http://apk-sederhana.test/api/makanans/5/ulasans
Accept: application/json
Authorization: Bearer TOKEN_LOGIN
```

### Mengubah ulasan

```http
PUT http://apk-sederhana.test/api/ulasans/1
Accept: application/json
Content-Type: application/json
Authorization: Bearer TOKEN_LOGIN
```

Body:

```json
{
    "rating": 4,
    "komentar": "Rasanya tetap enak, tetapi porsinya bisa ditambah."
}
```

### Menghapus ulasan

```http
DELETE http://apk-sederhana.test/api/ulasans/1
Accept: application/json
Authorization: Bearer TOKEN_LOGIN
```

## 10. Aturan Response

Gunakan format response yang konsisten:

### Sukses

```json
{
    "status": true,
    "message": "Operasi berhasil",
    "data": {}
}
```

### Validasi gagal

HTTP `422`:

```json
{
    "status": false,
    "message": "Validasi gagal",
    "errors": {
        "rating": ["The rating field must be between 1 and 5."]
    }
}
```

### Tidak terautentikasi

HTTP `401`:

```json
{
    "status": false,
    "message": "Autentikasi diperlukan"
}
```

### Resource tidak ditemukan

HTTP `404`:

```json
{
    "status": false,
    "message": "Ulasan tidak ditemukan"
}
```

### Tidak memiliki akses

HTTP `403`:

```json
{
    "status": false,
    "message": "Anda tidak memiliki akses ke ulasan ini"
}
```

## 11. Otorisasi

Pemilik ulasan harus diverifikasi sebelum update dan delete:

```php
if ($ulasan->user_id !== auth()->id()) {
    return response()->json([
        'status' => false,
        'message' => 'Anda tidak memiliki akses ke ulasan ini',
    ], 403);
}
```

Jangan menerima `user_id` dari body request. Nilai tersebut harus berasal dari token Sanctum:

```php
$data['user_id'] = auth()->id();
```

## 12. Service dan Controller

Gunakan service untuk aturan bisnis, misalnya:

```php
public function create(array $data): Ulasan
{
    $userId = auth()->id();

    $exists = Ulasan::where('user_id', $userId)
        ->where('makanan_id', $data['makanan_id'])
        ->exists();

    if ($exists) {
        throw new \DomainException('Anda sudah memberikan ulasan untuk makanan ini');
    }

    return Ulasan::create([
        'user_id' => $userId,
        'makanan_id' => $data['makanan_id'],
        'rating' => $data['rating'],
        'komentar' => $data['komentar'] ?? null,
    ]);
}
```

Controller tetap memproses satu ulasan per request:

```php
public function store(StoreUlasanRequest $request)
{
    $ulasan = $this->service->create($request->validated());

    return response()->json([
        'status' => true,
        'message' => 'Ulasan berhasil ditambahkan',
        'data' => new UlasanResource($ulasan->load(['user', 'makanan'])),
    ], 201);
}
```

## 13. Tanpa Batch Processing

Fitur ulasan tidak menggunakan array `items` dan tidak menerima banyak data dalam satu request.

Request yang benar:

```json
{
    "makanan_id": 5,
    "rating": 5,
    "komentar": "Sangat enak"
}
```

Request berikut tidak didukung:

```json
{
    "items": [
        { "makanan_id": 5, "rating": 5 },
        { "makanan_id": 6, "rating": 4 }
    ]
}
```

Jika beberapa ulasan perlu dibuat, client mengirim beberapa request `POST` terpisah. Ini membuat validasi, error response, dan audit setiap ulasan tetap sederhana.

## 14. Tanpa Job Scheduling

Ulasan tidak diproses oleh scheduler. Tidak perlu menambahkan jadwal seperti:

```php
Schedule::command('ulasans:process')->daily();
```

Pembuatan, pembaruan, dan penghapusan ulasan terjadi langsung saat endpoint dipanggil. Scheduler yang sudah digunakan untuk pengecekan stok makanan tetap merupakan fitur terpisah dan tidak terkait dengan ulasan.

## 15. Tanpa Message Queue

Jangan gunakan:

```php
SomeJob::dispatch($ulasan);
```

Simpan ulasan langsung menggunakan Eloquent pada request yang sama. Karena tidak ada job ulasan, tidak diperlukan worker tambahan seperti:

```bash
php artisan queue:work
```

Konfigurasi queue Redis yang dipakai aplikasi untuk fitur lain tidak mengubah desain ulasan ini. Fitur ulasan tetap sinkron dan tidak mengirim job ke queue.

## 16. Tanpa Caching

Query ulasan selalu mengambil data terbaru dari database. Jangan gunakan:

```php
Cache::remember(...);
Cache::forget(...);
```

Keuntungan pendekatan ini:

- perubahan ulasan langsung terlihat;
- tidak ada cache invalidation;
- perilaku lebih mudah dipahami saat testing;
- tidak bergantung pada service Redis untuk response ulasan.

Konfigurasi cache aplikasi secara umum boleh tetap digunakan untuk modul lain, tetapi endpoint ulasan tidak boleh menyimpan atau membaca hasil dari cache.

## 17. Tanpa Monitoring Khusus

Tidak ada dashboard atau metric khusus untuk ulasan. Error tetap dicatat oleh logging Laravel standar jika terjadi exception:

```php
Log::error('Gagal menyimpan ulasan', [
    'user_id' => auth()->id(),
    'makanan_id' => $data['makanan_id'] ?? null,
]);
```

Logging tidak sama dengan monitoring. Scope fitur ini hanya membutuhkan log aplikasi standar untuk troubleshooting, bukan scheduler monitoring, metric collector, atau alerting.

## 18. Pengujian yang Wajib

Minimal pengujian feature:

1. pengguna terautentikasi dapat membuat ulasan;
2. pengguna tanpa token menerima `401`;
3. `rating` kurang dari 1 ditolak;
4. `rating` lebih dari 5 ditolak;
5. `makanan_id` yang tidak ada ditolak;
6. user yang sama tidak dapat mengulas makanan yang sama dua kali;
7. user dapat mengubah ulasannya sendiri;
8. user tidak dapat mengubah ulasan milik user lain;
9. user dapat menghapus ulasannya sendiri;
10. ulasan untuk makanan yang dihapus ikut terhapus karena foreign key cascade;
11. tidak ada endpoint bulk ulasan;
12. response tidak membaca cache dan tidak dispatch job.

Contoh perintah:

```bash
php artisan test --filter=Ulasan
```

Jalankan seluruh test:

```bash
php artisan test --compact
```

## 19. Checklist Implementasi

- [ ] Buat migration `ulasans`.
- [ ] Jalankan `php artisan migrate`.
- [ ] Buat model `Ulasan`.
- [ ] Tambahkan relasi ke `User`.
- [ ] Tambahkan relasi ke `Makanans`.
- [ ] Buat `StoreUlasanRequest`.
- [ ] Buat `UpdateUlasanRequest`.
- [ ] Buat `UlasanResource`.
- [ ] Buat `UlasanService`.
- [ ] Buat `UlasansController`.
- [ ] Daftarkan route CRUD satu data.
- [ ] Pasang middleware `auth:sanctum`.
- [ ] Terapkan pengecekan kepemilikan.
- [ ] Tambahkan unique index `user_id` dan `makanan_id`.
- [ ] Pastikan tidak ada route bulk ulasan.
- [ ] Pastikan tidak ada job ulasan.
- [ ] Pastikan tidak ada jadwal ulasan.
- [ ] Pastikan tidak ada cache ulasan.
- [ ] Tambahkan feature test.
- [ ] Jalankan migration dan test di environment pengembangan.

## 20. Perintah Pengembangan

Perintah dasar yang relevan:

```bash
php artisan migrate
php artisan route:list --path=api/ulasans
php artisan test --compact
```

Jika menggunakan Redis untuk modul aplikasi lain, pastikan Redis aktif:

```bash
wsl -e redis-cli ping
```

Hasil yang benar:

```text
PONG
```

Redis tidak diperlukan oleh fitur ulasan karena fitur ini tidak menggunakan queue atau caching.

## 21. Catatan Keamanan

- Jangan commit file `.env` ke repository.
- Jangan menaruh password SMTP atau token API di dokumentasi publik.
- Gunakan token Sanctum melalui header `Authorization: Bearer ...`.
- Validasi semua input dari client.
- Ambil `user_id` dari user yang sedang login.
- Batasi panjang komentar untuk mencegah payload berlebihan.
- Gunakan foreign key agar ulasan tidak menunjuk ke data makanan atau user yang tidak ada.
- Pertahankan unique index meskipun validasi duplikat sudah ada di aplikasi.

## 22. Ringkasan Arsitektur

Alur ulasan yang diharapkan:

```text
Postman / Client
    |
    v
API Route + Sanctum
    |
    v
FormRequest Validation
    |
    v
UlasansController
    |
    v
UlasanService
    |
    v
Ulasan Model / Database
    |
    v
JSON Response
```

Tidak ada alur berikut:

```text
Scheduler -> Job -> Queue -> Ulasan
Cache -> Response Ulasan
Monitoring Worker -> Ulasan
Batch Items -> Ulasan
```

Dengan batasan tersebut, fitur ulasan tetap mudah diuji, mudah ditelusuri, dan konsisten dengan kebutuhan CRUD satu data per request.
