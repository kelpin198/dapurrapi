<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateRestaurantProfileRequest;
use App\Models\RestaurantProfile;
use Illuminate\Http\JsonResponse;

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
            $data['logo_data'] = base64_encode($logo->get());
            $data['logo_mime_type'] = $logo->getMimeType();
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
            'logo_url' => $profile->logoDataUrl(),
        ];
    }
}
