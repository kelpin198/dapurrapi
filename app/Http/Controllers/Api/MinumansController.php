<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMinumanRequest;
use App\Http\Requests\UpdateMinumanRequest;
use App\Models\Minuman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MinumansController extends Controller
{
    public function index(Request $request)
    {
        $query = Minuman::query()->orderBy('nama_minuman');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where('nama_minuman', 'like', "%{$search}%");
        }

        return response()->json([
            'status' => true,
            'data' => $query->paginate(10),
        ]);
    }

    public function show(Minuman $minuman)
    {
        return response()->json([
            'status' => true,
            'data' => $minuman,
        ]);
    }

    public function store(StoreMinumanRequest $request)
    {
        $minuman = Minuman::create($request->validated());
        Cache::flush();

        return response()->json([
            'status' => true,
            'message' => 'Minuman berhasil ditambahkan.',
            'data' => $minuman,
        ], 201);
    }

    public function update(UpdateMinumanRequest $request, Minuman $minuman)
    {
        $minuman->update($request->validated());
        Cache::flush();

        return response()->json([
            'status' => true,
            'message' => 'Minuman berhasil diperbarui.',
            'data' => $minuman->refresh(),
        ]);
    }

    public function destroy(Minuman $minuman)
    {
        $minuman->delete();
        Cache::flush();

        return response()->json([
            'status' => true,
            'message' => 'Minuman berhasil dihapus.',
        ]);
    }
}
