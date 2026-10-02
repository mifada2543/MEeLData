<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\TraineeTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TraineeTask>
 */
class TraineeTaskFactory extends Factory
{
    protected $model = TraineeTask::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-3 days', '+2 days');

        return [
            'trainee_id' => User::factory(),
            'task' => fake()->sentence(3),
            'desc' => fake()->optional()->paragraph(),
            'start_date' => $start,
            'deadline' => (clone $start)->modify('+2 days'),
            'status' => TaskStatus::Progress,
        ];
    }

    public function status(TaskStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function done(): static
    {
        return $this->status(TaskStatus::Done);
    }

    public function late(): static
    {
        return $this->status(TaskStatus::Late);
    }

    public function canceled(): static
    {
        return $this->status(TaskStatus::Canceled);
    }

    /**
     * Deadline sudah terlewati dan status masih progress.
     */
    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => TaskStatus::Progress,
            'start_date' => now()->subDays(10),
            'deadline' => now()->subDay(),
        ]);
    }
}
