<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Contracts\Services\ImageServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Image\StoreImagesRequest;
use App\Http\Resources\Image\ImageResource;
use App\Http\Responses\CollectionResponse;
use App\Http\Responses\EmptyResponse;
use App\Models\Image;
use Illuminate\Http\Request;

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
        $files = $request->file('files');
        $names = $request->input('names');
        $images = $this->imageService->uploadImages($files, $names);
        return new CollectionResponse(ImageResource::collection($images), 201);
    }

    public function destroy(Image $image)
    {
        $this->imageService->deleteImage($image);
        return new EmptyResponse();
    }
}
