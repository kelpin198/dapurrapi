<?php

namespace App\Repositories\Interfaces;

use App\Models\Makanans;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MakanansRepository implements MakanansRepositoryInterface
{
    protected Makanans $model;

    protected const CACHE_TTL = 300; // 5 menit

    public function __construct(Makanans $model)
    {
        $this->model = $model;
    }

    public function all(array $filters = [])
    {
        $cacheKey = 'makanans:all:'.md5(json_encode($filters));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters) {
            $query = $this->model->with('kategori')
                ->select('id', 'nama_makanan', 'kategori_id', 'harga', 'stok', 'deskripsi');

            if (! empty($filters['search'])) {
                $query->where('nama_makanan', 'like', '%'.$filters['search'].'%');
            }

            return $query->paginate(10);
        });
    }

    public function find($id)
    {
        $cacheKey = "makanans:find:{$id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($id) {
            return $this->model->with('kategori')->find($id);
        });
    }

    public function create(array $data)
    {
        $makanan = $this->model->create($data);
        Cache::flush();

        return $makanan;
    }

    public function update($id, array $data)
    {
        $makanan = $this->model->find($id);

        if (! $makanan) {
            return null;
        }

        $makanan->update($data);
        Cache::flush();

        return $makanan;
    }

    public function delete($id)
    {
        $makanan = $this->model->find($id);
        if (! $makanan) {
            return false;
        }

        $result = $makanan->delete();
        Cache::flush();

        return $result;
    }

    public function findByKategori($kategoriId)
    {
        return $this->model->where('kategori_id', $kategoriId)->get();
    }

    public function createBatch(array $items): int
    {
        $now = now();
        $rows = array_map(function ($item) use ($now) {
            return [
                'nama_makanan' => $item['nama_makanan'],
                'kategori_id' => $item['kategori_id'],
                'harga' => $item['harga'],
                'stok' => $item['stok'],
                'deskripsi' => $item['deskripsi'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $items);
        DB::table('makanans')->insert($rows);
        Cache::flush();

        return count($rows);
    }

    public function allTrashed()
    {
        return Makanans::onlyTrashed()->with('kategori')->paginate(10);
    }

    public function restore($id)
    {
        $makanan = Makanans::onlyTrashed()->find($id);

        if (! $makanan) {
            return null;
        }

        $makanan->restore();
        Cache::flush();

        return $makanan->fresh('kategori');
    }

    public function forceDelete($id)
    {
        $makanan = Makanans::onlyTrashed()->find($id);

        if (! $makanan) {
            return false;
        }

        $result = $makanan->forceDelete();
        Cache::flush();

        return $result;
    }
}
