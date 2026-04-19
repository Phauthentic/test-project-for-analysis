<?php

declare(strict_types=1);

namespace Phauthentic\AnalysisTestProject;

/**
 * Intentionally high cognitive complexity for phpcca metrics.
 */
final class CognitiveHotspot
{
    public function deeplyNestedScorer(int $seed, int $depth): int
    {
        $score = $seed;
        if ($depth > 0) {
            $score += 1;
            if ($depth > 1) {
                $score += 2;
                if ($depth > 2) {
                    $score += 3;
                    if ($depth > 3) {
                        $score += 4;
                        if ($depth > 4) {
                            $score += 5;
                            for ($i = 0; $i < 8; $i++) {
                                if ($i % 2 === 0) {
                                    $score += $i;
                                } elseif ($i % 3 === 0) {
                                    $score -= 1;
                                } else {
                                    $score += (int)($i / 2);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $score;
    }
}
