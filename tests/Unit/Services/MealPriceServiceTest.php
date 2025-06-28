<?php

use App\Models\MealPrice;
use App\Services\MealPrices\MealPriceService;

describe("Meal Price Service Test", function () {

    beforeEach(function () {
        MealPrice::factory()->create(['category' => 'adult', 'price' => 1700]);
        MealPrice::factory()->create(['category' => 'children', 'price' => 1000]);
        MealPrice::factory()->create(['category' => 'infant', 'price' => 0]);
        $this->mealPriceService = new MealPriceService();
    });

    it('get meal prices', function () {


        $mealPrices = $this->mealPriceService->getMealPrices();

        expect($mealPrices['adult']->price)->toBe(1700.0);
        expect($mealPrices['children']->price)->toBe(1000.0);
        expect($mealPrices['infant']->price)->toBe(0.0);
    });

    it('returns price for given category', function () {

        expect($this->mealPriceService->getPriceForCategory('adult'))->toBe(1700.0);
        expect($this->mealPriceService->getPriceForCategory('children'))->toBe(1000.0); // Should be 0 if not set
    });
});
