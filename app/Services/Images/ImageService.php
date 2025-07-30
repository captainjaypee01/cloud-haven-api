<?php

namespace App\Services\Images;

use App\Contracts\Services\ImageServiceInterface;
use App\Models\Image;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\UploadedFile;
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
        foreach ($files as $idx => $file) {
            $name = $names[$idx] ?? $file->getClientOriginalName();
            $result = $file->storeOnCloudinary();
            $uploaded[] = Image::create([
                'name'             => $name,
                'alt_text'         => $name,
                'image_url'        => $result->getPath(),
                'secure_image_url' => $result->getSecurePath(),
                'image_path'       => null,
                'provider'         => 'cloudinary',
                'public_id'        => $result->getPublicId(),
                'width'            => $result->getWidth(),
                'height'           => $result->getHeight(),
                'order'            => 0,
            ]);
        }
        return $uploaded;
    }

    public function deleteImage(Image $image): void
    {
        if ($image->provider === 'cloudinary' && $image->public_id) {
            Cloudinary::destroy($image->public_id);
        }
        $image->delete();
    }
}
