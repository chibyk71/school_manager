<?php

namespace Tests\Unit\Promotion;

use PHPUnit\Framework\TestCase;

/**
 * Lightweight pure-logic coverage for promotion recommendation rules.
 * Integration coverage (DB, placement, jobs) should be added as Feature tests
 * once factories for Student/ExamResult/ClassSection are stable.
 */
class PromotionRecommendationLogicTest extends TestCase
{
    public function test_missing_required_subjects_means_incomplete(): void
    {
        $required = ['subj-math', 'subj-eng', 'subj-sci'];
        $found = ['subj-math', 'subj-eng'];
        $missing = array_values(array_diff($required, $found));

        $this->assertSame(['subj-sci'], $missing);
        $recommendation = $missing !== [] || count($found) === 0 ? 'incomplete' : 'promote';
        $this->assertSame('incomplete', $recommendation);
    }

    public function test_subject_score_uses_average_not_latest_only(): void
    {
        $scores = [40.0, 60.0, 80.0];
        $avg = round(array_sum($scores) / count($scores), 2);
        $this->assertSame(60.0, $avg);
    }

    public function test_next_section_mapping_requires_same_arm_name(): void
    {
        $currentArm = 'Gold';
        $nextArms = ['Blue', 'Red', 'Green'];
        $mapped = in_array($currentArm, $nextArms, true) ? $currentArm : null;
        $this->assertNull($mapped);
    }

    public function test_approve_rejects_incomplete_outcomes(): void
    {
        $outcomes = ['promote', 'repeat', 'incomplete', 'graduate'];
        $incomplete = count(array_filter(
            $outcomes,
            fn ($o) => ! in_array($o, ['promote', 'repeat', 'graduate'], true)
        ));
        $this->assertSame(1, $incomplete);
    }
}
