<?php

namespace Database\Seeders;

use App\Enums\QuizMode;
use App\Enums\Role;
use App\Models\Study;
use App\Models\User;
use App\Services\LegacyQuestionImporter;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed roles, the first admin account, and a default study with the original question set.
     */
    public function run(LegacyQuestionImporter $importer): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web');
        }

        $admin = User::firstOrCreate(
            ['email' => config('quiz.admin.email')],
            [
                'name' => config('quiz.admin.name'),
                'password' => config('quiz.admin.password'),
                'email_verified_at' => now(),
            ],
        );
        $admin->assignRole(Role::Admin->value);

        $study = Study::firstOrCreate(
            ['slug' => 'learning-fun-default'],
            [
                'owner_id' => $admin->id,
                'name' => 'Learning Fun! — Default set',
                'description' => 'Original question set (phonics, letters, counting, objects, colours) for ages 4–6.',
                'locale' => 'en-US',
                'default_quiz_mode' => QuizMode::Correct,
                'age_min' => 4,
                'age_max' => 6,
            ],
        );

        $importer->importFile(database_path('seeders/data/questions.json'), $study);
    }
}
