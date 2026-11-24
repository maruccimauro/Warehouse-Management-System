<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\CreateCategory;
use App\Modules\Catalog\Actions\UpdateCategory;
use App\Modules\Catalog\Http\Requests\CategoryListRequest;
use App\Modules\Catalog\Http\Requests\StoreCategoryRequest;
use App\Modules\Catalog\Http\Requests\UpdateCategoryRequest;
use App\Modules\Catalog\Http\Resources\CategoryResource;
use App\Modules\Catalog\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Jerarquía de categorías (REG-016): consulta con la sesión, alta y edición
 * con catalog.manage. Las categorías no se borran.
 */
final class CategoryController
{
    public function index(CategoryListRequest $request): AnonymousResourceCollection
    {
        return CategoryResource::collection($request->paginate(Category::query()));
    }

    public function store(StoreCategoryRequest $request, CreateCategory $createCategory): JsonResponse
    {
        $parentId = $request->integer('parent_id');

        $category = $createCategory(
            $request->string('name')->toString(),
            $parentId > 0 ? Category::query()->findOrFail($parentId) : null,
        );

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        Gate::authorize('view', $category);

        return new CategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request, UpdateCategory $updateCategory): CategoryResource
    {
        $parentId = $request->integer('parent_id');

        return new CategoryResource($updateCategory(
            $request->category(),
            $request->has('name') ? $request->string('name')->toString() : null,
            $request->has('parent_id'),
            $parentId > 0 ? Category::query()->findOrFail($parentId) : null,
        ));
    }
}
