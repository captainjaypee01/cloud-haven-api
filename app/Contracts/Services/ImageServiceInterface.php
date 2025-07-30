<?php

namespace App\Contracts\Services;

use App\Models\Image;
use Illuminate\Http\UploadedFile;

interface ImageServiceInterface
{
    /**
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function list(array $filters): \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    /**
     * @param UploadedFile[] $files
     * @param array $names
     * @return Image[]
     */
    public function uploadImages(array $files, array $names): array;
    public function deleteImage(Image $image): void;
}
