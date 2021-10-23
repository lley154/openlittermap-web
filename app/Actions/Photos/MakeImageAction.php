<?php

namespace App\Actions\Photos;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;

class MakeImageAction
{
    private const TEMP_HEIC_STORAGE_DIR = 'app/heic_images/';

    /**
     * Create an instance of Intervention Image using an UploadedFile
     *
     * @param UploadedFile $file
     * @param bool $resize
     *
     * @return array<\Intervention\Image\Image, array>
     */
    public function run (UploadedFile $file, bool $resize = false): array
    {
        $imageAndExifData = $this->getImageAndExifData($file);

        if ($resize)
        {
            $imageAndExifData['image']->resize(500, 500);

            $imageAndExifData['image']->resize(500, 500, function ($constraint) {
                $constraint->aspectRatio();
            });
        }

        return $imageAndExifData;
    }

    /**
     * @param UploadedFile $file
     * @return array<\Intervention\Image\Image, array>
     */
    protected function getImageAndExifData(UploadedFile $file): array
    {
        $extension = $file->getClientOriginalExtension();

        if (!in_array(strtolower($extension), ['heif', 'heic'])) {
            $image = Image::make($file);
            $exif = $image->exif();

            return compact('image', 'exif');
        }

        // Generating a random filename, and not using the image's
        // original filename to handle cases
        // that contain spaces or other weird characters
        $randomFilename = bin2hex(random_bytes(8));

        // Path for a temporary file from the upload -> storage/app/heic_images/sample1.heic
        $tmpFilepath = storage_path(
            self::TEMP_HEIC_STORAGE_DIR .
            $randomFilename . ".$extension"
        );

        // Path for a converted temporary file -> storage/app/heic_images/sample1.jpg
        $convertedFilepath = storage_path(
            self::TEMP_HEIC_STORAGE_DIR .
            $randomFilename . '.png'
        );

        // Store the uploaded HEIC file on the server
        File::put($tmpFilepath, $file->getContent());

        // Run a shell command to execute ImageMagick conversion
        exec('convert ' . $tmpFilepath . ' ' . $convertedFilepath);

        // Run another shell command to copy the exif data
        exec('exiftool -overwrite_original_in_place -tagsFromFile ' . $tmpFilepath . ' ' . $convertedFilepath);
        \Log::info('exiftool -overwrite_original_in_place -tagsFromFile ' . $tmpFilepath . ' ' . $convertedFilepath);

        // Make the image from the new converted file
        $image = Image::make($convertedFilepath);

        $exif = $image->exif();
        \Log::info(['exif', $exif]);

        // Remove the temporary files from storage
//        unlink($tmpFilepath);
//        unlink($convertedFilepath);

        return compact('image', 'exif');
    }
}
