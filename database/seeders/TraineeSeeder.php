<?php

namespace Database\Seeders;

use App\Enums\TaskStatus;
use App\Models\TraineeTask;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data demo: beberapa trainee + task. Aman dijalankan berulang kali
 * karena memakai updateOrCreate berdasarkan email trainee yang tetap.
 */
class TraineeSeeder extends Seeder
{
    /**
     * @var list<array{email: string, name: string}>
     */
    protected array $trainees = [
        ['email' => 'andi@example.com', 'name' => 'Andi Pratama'],
        ['email' => 'budi@example.com', 'name' => 'Budi Santoso'],
        ['email' => 'siti@example.com', 'name' => 'Siti Aminah'],
        ['email' => 'daffa@example.com', 'name' => 'Muhammad Daffa'],
    ];

    public function run(): void
    {
        foreach ($this->trainees as $trainee) {
            $user = User::updateOrCreate(
                ['email' => $trainee['email']],
                [
                    'name' => $trainee['name'],
                    'password' => 'password',
                ],
            );

            $this->seedTasks($user);
        }
    }

    protected function seedTasks(User $user): void
    {
        // Progress dengan deadline/normal, masih berjalan.
        TraineeTask::updateOrCreate(
            ['trainee_id' => $user->id, 'task' => 'Maintenance Kabel Jaringan Lantai 2'],
            [
                'desc' => 'Perbaikan kabel jaringan dan pengujian konektivitas setiap port.',
                'start_date' => now()->subDays(2),
                'deadline' => now()->addDays(2),
                'status' => TaskStatus::Progress,
            ],
        );

        // Selesai.
        TraineeTask::updateOrCreate(
            ['trainee_id' => $user->id, 'task' => 'Setup Server Monitoring'],
            [
                'desc' => 'Instalasi dan konfigurasi monitoring untuk server produksi.',
                'start_date' => now()->subDays(7),
                'deadline' => now()->subDays(4),
                'status' => TaskStatus::Done,
            ],
        );

        // Deadline sudah lewat dan masih progress -> akan menjadi late oleh scheduler.
        TraineeTask::updateOrCreate(
            ['trainee_id' => $user->id, 'task' => 'Dokumentasi Topologi Jaringan'],
            [
                'desc' => 'Dokumentasi topologi jaringan inti dan pengujian failover.',
                'start_date' => now()->subDays(12),
                'deadline' => now()->subDays(2),
                'status' => TaskStatus::Progress,
            ],
        );
    }
}
