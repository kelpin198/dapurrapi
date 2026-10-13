<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransaksiRequest;
use App\Http\Requests\UpdateTransaksiStatusRequest;
use App\Http\Resources\TransaksiResource;
use App\Services\TransaksisService;
use Illuminate\Http\Request;

class TransaksisController extends Controller
{
    protected TransaksisService $service;

    public function __construct(TransaksisService $service)
    {
        $this->service = $service;
    }

    // GET /api/transaksis
    public function index(Request $request)
    {
        try {
            $data = $this->service->getMyTransaksi($request->user()->id);

            return response()->json([
                'status' => true,
                'data' => TransaksiResource::collection($data),
            ], 200);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    // POST /api/transaksis
    public function store(StoreTransaksiRequest $request)
    {
        try {
            $data = $request->validated();
            $data['user_id'] = $request->user()->id;

            $result = $this->service->store($data);

            if (! $result['success']) {
                return response()->json([
                    'status' => false,
                    'message' => $result['message'],
                ], 422);
            }

            return response()->json([
                'status' => true,
                'message' => $result['message'],
                'data' => new TransaksiResource($result['data']),
            ], 201);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    // GET /api/transaksis/{id}
    public function show(Request $request, $id)
    {
        try {
            $transaksi = $this->service->getById($id);

            if (! $transaksi) {
                return response()->json([
                    'status' => false,
                    'message' => 'Transaksi tidak ditemukan',
                ], 404);
            }

            if (! $request->user()->isAdmin() && $transaksi->user_id !== $request->user()->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Transaksi ini bukan milik akun Anda',
                ], 403);
            }

            return response()->json([
                'status' => true,
                'data' => new TransaksiResource($transaksi),
            ], 200);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }

    // PUT /api/transaksis/{id}/status
    public function updateStatus(UpdateTransaksiStatusRequest $request, $id)
    {
        try {
            $result = $this->service->updateStatus($id, $request->validated()['status']);

            if (! $result['success']) {
                return response()->json([
                    'status' => false,
                    'message' => $result['message'],
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => $result['message'],
                'data' => new TransaksiResource($result['data']),
            ], 200);
        } catch (\Throwable $exception) {
            return $this->serverError($exception);
        }
    }
}
