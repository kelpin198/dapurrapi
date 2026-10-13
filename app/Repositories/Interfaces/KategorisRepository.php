<?php

namespace App\Repositories\Interfaces;

use App\Models\Kategoris;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class KategorisRepository implements KategorisRepositoryInterface
{
    protected Kategoris $model;

    protected const CACHE_TTL = 300; // 5 menit

    public function __construct(Kategoris $model)
    {
        $this->model = $model;
    }

    public function all()
    {
        return Cache::remember('kategoris:all', self::CACHE_TTL, function () {
            return $this->model->all();
        });
    }

    public function find($id)
    {
        $cacheKey = "kategoris:find:{$id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($id) {
            return $this->model->find($id);
        });
    }

    public function findByName(string $nama)
    {
        return $this->model->whereRaw('lower(nama_kategori) = ?', [strtolower($nama)])->first();
    }

    public function create(array $data)
    {
        $kategori = $this->model->create($data);
        Cache::flush();

        return $kategori;
    }

    public function update($id, array $data)
    {
        $kategori = $this->find($id);

        if (! $kategori) {
            return null;
        }

        $kategori->update($data);
        Cache::flush();

        return $kategori;
    }

    public function delete($id)
    {
        $kategori = $this->find($id);

        if (! $kategori) {
            return false;
        }

        $result = $kategori->delete();
        Cache::flush();

        return $result;
    }

    public function hasRelatedMakanan($id): bool
    {
        $kategori = $this->find($id);

        return $kategori ? $kategori->makanans()->count() > 0 : false;
    }

    public function createBatch(array $items): int
    {
        $now = now();

        $rows = array_map(function ($item) use ($now) {
            return [
                'nama_kategori' => $item['nama_kategori'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $items);

        DB::table('kategoris')->insert($rows);
        Cache::flush();

        return count($rows);
    }
}
