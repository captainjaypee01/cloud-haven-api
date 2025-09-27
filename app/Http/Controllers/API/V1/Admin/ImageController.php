<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Contracts\Services\ImageServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Image\StoreImagesRequest;
use App\Http\Resources\Image\ImageResource;
use App\Http\Responses\CollectionResponse;
use App\Http\Responses\EmptyResponse;
use App\Http\Responses\ErrorResponse;
use App\Models\Image;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class ImageController extends Controller
{
    public function __construct(private readonly ImageServiceInterface $imageService) {}

    public function index(Request $request)
    {
        $images = $this->imageService->list($request->only(['search', 'per_page']));
        return new CollectionResponse(ImageResource::collection($images));
    }

    public function store(StoreImagesRequest $request)
    {
        try {
            Log::info('Starting image upload process', [
                'admin_user_id' => auth()->id(),
                'file_count' => count($request->file('files', [])),
                'file_sizes' => array_map(fn($file) => $file->getSize(), $request->file('files', [])),
                'file_types' => array_map(fn($file) => $file->getMimeType(), $request->file('files', []))
            ]);

            $files = $request->file('files');
            $names = $request->input('names');
            $images = $this->imageService->uploadImages($files, $names);
            
            Log::info('Image upload completed successfully', [
                'admin_user_id' => auth()->id(),
                'uploaded_count' => count($images),
                'image_ids' => collect($images)->pluck('id')->toArray()
            ]);
            
            return new CollectionResponse(ImageResource::collection($images), 201);
        } catch (Exception $e) {
            Log::error('Image upload failed', [
                'admin_user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'file_count' => count($request->file('files', [])),
                'file_sizes' => array_map(fn($file) => $file->getSize(), $request->file('files', []))
            ]);
            return new ErrorResponse('Unable to upload images: ' . $e->getMessage(), JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Image $image)
    {
        $this->imageService->deleteImage($image);
        return new EmptyResponse();
    }
}
