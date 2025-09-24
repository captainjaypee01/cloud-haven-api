<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Contracts\Services\ReviewServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Requests\Review\UpdateReviewRequest;
use App\Http\Resources\ReviewCollection;
use App\Http\Resources\ReviewResource;
use App\Http\Responses\CollectionResponse;
use App\Http\Responses\EmptyResponse;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewServiceInterface $reviewService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): CollectionResponse
    {
        $filters = $request->only(['type', 'rating', 'search', 'sort', 'per_page', 'page']);
        $paginator = $this->reviewService->list($filters);
        return new CollectionResponse(new ReviewCollection($paginator), JsonResponse::HTTP_OK);
    }

    /**
     * Display the specified resource.
     */
    public function show($review): ItemResponse|ErrorResponse
    {
        try {
            $data = $this->reviewService->show($review);
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('Review not found.');
        }
        return new ItemResponse(new ReviewResource($data));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreReviewRequest $request): ItemResponse|ErrorResponse
    {
        $validatedData = $request->validated();
        
        Log::info('Admin creating new review', [
            'admin_user_id' => $request->user()->id,
            'review_type' => $validatedData['type'] ?? null,
            'rating' => $validatedData['rating'] ?? null
        ]);
        
        try {
            $data = $this->reviewService->create($validatedData);
            
            Log::info('Review created successfully', [
                'admin_user_id' => $request->user()->id,
                'review_id' => $data->id,
                'review_type' => $data->type,
                'rating' => $data->rating
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to create review', [
                'admin_user_id' => $request->user()->id,
                'review_type' => $validatedData['type'] ?? null,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to create a review.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new ItemResponse(new ReviewResource($data), JsonResponse::HTTP_CREATED);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateReviewRequest $request, $review): ItemResponse|ErrorResponse
    {
        $validatedData = $request->validated();
        
        try {
            // Get original review data before update
            $originalReview = $this->reviewService->show($review);
            $originalValues = $originalReview->only(array_keys($validatedData));
            
            Log::info('Admin updating review', [
                'admin_user_id' => $request->user()->id,
                'review_id' => $review,
                'original_values' => $originalValues,
                'new_values' => $validatedData
            ]);
            
            $data = $this->reviewService->update($validatedData, $review);
            
            Log::info('Review updated successfully', [
                'admin_user_id' => $request->user()->id,
                'review_id' => $review,
                'review_type' => $data->type,
                'rating' => $data->rating
            ]);
            
        } catch (ModelNotFoundException $e) {
            Log::warning('Review not found for update', [
                'admin_user_id' => $request->user()->id,
                'review_id' => $review
            ]);
            return new ErrorResponse('Review not found.');
        } catch (Exception $e) {
            Log::error('Failed to update review', [
                'admin_user_id' => $request->user()->id,
                'review_id' => $review,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to update a review.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new ItemResponse(new ReviewResource($data), JsonResponse::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $review): EmptyResponse|ErrorResponse
    {
        Log::info('Admin deleting review', [
            'admin_user_id' => $request->user()->id,
            'review_id' => $review
        ]);
        
        try {
            $this->reviewService->delete($review);
            
            Log::info('Review deleted successfully', [
                'admin_user_id' => $request->user()->id,
                'deleted_review_id' => $review
            ]);
            
        } catch (ModelNotFoundException $e) {
            Log::warning('Review not found for deletion', [
                'admin_user_id' => $request->user()->id,
                'review_id' => $review
            ]);
            return new ErrorResponse('Review not found.');
        } catch (Exception $e) {
            Log::error('Failed to delete review', [
                'admin_user_id' => $request->user()->id,
                'review_id' => $review,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to delete a review.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new EmptyResponse();
    }
}
