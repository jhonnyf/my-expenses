<?php

namespace App\Actions;

use App\Models\File;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UpdateUserAvatarAction
{
    public function execute(User $user, UploadedFile $uploadedFile): File
    {
        [$width, $height] = $this->dimensions($uploadedFile);

        return DB::transaction(function () use ($user, $uploadedFile, $width, $height) {
            $user->avatar?->delete();

            $path = $uploadedFile->store('avatars', 'public');

            return $user->files()->create([
                'collection' => 'avatar',
                'disk' => 'public',
                'path' => $path,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'mime_type' => $uploadedFile->getMimeType(),
                'size' => $uploadedFile->getSize(),
                'width' => $width,
                'height' => $height,
            ]);
        });
    }

    private function dimensions(UploadedFile $uploadedFile): array
    {
        $imageSize = @getimagesize($uploadedFile->getRealPath());

        return $imageSize === false
            ? [null, null]
            : [$imageSize[0], $imageSize[1]];
    }
}
