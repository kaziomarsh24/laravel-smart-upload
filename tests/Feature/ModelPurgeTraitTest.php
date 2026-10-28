<?php

namespace KaziOmar\SmartUpload\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use KaziOmar\SmartUpload\Tests\TestCase;
use KaziOmar\SmartUpload\Traits\HasUploadPurge;

class TestUser extends Model
{
    use HasUploadPurge;

    protected $table = 'test_users';
    protected $guarded = [];
    protected array $purgeableFiles = ['avatar', 'cover'];
}

class SoftDeletedTestUser extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;
    use HasUploadPurge;

    protected $table = 'soft_test_users';
    protected $guarded = [];
    protected array $purgeableFiles = ['avatar'];
}

class ModelPurgeTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('avatar')->nullable();
            $table->string('cover')->nullable();
            $table->timestamps();
        });

        Schema::create('soft_test_users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('avatar')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function test_purges_old_file_when_model_attribute_is_updated(): void
    {
        Storage::disk('public')->put('avatars/v1.jpg', 'v1');
        Storage::disk('public')->put('avatars/v2.jpg', 'v2');

        $user = TestUser::create([
            'name' => 'Kazi Omar',
            'avatar' => 'avatars/v1.jpg',
        ]);

        $this->assertTrue(Storage::disk('public')->exists('avatars/v1.jpg'));

        // Update avatar to v2
        $user->update(['avatar' => 'avatars/v2.jpg']);

        // v1 should have been deleted, v2 stays
        $this->assertFalse(Storage::disk('public')->exists('avatars/v1.jpg'));
        $this->assertTrue(Storage::disk('public')->exists('avatars/v2.jpg'));
    }

    public function test_purges_all_files_when_model_is_deleted(): void
    {
        Storage::disk('public')->put('avatars/user-avatar.jpg', 'pic');
        Storage::disk('public')->put('covers/user-cover.jpg', 'banner');

        $user = TestUser::create([
            'name' => 'Kazi Omar',
            'avatar' => 'avatars/user-avatar.jpg',
            'cover' => 'covers/user-cover.jpg',
        ]);

        $this->assertTrue(Storage::disk('public')->exists('avatars/user-avatar.jpg'));
        $this->assertTrue(Storage::disk('public')->exists('covers/user-cover.jpg'));

        // Delete user
        $user->delete();

        // Both files should be auto-purged from storage
        $this->assertFalse(Storage::disk('public')->exists('avatars/user-avatar.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('covers/user-cover.jpg'));
    }

    public function test_respects_soft_deletes_and_only_purges_on_force_delete(): void
    {
        Storage::disk('public')->put('avatars/soft-user.jpg', 'pic');

        $user = SoftDeletedTestUser::create([
            'name' => 'Soft User',
            'avatar' => 'avatars/soft-user.jpg',
        ]);

        $this->assertTrue(Storage::disk('public')->exists('avatars/soft-user.jpg'));

        // Soft delete model -> file must NOT be deleted yet!
        $user->delete();
        $this->assertTrue(Storage::disk('public')->exists('avatars/soft-user.jpg'));

        // Force delete model -> file MUST be deleted!
        $user->forceDelete();
        $this->assertFalse(Storage::disk('public')->exists('avatars/soft-user.jpg'));
    }

    public function test_prevents_data_loss_by_not_deleting_old_file_if_model_update_rolls_back(): void
    {
        Storage::disk('public')->put('avatars/safe-orig.jpg', 'original');

        $user = TestUser::create([
            'name' => 'John Doe',
            'avatar' => 'avatars/safe-orig.jpg',
        ]);

        $this->assertTrue(Storage::disk('public')->exists('avatars/safe-orig.jpg'));

        try {
            DB::transaction(function () use ($user) {
                $user->update(['avatar' => 'avatars/new-uncommitted.jpg']);

                // Simulate DB failure before commit
                throw new \Exception('Failed SQL transaction!');
            });
        } catch (\Exception $e) {
            // Expected
        }

        // Because transaction rolled back, old file MUST STILL EXIST on disk (No data loss!)
        $this->assertTrue(Storage::disk('public')->exists('avatars/safe-orig.jpg'));
    }
}
