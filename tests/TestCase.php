<?php

namespace Tests;

use App\Models\TraineeTask;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function admin(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role' => User::ROLE_ADMIN]);
    }

    protected function trainee(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['role' => User::ROLE_TRAINEE]);
    }

    protected function task(array $attributes = []): TraineeTask
    {
        return TraineeTask::factory()->create($attributes);
    }

    protected function authAsAdmin(): self
    {
        $this->actingAs($this->admin(), 'web');

        return $this;
    }

    protected function authAsTrainee(?User $trainee = null): self
    {
        $this->actingAs($trainee ?? $this->trainee(), 'web');

        return $this;
    }

    /**
     * Tanggal create/update yang valid.
     *
     * Sejak aturan "tidak boleh lampau" diperkenalkan, task tidak boleh
     * dibuat dengan tanggal lampau (`App\Rules\NotBeforeNow`), jadi test TIDAK
     * boleh memakai tanggal hardcode - cepat atau lambat akan basi. Test
     * memakai offset relatif terhadap `now()` agar tidak bergantung pada
     * tanggal eksekusi.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    protected function validDates(array $overrides = []): array
    {
        return array_merge([
            'start_date' => now()->addDay()->format('Y-m-d H:i:s'),
            'deadline' => now()->addDays(7)->format('Y-m-d H:i:s'),
        ], $overrides);
    }

    /**
     * Tanggal masa lalu, untuk menguji aturan "tidak boleh lampau".
     *
     * @return array<string, string>
     */
    protected function pastDates(): array
    {
        return [
            'start_date' => now()->subDays(3)->format('Y-m-d H:i:s'),
            'deadline' => now()->subDay()->format('Y-m-d H:i:s'),
        ];
    }
}
