<?php

namespace App\Http\Controllers\API\V1\Dashboard;

use App\Contracts\Services\MealPriceServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\MealPrice\PublicMealPriceCollection;
use App\Http\Responses\CollectionResponse;
use Illuminate\Http\Request;

class MealPriceController extends Controller
{
    public function __construct(private readonly MealPriceServiceInterface $mealPriceService)
    {
        
    }
    public function getMealPrices()
    {
        return new CollectionResponse(new PublicMealPriceCollection($this->mealPriceService->getMealPrices()));
    }
}
