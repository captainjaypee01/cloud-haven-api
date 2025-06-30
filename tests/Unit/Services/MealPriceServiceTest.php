<?php

use App\Models\MealPrice;
use App\Services\MealPrices\MealPriceService;

describe("Meal Price Service Test", function () {

    beforeEach(function () {
        $this->artisan('db:seed', ['--class' => 'MealPriceSeeder']);
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

    it('returns correct adult price', function () {
        expect($this->mealPriceService->getPriceForCategory('adult'))->toBe(1700.0);
    });

    it('returns correct children price', function () {
        expect($this->mealPriceService->getPriceForCategory('children'))->toBe(1000.0);
    });

    it('returns zero for unknown category', function () {
        expect($this->mealPriceService->getPriceForCategory('nonexistent'))->toBe(0.0);
    });

    it('returns zero for infant', function () {
        expect($this->mealPriceService->getPriceForCategory('infant'))->toBe(0.0);
    });
});
