<?php

namespace Tests;

use App\Models\Admin;
use App\Models\TraineeTask;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function admin(array $attributes = []): Admin
    {
        return Admin::factory()->create($attributes);
    }

    protected function trainee(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    protected function task(array $attributes = []): TraineeTask
    {
        return TraineeTask::factory()->create($attributes);
    }

    protected function authAsAdmin(): self
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin');

        return $this;
    }

    protected function authAsTrainee(?User $trainee = null): self
    {
        $trainee ??= $this->trainee();

        $this->actingAs($trainee, 'web');

        return $this;
    }
}
