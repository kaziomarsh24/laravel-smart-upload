<?php

namespace KaziOmar\SmartUpload\Traits;

use Illuminate\Database\Eloquent\Model;
use KaziOmar\SmartUpload\Facades\Upload;

/**
 * Trait HasUploadPurge
 *
 * Automatically purges old files from storage disks when Eloquent model attributes
 * are updated, or purges all associated files when the model is deleted.
 */
trait HasUploadPurge
{
    /**
     * Boot the trait and hook into Eloquent model events.
     */
    public static function bootHasUploadPurge(): void
    {
        // When updating, delete old files if their attribute changed
        static::updating(function (Model $model) {
            $purgeable = $model->getPurgeableFiles();

            foreach ($purgeable as $attribute) {
                if ($model->isDirty($attribute)) {
                    $original = $model->getOriginal($attribute);
                    if ($original) {
                        Upload::delete($original);
                    }
                }
            }
        });

        // When model is deleted, delete all referenced files
        static::deleted(function (Model $model) {
            // If soft deletes is used, only delete if force deleting
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                return;
            }

            $purgeable = $model->getPurgeableFiles();
            foreach ($purgeable as $attribute) {
                $path = $model->getAttribute($attribute);
                if ($path) {
                    Upload::delete($path);
                }
            }
        });
    }

    /**
     * Get the list of purgeable file attributes for this model.
     *
     * @return array<string>
     */
    public function getPurgeableFiles(): array
    {
        return property_exists($this, 'purgeableFiles') && is_array($this->purgeableFiles)
            ? $this->purgeableFiles
            : [];
    }
}
