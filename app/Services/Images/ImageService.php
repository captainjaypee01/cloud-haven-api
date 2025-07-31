<?php

namespace App\Services\Images;

use App\Contracts\Services\ImageServiceInterface;
use App\Models\Image;
use Cloudinary\Api\Upload\UploadApi;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Pagination\LengthAwarePaginator;

class ImageService implements ImageServiceInterface
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = Image::query();
        if (!empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }
        return $query->orderByDesc('id')->paginate($filters['per_page'] ?? 50);
    }

    public function uploadImages(array $files, array $names): array
    {
        $uploaded = [];
        // Upload the image
        $uploadApi = new UploadApi();
        foreach ($files as $idx => $file) {
            $name = $names[$idx] ?? $file->getClientOriginalName();
            $result = $uploadApi->upload($file->getRealPath(), [
                'asset_folder' => 'netania',
                'public_id' => $name,
                'overwrite' => true,
            ]);
            $uploaded[] = Image::create([
                'name'             => $name,
                'alt_text'         => $name,
                'image_url'        => $result['url'],
                'secure_image_url' => $result['secure_url'],
                'image_path'       => null,
                'provider'         => 'cloudinary',
                'public_id'        => $result['public_id'],
                'width'            => $result['width'],
                'height'           => $result['height'],
                'order'            => 0,
            ]);
        }
        return $uploaded;
    }

    public function deleteImage(Image $image): void
    {
        if ($image->provider === 'cloudinary' && $image->public_id) {
            $uploadApi = new UploadApi();
            $uploadApi->destroy($image->public_id);
        }
        $image->delete();
    }
}
