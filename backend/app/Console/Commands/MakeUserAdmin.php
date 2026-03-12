<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\GroupService;
use Illuminate\Console\Command;

class MakeUserAdmin extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'user:make-admin {email : The email address of the user to make admin}';

    /**
     * The console command description.
     */
    protected $description = 'Promote a user to admin (add to admin group)';

    /**
     * Execute the console command.
     */
    public function handle(GroupService $groupService): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return Command::FAILURE;
        }

        if ($user->inGroup('admin')) {
            $this->warn("User '{$user->name}' ({$email}) is already an admin.");
            return Command::SUCCESS;
        }

        $groupService->ensureDefaultGroupsExist();
        $user->assignGroup('admin');

        $this->info("User '{$user->name}' ({$email}) has been promoted to admin.");

        return Command::SUCCESS;
    }
}
