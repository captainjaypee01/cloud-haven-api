<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Review\ReviewResource;
use App\Http\Responses\CollectionResponse;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function testimonials()  // for fetching reviews to display
    {
        // Example: fetch recent resort reviews for homepage
        $reviews = Review::with('user')
            ->where('type', 'resort')
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get();
        return new CollectionResponse(ReviewResource::collection($reviews));
    }
}
