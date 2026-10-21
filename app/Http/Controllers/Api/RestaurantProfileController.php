<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRestaurantProfileRequest;
use App\Models\RestaurantProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class RestaurantProfileController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->profileData(RestaurantProfile::current()),
        ]);
    }

    public function update(UpdateRestaurantProfileRequest $request): JsonResponse
    {
        $profile = RestaurantProfile::current();
        $data = $request->safe()->only(['name', 'history']);

        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            $extension = match ($logo->getMimeType()) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => null,
            };

            abort_unless($extension !== null, 422, 'Format logo tidak didukung.');

            $logoPath = 'restaurant-profile/logo.'.$extension;
            abort_unless(
                Storage::disk('local')->put($logoPath, $logo->get()),
                500,
                'Logo restoran gagal disimpan.'
            );

            foreach (['jpg', 'jpeg', 'png', 'webp'] as $existingExtension) {
                $existingPath = 'restaurant-profile/logo.'.$existingExtension;

                if ($existingPath !== $logoPath) {
                    Storage::disk('local')->delete($existingPath);
                }
            }

            $data['logo_data'] = null;
            $data['logo_mime_type'] = null;
        }

        $profile->fill($data)->save();

        return response()->json([
            'status' => true,
            'message' => 'Profil restoran berhasil diperbarui.',
            'data' => $this->profileData($profile->refresh()),
        ]);
    }

    /**
     * @return array{id: int, name: string, history: string|null, logo_url: string|null}
     */
    private function profileData(RestaurantProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'name' => $profile->name,
            'history' => $profile->history,
            'logo_url' => $profile->logoUrl(),
        ];
    }
}
