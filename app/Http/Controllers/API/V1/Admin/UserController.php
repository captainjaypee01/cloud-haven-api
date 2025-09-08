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
use App\Utils\ChangeLogger;

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
        $validatedData = $request->validated();
        
        Log::info('Admin creating new user', [
            'admin_user_id' => auth()->id(),
            'user_email' => $validatedData['email'] ?? null,
            'user_role' => $validatedData['role'] ?? null
        ]);
        
        try {
            $data = $this->userService->createUser($validatedData);
            
            Log::info('User created successfully', [
                'admin_user_id' => auth()->id(),
                'created_user_id' => $data->id,
                'user_email' => $data->email,
                'user_role' => $data->role
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to create user', [
                'admin_user_id' => auth()->id(),
                'user_email' => $validatedData['email'] ?? null,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode()
            ]);
            
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
        $validatedData = $request->validated();
        
        try {
            // Get original user data before update
            $originalUser = $this->userService->show($id);
            $originalValues = $originalUser->only(array_keys($validatedData));
            
            ChangeLogger::logUpdateAttempt(
                'Admin updating user',
                $originalValues,
                $validatedData,
                [
                    'admin_user_id' => auth()->id(),
                    'target_user_id' => $id,
                    'user_email' => $originalUser->email
                ]
            );
            
            $user = $this->userService->updateById($id, $validatedData);
            
            ChangeLogger::logSuccessfulUpdate(
                'User updated successfully',
                $originalValues,
                $validatedData,
                [
                    'admin_user_id' => auth()->id(),
                    'target_user_id' => $id,
                    'user_email' => $user->email
                ]
            );
            
        } catch (ModelNotFoundException $e) {
            Log::warning('User not found for update', [
                'admin_user_id' => auth()->id(),
                'target_user_id' => $id
            ]);
            return new ErrorResponse('User not found.', JsonResponse::HTTP_NOT_FOUND);
        }
        return new ItemResponse(new UserResource($user));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        Log::info('Admin deleting user', [
            'admin_user_id' => auth()->id(),
            'target_user_id' => $id
        ]);
        
        try {
            $this->userService->deleteById($id);
            
            Log::info('User deleted successfully', [
                'admin_user_id' => auth()->id(),
                'deleted_user_id' => $id
            ]);
            
        } catch (ModelNotFoundException $e) {
            Log::warning('User not found for deletion', [
                'admin_user_id' => auth()->id(),
                'target_user_id' => $id
            ]);
            return new ErrorResponse('User not found.');
        } catch (Exception $e) {
            Log::error('Failed to delete user', [
                'admin_user_id' => auth()->id(),
                'target_user_id' => $id,
                'error' => $e->getMessage()
            ]);
            return new ErrorResponse('Unable to delete a user.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
        return new EmptyResponse();
    }

}
