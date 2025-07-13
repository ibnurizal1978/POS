<?php
class ImageCompressor {
    /**
     * Compress and resize an uploaded image
     * @param string $source_path Path to the uploaded image
     * @param string $destination_path Path to save the compressed image
     * @param int $max_width Maximum width of the image
     * @param int $quality Compression quality (0-100)
     * @return bool True if successful, false otherwise
     */
    public static function compress($source_path, $destination_path, $max_width = 800, $quality = 75) {
        // Check if GD library is available
        if (!function_exists('gd_info')) {
            error_log('GD library not available for image compression');
            return false;
        }

        // Determine image type
        $image_info = getimagesize($source_path);
        
        switch ($image_info[2]) {
            case IMAGETYPE_JPEG:
                $source_image = imagecreatefromjpeg($source_path);
                break;
            case IMAGETYPE_PNG:
                $source_image = imagecreatefrompng($source_path);
                break;
            case IMAGETYPE_WEBP:
                $source_image = imagecreatefromwebp($source_path);
                break;
            default:
                error_log('Unsupported image type for compression');
                return false;
        }

        // Get original image dimensions
        $width = imagesx($source_image);
        $height = imagesy($source_image);

        // Calculate new dimensions while maintaining aspect ratio
        if ($width > $max_width) {
            $ratio = $max_width / $width;
            $new_width = $max_width;
            $new_height = round($height * $ratio);
        } else {
            $new_width = $width;
            $new_height = $height;
        }

        // Create new image with resized dimensions
        $destination_image = imagecreatetruecolor($new_width, $new_height);

        // Handle transparency for PNG
        if ($image_info[2] == IMAGETYPE_PNG) {
            imagealphablending($destination_image, false);
            imagesavealpha($destination_image, true);
            $transparent = imagecolorallocatealpha($destination_image, 255, 255, 255, 127);
            imagefilledrectangle($destination_image, 0, 0, $new_width, $new_height, $transparent);
        }

        // Resize image
        imagecopyresampled(
            $destination_image, $source_image, 
            0, 0, 0, 0, 
            $new_width, $new_height, 
            $width, $height
        );

        // Save compressed image
        switch ($image_info[2]) {
            case IMAGETYPE_JPEG:
                $result = imagejpeg($destination_image, $destination_path, $quality);
                break;
            case IMAGETYPE_PNG:
                $result = imagepng($destination_image, $destination_path, round(9 * $quality / 100));
                break;
            case IMAGETYPE_WEBP:
                $result = imagewebp($destination_image, $destination_path, $quality);
                break;
        }

        // Free up memory
        imagedestroy($source_image);
        imagedestroy($destination_image);

        return $result;
    }
}