<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ListUsersRequest;
use App\Http\Requests\User\SaveUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(ListUsersRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->filter($request->filters())
            ->orderBy(...$request->sort())
            ->paginate($request->perPage());

        return UserResource::collection($users);
    }

    public function store(SaveUserRequest $request): JsonResponse
    {
        $user = User::create($request->userData());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user);
    }

    public function update(SaveUserRequest $request, User $user): UserResource
    {
        $user->update($request->userData());

        return new UserResource($user);
    }

    public function destroy(User $user): Response
    {
        Gate::authorize('delete', $user);

        $user->delete();

        return response()->noContent();
    }
}
