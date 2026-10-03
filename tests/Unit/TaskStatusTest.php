<?php

namespace Tests\Unit;

use App\Enums\TaskStatus;
use PHPUnit\Framework\TestCase;

class TaskStatusTest extends TestCase
{
    public function test_values_match_database_enum(): void
    {
        $this->assertSame(
            ['progress', 'done', 'late', 'canceled'],
            TaskStatus::values(),
        );
    }

    public function test_manual_values_exclude_late(): void
    {
        $this->assertSame(['progress', 'done', 'canceled'], TaskStatus::manualValues());
        $this->assertNotContains('late', TaskStatus::manualValues());
    }

    public function test_labels_are_human_readable(): void
    {
        $this->assertSame('Progress', TaskStatus::Progress->label());
        $this->assertSame('Selesai', TaskStatus::Done->label());
        $this->assertSame('Terlambat', TaskStatus::Late->label());
        $this->assertSame('Dibatalkan', TaskStatus::Canceled->label());
    }

    public function test_manual_cases_returns_three_cases(): void
    {
        $this->assertCount(3, TaskStatus::manualCases());
    }

    public function test_can_be_created_from_value(): void
    {
        $this->assertSame(TaskStatus::Late, TaskStatus::from('late'));
    }
}
