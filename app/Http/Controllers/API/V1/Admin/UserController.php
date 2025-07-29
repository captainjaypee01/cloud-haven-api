<?php

namespace App\Http\Controllers\API\V1\Admin;

use App\Contracts\Services\UserServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\User\UserCollection;
use App\Http\Resources\User\UserResource;
use App\Http\Responses\CollectionResponse;
use App\Http\Responses\EmptyResponse;
use App\Http\Responses\ErrorResponse;
use App\Http\Responses\ItemResponse;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;

class UserController extends Controller
{
    public function __construct(
        private readonly UserServiceInterface $userService
    ) {}

    /**
     * Display a paginated listing of users.
     */
    public function index(Request $request): CollectionResponse
    {
        $filters = $request->only(['status', 'search', 'sort', 'per_page', 'page', 'role']);
        $paginator = $this->userService->list($filters);
        return new CollectionResponse(new UserCollection($paginator), JsonResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): ItemResponse|ErrorResponse
    {
        
        try {
            $data = $this->userService->createUser($request->validated());
        } catch (Exception $e) {
            Log::error($e->getMessage());
            if($e->getCode() === 422) {
                return new ErrorResponse($e->getMessage(), 422);
            }
            return new ErrorResponse('Unable to create a user.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new ItemResponse(new UserResource($data), JsonResponse::HTTP_CREATED);
    }

    /**
     * Show a single user by ID.
     */
    public function show($id): ItemResponse|ErrorResponse
    {
        try {
            $user = $this->userService->show((int)$id);
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('User not found.', JsonResponse::HTTP_NOT_FOUND);
        }
        return new ItemResponse(new UserResource($user));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, int $id)
    {
        try {
            $user = $this->userService->updateById($id, $request->validated());
        } catch (ModelNotFoundException $e) {
            return new ErrorResponse('User not found.', JsonResponse::HTTP_NOT_FOUND);
        }
        return new ItemResponse(new UserResource($user));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        try {
            $this->userService->deleteById($id);
        } catch (ModelNotFoundException $e) {
            Log::error($e->getMessage());
            return new ErrorResponse('User not found.');
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return new ErrorResponse('Unable to delete a user.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new EmptyResponse();
    }
}
