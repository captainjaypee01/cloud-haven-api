<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Actions\UpsertMealProgramAction;
use App\Contracts\Repositories\MealProgramRepositoryInterface;
use App\Contracts\Services\MealCalendarServiceInterface;
use App\DTO\MealProgramDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MealProgramRequest;
use App\Http\Resources\Admin\MealProgramResource;
use App\Http\Resources\Admin\MealProgramCollection;
use App\Http\Responses\CollectionResponse;
use App\Http\Responses\ItemResponse;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\EmptyResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class MealProgramController extends Controller
{
    public function __construct(
        private MealProgramRepositoryInterface $programRepository,
        private MealCalendarServiceInterface $calendarService,
        private UpsertMealProgramAction $upsertAction
    ) {}

    /**
     * Display a listing of meal programs.
     */
    public function index(Request $request): CollectionResponse
    {
        $filters = $request->only(['status', 'search', 'sort', 'per_page', 'page']);
        $paginator = $this->programRepository->paginate($filters, ['pricingTiers', 'calendarOverrides']);

        return new CollectionResponse(new MealProgramCollection($paginator));
    }

    /**
     * Store a newly created meal program.
     */
    public function store(MealProgramRequest $request): ItemResponse|ErrorResponse
    {
        try {
            $dto = new MealProgramDTO(
                id: null,
                name: $request->validated()['name'],
                status: $request->validated()['status'],
                scopeType: $request->validated()['scope_type'],
                dateStart: isset($request->validated()['date_start']) ? Carbon::parse($request->validated()['date_start']) : null,
                dateEnd: isset($request->validated()['date_end']) ? Carbon::parse($request->validated()['date_end']) : null,
                months: $request->validated()['months'] ?? null,
                weekdays: $request->validated()['weekdays'] ?? null,
                weekendDefinition: $request->validated()['weekend_definition'] ?? 'SAT_SUN',
                inactiveLabel: $request->validated()['inactive_label'] ?? 'Free Breakfast',
                notes: $request->validated()['notes'] ?? null
            );

            $program = $this->upsertAction->execute($dto);
            $program->load(['pricingTiers', 'calendarOverrides']);

            return new ItemResponse(new MealProgramResource($program), JsonResponse::HTTP_CREATED);
        } catch (Exception $e) {
            Log::error('Failed to create meal program: ' . $e->getMessage());
            return new ErrorResponse('Unable to create meal program.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified meal program.
     */
    public function show(int $id): ItemResponse|ErrorResponse
    {
        try {
            $program = $this->programRepository->find($id, ['pricingTiers', 'calendarOverrides']);

            if (!$program) {
                return new ErrorResponse('Meal program not found.', JsonResponse::HTTP_NOT_FOUND);
            }

            return new ItemResponse(new MealProgramResource($program));
        } catch (Exception $e) {
            Log::error('Failed to retrieve meal program: ' . $e->getMessage());
            return new ErrorResponse('Unable to retrieve meal program.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified meal program.
     */
    public function update(MealProgramRequest $request, int $id): ItemResponse|ErrorResponse
    {
        try {
            $program = $this->programRepository->find($id);

            if (!$program) {
                return new ErrorResponse('Meal program not found.', JsonResponse::HTTP_NOT_FOUND);
            }

            $dto = new MealProgramDTO(
                id: $id,
                name: $request->validated()['name'],
                status: $request->validated()['status'],
                scopeType: $request->validated()['scope_type'],
                dateStart: isset($request->validated()['date_start']) ? Carbon::parse($request->validated()['date_start']) : null,
                dateEnd: isset($request->validated()['date_end']) ? Carbon::parse($request->validated()['date_end']) : null,
                months: $request->validated()['months'] ?? null,
                weekdays: $request->validated()['weekdays'] ?? null,
                weekendDefinition: $request->validated()['weekend_definition'] ?? 'SAT_SUN',
                inactiveLabel: $request->validated()['inactive_label'] ?? 'Free Breakfast',
                notes: $request->validated()['notes'] ?? null
            );

            $program = $this->upsertAction->execute($dto);
            $program->load(['pricingTiers', 'calendarOverrides']);

            return new ItemResponse(new MealProgramResource($program));
        } catch (Exception $e) {
            Log::error('Failed to update meal program: ' . $e->getMessage());
            return new ErrorResponse('Unable to update meal program.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified meal program.
     */
    public function destroy(int $id): EmptyResponse|ErrorResponse
    {
        try {
            $program = $this->programRepository->find($id);

            if (!$program) {
                return new ErrorResponse('Meal program not found.', JsonResponse::HTTP_NOT_FOUND);
            }

            $this->programRepository->delete($program);

            return new EmptyResponse();
        } catch (Exception $e) {
            Log::error('Failed to delete meal program: ' . $e->getMessage());
            return new ErrorResponse('Unable to delete meal program.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Preview calendar for a meal program.
     */
    public function preview(Request $request, int $id): ItemResponse|ErrorResponse
    {
        try {
            $request->validate([
                'from' => 'required|date',
                'to' => 'required|date|after:from',
            ]);

            $program = $this->programRepository->find($id);

            if (!$program) {
                return new ErrorResponse('Meal program not found.', JsonResponse::HTTP_NOT_FOUND);
            }

            $startDate = Carbon::parse($request->from);
            $endDate = Carbon::parse($request->to);

            $calendar = $this->calendarService->previewProgramCalendar($id, $startDate, $endDate);

            return new ItemResponse(new \Illuminate\Http\Resources\Json\JsonResource($calendar));
        } catch (Exception $e) {
            Log::error('Failed to preview meal program calendar: ' . $e->getMessage());
            return new ErrorResponse('Unable to preview meal program calendar.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
