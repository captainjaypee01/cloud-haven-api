<?php
namespace App\Services\MealPrices;

use App\Contracts\MealPrices\CreateMealPriceContract;
use App\Contracts\MealPrices\DeleteMealPriceContract;
use App\Contracts\MealPrices\UpdateMealPriceContract;
use App\Contracts\Repositories\MealPriceRepositoryInterface;
use App\Contracts\Services\MealPriceServiceInterface;
use App\DTO\MealPrices\MealPriceDtoFactory;
use App\Models\MealPrice;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class MealPriceService implements MealPriceServiceInterface
{
    
    public function __construct(
        private MealPriceRepositoryInterface $mealPriceRepository,
        private MealPriceDtoFactory          $dtoFactory,
        private CreateMealPriceContract      $creator,
        private UpdateMealPriceContract      $updater,
        private DeleteMealPriceContract      $deleter,
    ) {}

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->mealPriceRepository->get(
            filters: $filters,
            sort: $filters['sort'] ?? null,
            perPage: $filters['per_page'] ?? 10
        );
    }
    public function show(int $id): MealPrice
    {
        return $this->mealPriceRepository->getId($id);
    }

    public function create(array $data): MealPrice
    {
        $dto = $this->dtoFactory->newMealPrice($data);
        return $this->creator->handle($dto);
    }

    public function update(int $id, array $data): MealPrice
    {
        if (empty($data)) {
            throw new InvalidArgumentException('No update data provided');
        }
        $promo = $this->mealPriceRepository->getId($id);
        $dto   = $this->dtoFactory->updateMealPrice($data);
        return $this->updater->handle($promo, $dto);
    }

    public function delete(int $id): void
    {
        $promo = $this->mealPriceRepository->getId($id);
        $this->deleter->handle($promo);
    }

    public function getMealPrices()
    {
        return MealPrice::select("category", "price")->get()->keyBy('category');
    }

    public function getPriceForCategory(string $category): float
    {
        $mealPrices = $this->getMealPrices();
        return $mealPrices[$category]->price ?? 0;
    }
}
