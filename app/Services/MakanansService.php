<?php

namespace App\Services;

use App\Jobs\LogMakananCreated;
use App\Repositories\Interfaces\KategorisRepositoryInterface;
use App\Repositories\Interfaces\MakanansRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class MakanansService
{
    protected MakanansRepositoryInterface $repository;

    protected KategorisRepositoryInterface $kategoriRepository;

    private array $keywordMinuman = ['air', 'jus', 'teh', 'kopi', 'soda', 'minuman', 'milkshake', 'es'];

    private array $keywordMakananBerat = ['nasi', 'mie', 'mi ', 'ayam', 'ikan', 'daging', 'soto', 'bakso', 'sop', 'sup', 'rendang', 'gulai', 'seblak', 'burger', 'pizza', 'pasta', 'kwetiau', 'capcay', 'pempek', 'rawon', 'opor', 'ketoprak'];

    private array $keywordMakananRingan = ['snack', 'camilan', 'keripik', 'kripik', 'kerupuk', 'gorengan', 'roti', 'kue', 'biskuit', 'pisang', 'kentang'];

    public function __construct(MakanansRepositoryInterface $repository, KategorisRepositoryInterface $kategoriRepository)
    {
        $this->repository = $repository;
        $this->kategoriRepository = $kategoriRepository;
    }

    public function getAll(array $filters = [])
    {
        return $this->repository->all($filters);
    }

    public function getById($id)
    {
        return $this->repository->find($id);
    }

    // Resolusi kategori: terima kategori_id ATAU nama kategori
    public function resolveKategoriId(array $data): array
    {
        // errors: null = ok, string = pesan error
        if (isset($data['kategori']) && ! isset($data['kategori_id'])) {
            $kategori = $this->kategoriRepository->findByName($data['kategori']);

            if (! $kategori) {
                return ['error' => 'kategori tidak ditemukan'];
            }

            $data['kategori_id'] = $kategori->id;
        }

        return ['data' => $data, 'error' => null];
    }

    public function isLikelyMinuman(string $nama): bool
    {
        $lower = strtolower($nama);

        foreach ($this->keywordMinuman as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    public function kategoriSesuaiNama(string $namaMakanan, $kategoriId): bool
    {
        $kategori = $this->kategoriRepository->find($kategoriId);

        if (! $kategori) {
            return false;
        }

        $jenisMakanan = $this->tentukanJenisMakanan($namaMakanan);
        $jenisKategori = $this->tentukanJenisKategori($kategori->nama_kategori);

        return $jenisMakanan !== null && $jenisMakanan === $jenisKategori;
    }

    public function isKategoriMakanan($kategoriId): bool
    {
        $kategori = $this->kategoriRepository->find($kategoriId);

        return $kategori && str_contains(strtolower($kategori->nama_kategori), 'makanan');
    }

    private function isLikelyMakananBerat(string $nama): bool
    {
        foreach ($this->keywordMakananBerat as $keyword) {
            if (str_contains(strtolower($nama), $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function isLikelyMakananRingan(string $nama): bool
    {
        foreach ($this->keywordMakananRingan as $keyword) {
            if (str_contains(strtolower($nama), $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function tentukanJenisMakanan(string $nama): ?string
    {
        $nama = strtolower(trim($nama));

        if ($this->isLikelyMinuman($nama)) {
            return 'minuman_dingin';
        }

        if ($this->isLikelyMakananBerat($nama)) {
            return 'makanan_berat';
        }

        if ($this->isLikelyMakananRingan($nama)) {
            return 'makanan_ringan';
        }

        return null;
    }

    private function tentukanJenisKategori(string $namaKategori): ?string
    {
        $namaKategori = strtolower(trim($namaKategori));

        return match (true) {
            $namaKategori === 'makanan berat' => 'makanan_berat',
            $namaKategori === 'makanan ringan' => 'makanan_ringan',
            str_contains($namaKategori, 'minuman') => 'minuman_dingin',
            default => null,
        };
    }

    public function store(array $input)
    {
        // Use resolveKategoriId for category resolution if the controller hasn't already resolved it.
        $resolved = $this->resolveKategoriId($input);

        if ($resolved['error']) {
            return null;
        }

        $input = $resolved['data'];

        $makanan = $this->repository->create($input);

        LogMakananCreated::dispatch($makanan);

        return $makanan;
    }

    public function updateData($id, array $input)
    {
        $makanan = $this->repository->find($id);

        if (! $makanan) {
            return null;
        }

        $resolved = $this->resolveKategoriId($input);

        if ($resolved['error']) {
            return null;
        }

        $updatedMakanan = $this->repository->update($id, $resolved['data']);

        if ($updatedMakanan->stok > config('inventory.low_stock_threshold')) {
            Cache::forget('low-stock-email:'.$updatedMakanan->getKey());
        }

        return $updatedMakanan;
    }

    public function destroy($id)
    {
        $makanan = $this->repository->find($id);

        if (! $makanan) {
            return false;
        }

        return $this->repository->delete($id);
    }

    public function storeBatch(array $items): int
    {
        foreach ($items as $item) {
            if (! $this->kategoriSesuaiNama($item['nama_makanan'], $item['kategori_id'])) {
                throw new \InvalidArgumentException('Kategori tidak sesuai dengan jenis makanan: '.$item['nama_makanan']);
            }
        }

        return $this->repository->createBatch($items);
    }

    public function getTrashed()
    {
        return $this->repository->allTrashed();
    }

    public function restore($id)
    {
        return $this->repository->restore($id);
    }

    public function forceDelete($id)
    {
        return $this->repository->forceDelete($id);
    }
}
