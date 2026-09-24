<?php

namespace App\Actions;

use App\Models\File;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UpdateUserAvatarAction
{
    public function execute(User $user, UploadedFile $uploadedFile): File
    {
        $this->stripMetadata($uploadedFile);
        [$width, $height] = $this->dimensions($uploadedFile);

        return DB::transaction(function () use ($user, $uploadedFile, $width, $height) {
            $path = $uploadedFile->store('avatars', 'public');

            if ($path === false) {
                throw new RuntimeException('Falha ao salvar a imagem de avatar.');
            }

            $oldAvatar = $user->avatar;

            $file = $user->files()->create([
                'collection' => 'avatar',
                'disk' => 'public',
                'path' => $path,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'mime_type' => $uploadedFile->getMimeType(),
                'size' => $uploadedFile->getSize(),
                'width' => $width,
                'height' => $height,
            ]);

            $oldAvatar?->delete();

            return $file;
        });
    }

    private function dimensions(UploadedFile $uploadedFile): array
    {
        $imageSize = @getimagesize($uploadedFile->getRealPath());

        return $imageSize === false
            ? [null, null]
            : [$imageSize[0], $imageSize[1]];
    }

    /**
     * Regrava a imagem sem metadados: fotos de celular trazem EXIF (inclusive GPS) e o avatar é servido por URL pública.
     * A rotação do EXIF é aplicada antes de descartá-lo. Sem a extensão GD a imagem segue como veio.
     */
    private function stripMetadata(UploadedFile $uploadedFile): void
    {
        if (! function_exists('imagecreatefromstring')) {
            return;
        }

        $path = $uploadedFile->getRealPath();
        $image = @imagecreatefromstring((string) file_get_contents($path));

        if ($image === false) {
            return;
        }

        $mime = $uploadedFile->getMimeType();

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $angle = [3 => 180, 6 => -90, 8 => 90][(int) (@exif_read_data($path)['Orientation'] ?? 1)] ?? 0;
            $image = $angle !== 0 ? (imagerotate($image, $angle, 0) ?: $image) : $image;
        }

        imagesavealpha($image, true);

        match ($mime) {
            'image/png' => imagepng($image, $path),
            'image/webp' => imagewebp($image, $path, 90),
            default => imagejpeg($image, $path, 90),
        };

        clearstatcache(true, $path);
    }
}
