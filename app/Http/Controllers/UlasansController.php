<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUlasanRequest;
use App\Http\Requests\UpdateUlasanRequest;
use App\Http\Resources\UlasanResource;
use App\Models\Makanans;
use App\Models\Minuman;
use App\Models\Ulasans;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UlasansController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'makanan_id' => ['sometimes', 'integer', 'exists:makanans,id'],
            'minuman_id' => ['sometimes', 'integer', 'exists:minumans,id'],
        ]);

        $reviews = Ulasans::query()
            ->with(['user', 'makanan', 'minuman'])
            ->when(isset($validated['makanan_id']), fn ($query) => $query->where('makanan_id', $validated['makanan_id']))
            ->when(isset($validated['minuman_id']), fn ($query) => $query->where('minuman_id', $validated['minuman_id']))
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'data' => UlasanResource::collection($reviews)->resolve($request),
        ]);
    }

    public function store(StoreUlasanRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $makanan = isset($validated['makanan_id'])
            ? Makanans::query()->findOrFail($validated['makanan_id'])
            : null;
        $minuman = isset($validated['minuman_id'])
            ? Minuman::query()->findOrFail($validated['minuman_id'])
            : null;
        $ulasan = Ulasans::query()->create([
            'user_id' => $user->getKey(),
            'makanan_id' => $makanan?->getKey(),
            'minuman_id' => $minuman?->getKey(),
            'nama_menu' => $makanan?->nama_makanan ?? $minuman?->nama_minuman,
            'rating' => $validated['rating'],
            'komentar' => $validated['komentar'] ?? null,
        ]);
        $ulasan->load(['user', 'makanan', 'minuman']);

        return response()->json([
            'status' => true,
            'message' => 'Ulasan berhasil ditambahkan.',
            'data' => (new UlasanResource($ulasan->load(['user', 'makanan', 'minuman'])))->resolve($request),
        ], 201);
    }

    public function show(Request $request, Ulasans $ulasan): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => (new UlasanResource($ulasan->load(['user', 'makanan', 'minuman'])))->resolve($request),
        ]);
    }

    public function reply(Request $request, Ulasans $ulasan): JsonResponse
    {
        $validated = $request->validate([
            'balasan_admin' => ['required', 'string', 'max:2000'],
        ]);

        $ulasan->update(['balasan_admin' => $validated['balasan_admin']]);

        return response()->json([
            'status' => true,
            'message' => 'Balasan ulasan berhasil disimpan.',
            'data' => (new UlasanResource($ulasan->load(['user', 'makanan', 'minuman'])))->resolve($request),
        ]);
    }

    public function update(UpdateUlasanRequest $request, Ulasans $ulasan): JsonResponse
    {
        $this->authorizeReviewOwner($request, $ulasan);

        $ulasan->update($request->validated());
        $ulasan->load(['user', 'makanan']);

        return response()->json([
            'status' => true,
            'message' => 'Ulasan berhasil diperbarui.',
            'data' => (new UlasanResource($ulasan->load(['user', 'makanan', 'minuman'])))->resolve($request),
        ]);
    }

    public function destroy(Request $request, Ulasans $ulasan): JsonResponse
    {
        $this->authorizeReviewOwner($request, $ulasan);
        $ulasan->delete();

        return response()->json([
            'status' => true,
            'message' => 'Ulasan berhasil dihapus.',
        ]);
    }

    private function authorizeReviewOwner(Request $request, Ulasans $ulasan): void
    {
        abort_unless($ulasan->user_id === $request->user()->getKey(), 403);
    }
}
