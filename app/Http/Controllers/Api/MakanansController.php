<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMakananBatchRequest;
use App\Http\Requests\StoreMakananRequest;
use App\Http\Requests\UpdateMakananRequest;
use App\Http\Resources\MakananResource;
use App\Services\MakanansService;
use Illuminate\Http\Request;

class MakanansController extends Controller
{
    protected MakanansService $service;

    public function __construct(MakanansService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        try {
            $data = $this->service->getAll($request->only('search'));

            return response()->json([
                'status' => true,
                'message' => 'Data makanan berhasil diambil',
                'data' => MakananResource::collection($data),
            ], 200);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function store(StoreMakananRequest $request)
    {
        try {
            $makanan = $this->service->store($request->validated());

            if (! $makanan) {
                return response()->json([
                    'status' => false,
                    'message' => 'Kategori tidak ditemukan',
                ], 422);
            }

            $makanan->load('kategori');

            return response()->json([
                'status' => true,
                'message' => 'Makanan berhasil ditambahkan',
                'data' => new MakananResource($makanan),
            ], 201);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function show($id)
    {
        try {
            $makanan = $this->service->getById($id);

            if (! $makanan) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data tidak ditemukan',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => new MakananResource($makanan),
            ], 200);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function update(UpdateMakananRequest $request, $id)
    {
        try {
            $makanan = $this->service->updateData($id, $request->validated());

            if (! $makanan) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data makanan tidak ditemukan',
                ], 404);
            }

            $makanan->load('kategori');

            return response()->json([
                'status' => true,
                'message' => 'Makanan berhasil diperbarui',
                'data' => new MakananResource($makanan),
            ], 200);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function destroy($id)
    {
        try {
            $deleted = $this->service->destroy($id);

            if (! $deleted) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data tidak ditemukan',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Makanan berhasil dihapus',
            ], 200);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    // POST /api/makanans/bulk
    public function storeBatch(StoreMakananBatchRequest $request)
    {
        try {
            $items = $request->validated()['items'];

            foreach ($items as $item) {
                if ($this->service->isLikelyMinuman($item['nama_makanan'])) {
                    return response()->json([
                        'status' => false,
                        'message' => "Nama makanan '{$item['nama_makanan']}' terindikasi minuman",
                    ], 422);
                }

                if (! $this->service->kategoriSesuaiNama($item['nama_makanan'], $item['kategori_id'])) {
                    return response()->json([
                        'status' => false,
                        'message' => "Kategori tidak sesuai untuk makanan '{$item['nama_makanan']}'",
                    ], 422);
                }
            }

            $total = $this->service->storeBatch($items);

            return response()->json([
                'status' => true,
                'message' => "$total data makanan berhasil ditambahkan",
                'total' => $total,
            ], 201);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    // GET /api/makanans/trashed
    public function trashed()
    {
        try {
            $data = $this->service->getTrashed();

            return response()->json([
                'status' => true,
                'message' => 'Data makanan yang terhapus berhasil diambil',
                'data' => MakananResource::collection($data),
            ], 200);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    // PUT /api/makanans/{id}/restore
    public function restore($id)
    {
        try {
            $makanan = $this->service->restore($id);

            if (! $makanan) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data tidak ditemukan di tempat sampah',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Makanan berhasil dipulihkan',
                'data' => new MakananResource($makanan),
            ], 200);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    // DELETE /api/makanans/{id}/force
    public function forceDelete($id)
    {
        try {
            $deleted = $this->service->forceDelete($id);

            if (! $deleted) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data tidak ditemukan di tempat sampah',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Makanan berhasil dihapus permanen',
            ], 200);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }
}
