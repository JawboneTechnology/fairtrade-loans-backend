<?php

namespace App\Services;

use Illuminate\Support\Str;
use App\Models\SystemImage;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;

class ImageUploadService
{
    private $width;
    private $height;
    private $imageManager;

    public function __construct($width = 500, $height = 500, ImageManager $imageManager = null)
    {
        $this->width = $width;
        $this->height = $height;
        $this->imageManager = $imageManager ?? new ImageManager(new Driver());
    }

    public function upload($images, $directory = 'uploads/system_images'): array|string|null
    {
        if (is_array($images)) {
            return $this->uploadMultiple($images, $directory);
        } elseif ($images instanceof UploadedFile) {
            return $this->uploadSingle($images, $directory);
        }

        return null;
    }

    public function getSystemImages(): Collection
    {
        return SystemImage::all();
    }

    public function deleteSystemImage(string $image, string $directory = 'uploads/system_images'): bool
    {
        $image = SystemImage::where('image_path', $image)->first();

        if (!$image) {
            return false;
        }

        $imagePath = public_path($directory . '/thumbnails/' . basename($image->image_path));
        $originalImagePath = public_path($directory . '/' . basename($image->image_path));

        if (file_exists($imagePath)) {
            unlink($imagePath);
        }

        if (file_exists($originalImagePath)) {
            unlink($originalImagePath);
        }

        $image->delete();

        return true;
    }

    private function uploadSingle(UploadedFile $image, string $directory)
    {
        // Validate the uploaded file
        $validator = Validator::make(['image' => $image], [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($validator->fails()) {
            throw new \Exception("Invalid image file.");
        }

        // Generate a unique file name
        $imageName = time() . '-' . Str::slug($this->width . 'X' . $this->height . '-' . $image->getClientOriginalName(), '-') . '.' . $image->getClientOriginalExtension();
        $imagePath = $directory . '/' . $imageName;

        // Move the uploaded file to the target directory
        $image->move(public_path($directory), $imageName);

        // Resize the image and create a thumbnail
        $thumbnailPath = $this->resizeImage($imagePath, $directory);

        $fileSize = filesize($imagePath);

        // Save the image details to the database
        $fullPath = url($thumbnailPath);
        $image = SystemImage::create([
            'image_path' => $fullPath,
            'original_name' => $image->getClientOriginalName(),
            'file_size' => $fileSize,
            'file_extension' => $image->getClientOriginalExtension(),
            'thumbnail_width' => $this->width,
            'thumbnail_height' => $this->height,
        ]);

        return $image->toArray() ?? null;
    }

    private function uploadMultiple(array $images, string $directory): array
    {
        $uploadedImagePaths = [];

        foreach ($images as $image) {
            if ($image instanceof UploadedFile) {
                $uploadedImagePaths[] = $this->uploadSingle($image, $directory);
            }
        }

        return $uploadedImagePaths;
    }

    private function resizeImage(string $imagePath, string $directory): string
    {
        // Create the thumbnail directory if it doesn't exist
        $thumbnailDirectory = $directory . '/thumbnails';
        $uploadPath = public_path($thumbnailDirectory);

        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        // Resize the image
        $resizedImage = $this->imageManager->read(public_path($imagePath))
            ->resize($this->width, $this->height, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

        // Save the resized image
        $thumbnailPath = $thumbnailDirectory . '/' . basename($imagePath);
        $resizedImage->save(public_path($thumbnailPath));

        return $thumbnailPath;
    }
}
