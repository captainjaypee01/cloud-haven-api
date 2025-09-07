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

            $files = $request->file('files');
            $names = $request->input('names');
            $images = $this->imageService->uploadImages($files, $names);
            return new CollectionResponse(ImageResource::collection($images), 201);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return new ErrorResponse('Unable to upload images.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Image $image)
    {
        $this->imageService->deleteImage($image);
        return new EmptyResponse();
    }
}
