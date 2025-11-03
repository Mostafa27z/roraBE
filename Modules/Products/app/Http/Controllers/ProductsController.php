<?php

namespace Modules\Products\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Products\Models\Product;
use Modules\Products\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class ProductsController extends Controller
{
    /**
     * Display a listing of products with images
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'images']);

        // 🔍 Optional search by name or description
        if ($search = $request->get('search')) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // 📄 Pagination (default 10 per page)
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
        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'images' => 'nullable|array|max:5', // Max 5 images
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120', // Max 5MB per image
            'main_image_index' => 'nullable|integer|min:0', // Index of main image
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
                'is_active' => $data['is_active'] ?? true,
            ]);

            // Handle image uploads
            if ($request->hasFile('images')) {
                $mainImageIndex = $data['main_image_index'] ?? 0;
                
                foreach ($request->file('images') as $index => $image) {
                    // Upload to Cloudinary
                    $uploadedFile = $image->storeOnCloudinary('products');
                    
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_url' => $uploadedFile->getSecurePath(),
                        'is_main' => ($index === $mainImageIndex),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Product created successfully',
                'product' => $product->load(['category', 'images'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product creation failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to create product',
                'error' => $e->getMessage()
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

        $data = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'images' => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'main_image_index' => 'nullable|integer|min:0',
            'remove_image_ids' => 'nullable|array', // IDs of images to remove
            'remove_image_ids.*' => 'integer|exists:product_images,id',
        ]);

        DB::beginTransaction();
        try {
            // Update product details
            $updateData = array_filter([
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'price' => $data['price'] ?? null,
                'stock_quantity' => $data['stock_quantity'] ?? null,
                'is_active' => $data['is_active'] ?? null,
            ], function($value) {
                return $value !== null;
            });

            if (!empty($updateData)) {
                $product->update($updateData);
            }

            // Remove specified images
            if (!empty($data['remove_image_ids'])) {
                $imagesToRemove = ProductImage::whereIn('id', $data['remove_image_ids'])
                    ->where('product_id', $product->id)
                    ->get();

                foreach ($imagesToRemove as $image) {
                    $this->deleteFromCloudinary($image->image_url);
                    $image->delete();
                }
            }

            // Add new images
            if ($request->hasFile('images')) {
                $mainImageIndex = $data['main_image_index'] ?? null;
                
                // Reset main image flag if setting a new main image
                if ($mainImageIndex !== null) {
                    ProductImage::where('product_id', $product->id)->update(['is_main' => false]);
                }
                
                foreach ($request->file('images') as $index => $image) {
                    // Upload to Cloudinary with transformations
                    $uploadedFile = $image->storeOnCloudinary('products');
                    
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_url' => $uploadedFile->getSecurePath(),
                        'is_main' => ($mainImageIndex !== null && $index === $mainImageIndex),
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Product updated successfully',
                'product' => $product->load(['category', 'images'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product update failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to update product',
                'error' => $e->getMessage()
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
                $this->deleteFromCloudinary($image->image_url);
            }

            // Delete product (images will cascade delete if foreign key is set)
            $product->delete();

            DB::commit();

            return response()->json(['message' => 'Product deleted successfully']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product deletion failed: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to delete product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete image from Cloudinary
     */
    private function deleteFromCloudinary($imageUrl)
    {
        try {
            // Extract public_id from Cloudinary URL
            // URL format: https://res.cloudinary.com/{cloud_name}/image/upload/{version}/{public_id}.{format}
            preg_match('/\/v\d+\/(.+)\.\w+$/', $imageUrl, $matches);
            
            if (isset($matches[1])) {
                $publicId = $matches[1];
                Cloudinary::destroy($publicId);
            }
        } catch (\Exception $e) {
            Log::error('Cloudinary deletion failed: ' . $e->getMessage());
            // Don't throw - deletion failure shouldn't stop the process
        }
    }
}