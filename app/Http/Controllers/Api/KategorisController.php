<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKategoriBatchRequest;
use App\Http\Requests\StoreKategoriRequest;
use App\Http\Requests\UpdateKategoriRequest;
use App\Http\Resources\KategoriResource;
use App\Services\KategoriService;

class KategorisController extends Controller
{
    protected KategoriService $service;

    public function __construct(KategoriService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        try {
            return response()->json([
                'status' => true,
                'data' => KategoriResource::collection($this->service->getAll()),
            ], 200);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function store(StoreKategoriRequest $request)
    {
        try {
            $kategori = $this->service->store($request->validated());

            return response()->json([
                'status' => true,
                'message' => 'Kategori berhasil ditambahkan',
                'data' => new KategoriResource($kategori),
            ], 201);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function show($id)
    {
        try {
            $kategori = $this->service->getById($id);

            if (! $kategori) {
                return response()->json([
                    'status' => false,
                    'message' => 'Kategori tidak ditemukan',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => new KategoriResource($kategori),
            ], 200);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function update(UpdateKategoriRequest $request, $id)
    {
        try {
            $result = $this->service->updateData($id, $request->validated());

            if (! $result['success']) {
                return response()->json([
                    'status' => false,
                    'message' => $result['message'],
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => $result['message'],
                'data' => new KategoriResource($result['data']),
            ], 200);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    public function destroy($id)
    {
        try {
            $result = $this->service->destroy($id);

            $statusCode = match ($result['status']) {
                'not_found' => 404,
                'conflict' => 409,
                default => 200,
            };

            return response()->json([
                'status' => $result['success'],
                'message' => $result['message'],
            ], $statusCode);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    // POST /api/kategoris/bulk
    public function storeBatch(StoreKategoriBatchRequest $request)
    {
        try {
            $total = $this->service->storeBatch($request->validated()['items']);

            return response()->json([
                'status' => true,
                'message' => "{$total} kategori berhasil ditambahkan sekaligus",
                'total' => $total,
            ], 201);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }
}
