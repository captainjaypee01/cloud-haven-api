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
use Illuminate\Support\Facades\Log;
use Exception;
use App\Utils\ChangeLogger;

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
        $validatedData = $request->validated();
        
        Log::info('Admin creating new meal program', [
            'admin_user_id' => auth()->id(),
            'program_name' => $validatedData['name'],
            'status' => $validatedData['status'],
            'scope_type' => $validatedData['scope_type'],
            'pm_snack_policy' => $validatedData['pm_snack_policy']
        ]);
        
        try {
            $dto = new MealProgramDTO(
                id: null,
                name: $validatedData['name'],
                status: $validatedData['status'],
                scopeType: $validatedData['scope_type'],
                dateStart: isset($validatedData['date_start']) ? Carbon::parse($validatedData['date_start']) : null,
                dateEnd: isset($validatedData['date_end']) ? Carbon::parse($validatedData['date_end']) : null,
                months: $validatedData['months'] ?? null,
                weekdays: $validatedData['weekdays'] ?? null,
                weekendDefinition: $validatedData['weekend_definition'] ?? 'SAT_SUN',
                pmSnackPolicy: $validatedData['pm_snack_policy'],
                inactiveLabel: $validatedData['inactive_label'] ?? 'Free Breakfast',
                buffetEnabled: $validatedData['buffet_enabled'] ?? true,
                notes: $validatedData['notes'] ?? null
            );

            $program = $this->upsertAction->execute($dto);
            $program->load(['pricingTiers', 'calendarOverrides']);

            Log::info('Meal program created successfully', [
                'admin_user_id' => auth()->id(),
                'program_id' => $program->id,
                'program_name' => $program->name,
                'status' => $program->status,
                'scope_type' => $program->scope_type
            ]);

            return new ItemResponse(new MealProgramResource($program), JsonResponse::HTTP_CREATED);
        } catch (Exception $e) {
            Log::error('Failed to create meal program', [
                'admin_user_id' => auth()->id(),
                'program_name' => $validatedData['name'] ?? null,
                'error' => $e->getMessage()
            ]);
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
        $validatedData = $request->validated();
        
        try {
            $program = $this->programRepository->find($id);

            if (!$program) {
                Log::warning('Meal program not found for update', [
                    'admin_user_id' => auth()->id(),
                    'program_id' => $id
                ]);
                return new ErrorResponse('Meal program not found.', JsonResponse::HTTP_NOT_FOUND);
            }

            // Capture original values for comparison
            $originalValues = $program->only(array_keys($validatedData));
            
            ChangeLogger::logUpdateAttempt(
                'Admin updating meal program',
                $originalValues,
                $validatedData,
                [
                    'admin_user_id' => auth()->id(),
                    'program_id' => $id,
                    'program_name' => $program->name
                ]
            );

            $dto = new MealProgramDTO(
                id: $id,
                name: $validatedData['name'],
                status: $validatedData['status'],
                scopeType: $validatedData['scope_type'],
                dateStart: isset($validatedData['date_start']) ? Carbon::parse($validatedData['date_start']) : null,
                dateEnd: isset($validatedData['date_end']) ? Carbon::parse($validatedData['date_end']) : null,
                months: $validatedData['months'] ?? null,
                weekdays: $validatedData['weekdays'] ?? null,
                weekendDefinition: $validatedData['weekend_definition'] ?? 'SAT_SUN',
                pmSnackPolicy: $validatedData['pm_snack_policy'],
                inactiveLabel: $validatedData['inactive_label'] ?? 'Free Breakfast',
                buffetEnabled: $validatedData['buffet_enabled'] ?? true,
                notes: $validatedData['notes'] ?? null
            );

            $program = $this->upsertAction->execute($dto);
            $program->load(['pricingTiers', 'calendarOverrides']);

            ChangeLogger::logSuccessfulUpdate(
                'Meal program updated successfully',
                $originalValues,
                $validatedData,
                [
                    'admin_user_id' => auth()->id(),
                    'program_id' => $id,
                    'program_name' => $program->name
                ]
            );

            return new ItemResponse(new MealProgramResource($program));
        } catch (Exception $e) {
            Log::error('Failed to update meal program', [
                'admin_user_id' => auth()->id(),
                'program_id' => $id,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to update meal program.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified meal program.
     */
    public function destroy(int $id): EmptyResponse|ErrorResponse
    {
        Log::info('Admin deleting meal program', [
            'admin_user_id' => auth()->id(),
            'program_id' => $id
        ]);
        
        try {
            $program = $this->programRepository->find($id);

            if (!$program) {
                Log::warning('Meal program not found for deletion', [
                    'admin_user_id' => auth()->id(),
                    'program_id' => $id
                ]);
                return new ErrorResponse('Meal program not found.', JsonResponse::HTTP_NOT_FOUND);
            }

            $this->programRepository->delete($program);

            Log::info('Meal program deleted successfully', [
                'admin_user_id' => auth()->id(),
                'deleted_program_id' => $id,
                'program_name' => $program->name
            ]);

            return new EmptyResponse();
        } catch (Exception $e) {
            Log::error('Failed to delete meal program', [
                'admin_user_id' => auth()->id(),
                'program_id' => $id,
                'error' => $e->getMessage()
            ]);
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

            $program = $this->programRepository->find($id, ['calendarOverrides']);

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
