<?php

namespace Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Products\Models\Product;
use Modules\Products\Models\ProductImage;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class ProductsController extends Controller
{
    /**
     * Display a listing of products with images
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'images']);

        // Optional search by name or description
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Pagination (default 10 per page)
        $perPage = $request->get('per_page', 10);
        $products = $query->paginate($perPage);

        return response()->json($products);
    }

    /**
     * Display only active products
     */
    public function active(Request $request)
    {
        $query = Product::with(['category', 'images'])->where('is_active', true);

        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($categoryId = $request->get('category_id')) {
            $query->where('category_id', $categoryId);
        }

        $perPage = $request->get('per_page', 10);
        $products = $query->paginate($perPage);

        return response()->json($products);
    }

    /**
     * Store a newly created product with images
     */
    public function store(Request $request)
    {
        Log::info('Product creation request', [
            'has_files' => $request->hasFile('images'),
            'all_files' => $request->allFiles(),
            'all_input' => $request->except(['images']),
        ]);

        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'images' => 'nullable|array|max:5',
            'images.*' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'main_image_index' => 'nullable|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Create product
            $product = Product::create([
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'stock_quantity' => $data['stock_quantity'] ?? 0,
                'is_active' => isset($data['is_active']) ? (bool)$data['is_active'] : true,
            ]);

            Log::info('Product created', ['product_id' => $product->id]);

            // Upload images if provided
            if ($request->hasFile('images')) {
                $images = $request->file('images');

                // normalize to array
                if (!is_array($images)) {
                    $images = [$images];
                }

                // filter valid uploads
                $images = array_filter($images, function ($img) {
                    return $img !== null && method_exists($img, 'isValid') ? $img->isValid() : false;
                });

                Log::info('Processing images', ['count' => count($images)]);

                if (!empty($images)) {
                    $mainImageIndex = isset($data['main_image_index']) ? (int)$data['main_image_index'] : 0;

                    foreach ($images as $index => $image) {
                        try {
                            // Use Cloudinary Upload API
                            $uploaded = Cloudinary::uploadApi()->upload(
                                $image->getRealPath(),
                                [
                                    'folder' => 'products',
                                    'transformation' => [
                                        'quality' => 'auto',
                                        'fetch_format' => 'auto',
                                    ],
                                ]
                            );

                            ProductImage::create([
                                'product_id' => $product->id,
                                'image_url' => $uploaded['secure_url'] ?? null,
                                'public_id' => $uploaded['public_id'] ?? null,
                                'is_main' => ($index === $mainImageIndex),
                            ]);

                            Log::info('Image uploaded', [
                                'product_id' => $product->id,
                                'index' => $index,
                                'is_main' => ($index === $mainImageIndex),
                                'url' => $uploaded['secure_url'] ?? null,
                                'public_id' => $uploaded['public_id'] ?? null,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Failed to upload image', [
                                'product_id' => $product->id,
                                'index' => $index,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                            // continue with other images
                        }
                    }
                }
            } else {
                Log::info('No images in request');
            }

            DB::commit();

            return response()->json([
                'message' => 'Product created successfully',
                'product' => $product->load(['category', 'images']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product creation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to create product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified product with images
     */
    public function show($id)
    {
        $product = Product::with(['category', 'images'])->findOrFail($id);
        return response()->json($product);
    }

    /**
     * Update the specified product
     */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        Log::info('Product update request', [
            'product_id' => $id,
            'has_files' => $request->hasFile('images'),
            'all_files' => $request->allFiles(),
            'all_input' => $request->except(['images', '_method']),
            'method' => $request->method(),
        ]);

        try {
            $data = $request->validate([
                '_method' => 'nullable|string', // accept method spoofing
                'category_id' => 'nullable|exists:categories,id',
                'name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'price' => 'nullable|numeric|min:0',
                'stock_quantity' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
                'images' => 'nullable|array|max:5',
                'images.*' => 'nullable|file|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
                'main_image_index' => 'nullable|integer|min:0',
                'remove_image_ids' => 'nullable|array',
                'remove_image_ids.*' => 'integer|exists:product_images,id',
                'existing_main_image_id' => 'nullable|integer|exists:product_images,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', [
                'errors' => $e->errors(),
                'input' => $request->except(['images', '_method']),
            ]);
            throw $e;
        }

        DB::beginTransaction();
        try {
            // Update product fields
            $updateData = [];
            if (isset($data['category_id'])) $updateData['category_id'] = $data['category_id'];
            if (isset($data['name'])) $updateData['name'] = $data['name'];
            if (array_key_exists('description', $data)) $updateData['description'] = $data['description'];
            if (isset($data['price'])) $updateData['price'] = $data['price'];
            if (isset($data['stock_quantity'])) $updateData['stock_quantity'] = $data['stock_quantity'];
            if (array_key_exists('is_active', $data)) $updateData['is_active'] = (bool)$data['is_active'];

            if (!empty($updateData)) {
                $product->update($updateData);
                Log::info('Product updated', ['product_id' => $id]);
            }

            // Remove specified images
            if (!empty($data['remove_image_ids']) && is_array($data['remove_image_ids'])) {
                $imagesToRemove = ProductImage::whereIn('id', $data['remove_image_ids'])
                    ->where('product_id', $product->id)
                    ->get();

                foreach ($imagesToRemove as $image) {
                    $this->deleteFromCloudinary($image->public_id ?? $image->image_url);
                    $image->delete();
                }

                Log::info('Images removed', ['product_id' => $id, 'count' => count($imagesToRemove)]);
            }

            // Handle existing images main flag
            if (isset($data['existing_main_image_id'])) {
                ProductImage::where('product_id', $product->id)->update(['is_main' => false]);
                ProductImage::where('id', $data['existing_main_image_id'])
                    ->where('product_id', $product->id)
                    ->update(['is_main' => true]);

                Log::info('Main image updated', [
                    'product_id' => $id,
                    'main_image_id' => $data['existing_main_image_id'],
                ]);
            }

            // Upload new images if provided
            if ($request->hasFile('images')) {
                $images = $request->file('images');

                if (!is_array($images)) $images = [$images];

                $images = array_filter($images, function ($img) {
                    return $img !== null && method_exists($img, 'isValid') ? $img->isValid() : false;
                });

                Log::info('New images to upload', ['count' => count($images)]);

                if (!empty($images)) {
                    $shouldSetNewImageAsMain = !isset($data['existing_main_image_id']);
                    $mainImageIndex = $data['main_image_index'] ?? null;

                    if ($shouldSetNewImageAsMain && $mainImageIndex !== null) {
                        ProductImage::where('product_id', $product->id)->update(['is_main' => false]);
                    }

                    $uploadedCount = 0;
                    foreach ($images as $index => $image) {
                        try {
                            $uploaded = Cloudinary::uploadApi()->upload(
                                $image->getRealPath(),
                                [
                                    'folder' => 'products',
                                    'transformation' => [
                                        'quality' => 'auto',
                                        'fetch_format' => 'auto',
                                    ],
                                ]
                            );

                            $isMain = $shouldSetNewImageAsMain &&
                                      $mainImageIndex !== null &&
                                      $uploadedCount === (int)$mainImageIndex;

                            ProductImage::create([
                                'product_id' => $product->id,
                                'image_url' => $uploaded['secure_url'] ?? null,
                                'public_id' => $uploaded['public_id'] ?? null,
                                'is_main' => $isMain,
                            ]);

                            Log::info('New image uploaded', [
                                'product_id' => $id,
                                'index' => $uploadedCount,
                                'is_main' => $isMain,
                                'url' => $uploaded['secure_url'] ?? null,
                                'public_id' => $uploaded['public_id'] ?? null,
                            ]);

                            $uploadedCount++;
                        } catch (\Exception $e) {
                            Log::error('Failed to upload image', [
                                'product_id' => $id,
                                'index' => $index,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    }
                }
            }

            // Ensure at least one image is main
            $hasMain = ProductImage::where('product_id', $product->id)->where('is_main', true)->exists();
            if (!$hasMain) {
                $first = ProductImage::where('product_id', $product->id)->first();
                if ($first) {
                    $first->update(['is_main' => true]);
                    Log::info('Set first image as main', ['product_id' => $id]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Product updated successfully',
                'product' => $product->load(['category', 'images']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product update failed: ' . $e->getMessage(), [
                'product_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Failed to update product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified product and its images
     */
    public function destroy($id)
    {
        $product = Product::with('images')->findOrFail($id);

        DB::beginTransaction();
        try {
            // Delete all images from Cloudinary
            foreach ($product->images as $image) {
                $this->deleteFromCloudinary($image->public_id ?? $image->image_url);
            }

            $product->delete();

            DB::commit();

            Log::info('Product deleted', ['product_id' => $id]);

            return response()->json(['message' => 'Product deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product deletion failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to delete product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete image from Cloudinary by public_id or extract from URL.
     */
    private function deleteFromCloudinary($publicIdOrUrl)
    {
        try {
            if (!$publicIdOrUrl) {
                return;
            }

            // If it's not a URL, we assume it's a public_id
            if (!filter_var($publicIdOrUrl, FILTER_VALIDATE_URL)) {
                Cloudinary::uploadApi()->destroy($publicIdOrUrl);
                Log::info('Cloudinary image deleted', ['public_id' => $publicIdOrUrl]);
                return;
            }

            // Extract public_id from Cloudinary URL
            // Example: /image/upload/v123456789/products/abc123.jpg
            $path = parse_url($publicIdOrUrl, PHP_URL_PATH);
            preg_match('/\/v\d+\/(.+?)(?:\.\w+)?$/', $path, $matches);

            if (isset($matches[1])) {
                $publicId = $matches[1];
                Cloudinary::uploadApi()->destroy($publicId);
                Log::info('Cloudinary image deleted', ['public_id' => $publicId]);
            } else {
                // fallback: try to remove as-is (may fail)
                Cloudinary::uploadApi()->destroy($publicIdOrUrl);
                Log::info('Cloudinary image deleted fallback', ['identifier' => $publicIdOrUrl]);
            }
        } catch (\Exception $e) {
            Log::error('Cloudinary deletion failed: ' . $e->getMessage(), [
                'identifier' => $publicIdOrUrl,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
