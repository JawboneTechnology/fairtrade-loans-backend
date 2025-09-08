<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\ImageUploadService;
use App\Http\Resources\ImageUploaderResource;
use App\Http\Requests\StoreImageUploaderRequest;

class ImageUploadController extends Controller
{
    protected $imageUploaderService;

    public function __construct(ImageUploadService $imageUploaderService)
    {
        $this->imageUploaderService = $imageUploaderService;
    }

    public function index(): JsonResponse
    {
        $images = $this->imageUploaderService->getSystemImages();

        return response()->json([
            'success' => true,
            'message' => 'Images retrieved successfully',
            'data' => ImageUploaderResource::collection($images),
        ], 200);
    }

    public function uploadImages(StoreImageUploaderRequest $request): JsonResponse
    {
        try {
            $images = $request->file('image');
            $height = $request->height;
            $width = $request->width;

            $imageUploader = new ImageUploadService($height, $width);
            $imagePath = $imageUploader->upload($images);

            $response = [
                'success' => true,
                'message' => 'Images uploaded successfully',
                'data' => is_array($imagePath) ? $imagePath : [$imagePath],
            ];

            return response()->json($response, 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while uploading images',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'image' => 'required|string',
            ]);

            $image = $request->image;
            $image = $this->imageUploaderService->deleteSystemImage($image);

            if (!$image) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image not found',
                    'data' => null,
                ], 404);
            }

            $response = [
                'success' => true,
                'message' => 'Image deleted successfully',
                'data' => null,
            ];

            return response()->json($response, 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
