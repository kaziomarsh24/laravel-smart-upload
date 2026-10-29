<?php

namespace KaziOmar\SmartUpload\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use KaziOmar\SmartUpload\Facades\Upload;

/**
 * Trait HasUploadPurge
 *
 * Automatically purges old files from storage disks when Eloquent model attributes
 * are updated, or purges all associated files when the model is deleted.
 * Protects against data loss by deferring file deletions until DB::afterCommit().
 */
trait HasUploadPurge
{
    /**
     * Boot the trait and hook into Eloquent model events.
     */
    public static function bootHasUploadPurge(): void
    {
        // When updated successfully in database, delete old replaced files
        static::updated(function (Model $model) {
            $purgeable = $model->getPurgeableFiles();

            foreach ($purgeable as $attribute) {
                if ($model->wasChanged($attribute)) {
                    $original = $model->getOriginal($attribute);
                    $current = $model->getAttribute($attribute);

                    if ($original && $original !== $current) {
                        if (is_array($original) && is_array($current)) {
                            $removed = array_diff($original, $current);
                            static::safePurge($removed);
                        } else {
                            static::safePurge($original);
                        }
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
                    static::safePurge($path);
                }
            }
        });
    }

    /**
     * Safely delete a file or collection of files, deferring until DB::afterCommit if inside a transaction.
     *
     * @param string|array<string>|null $pathOrPaths
     */
    protected static function safePurge(string|array|null $pathOrPaths): void
    {
        if (empty($pathOrPaths)) {
            return;
        }

        // Handle raw JSON strings stored in database
        if (is_string($pathOrPaths) && (str_starts_with($pathOrPaths, '[') || str_starts_with($pathOrPaths, '{'))) {
            $decoded = json_decode($pathOrPaths, true);
            if (is_array($decoded)) {
                $pathOrPaths = $decoded;
            }
        }

        if (is_array($pathOrPaths)) {
            foreach ($pathOrPaths as $path) {
                static::safePurge($path);
            }
            return;
        }

        if (!is_string($pathOrPaths) || trim($pathOrPaths) === '') {
            return;
        }

        $path = trim($pathOrPaths);

        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => Upload::delete($path));
        } else {
            Upload::delete($path);
        }
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
