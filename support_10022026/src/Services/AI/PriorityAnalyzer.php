<?php

namespace App\Services\AI;

class PriorityAnalyzer
{
    /**
     * Keywords that indicate urgency levels
     */
    private array $urgentPatterns = [
        'patterns' => [
            '/production\s+(down|issue|problem)/i',
            '/site\s+(down|not working)/i',
            '/system\s+(down|crash)/i',
            '/cannot\s+(access|login|work)/i',
            '/service\s+outage/i',
            '/data\s+(loss|missing|corrupted)/i',
            '/security\s+(breach|issue|vulnerability)/i',
        ],
        'keywords' => [
            'urgent', 'asap', 'immediately', 'emergency', 'critical',
            'blocking', 'outage', 'down', 'crashed',
        ],
    ];

    private array $highPatterns = [
        'patterns' => [
            '/error\s+(message|code)/i',
            '/not\s+(working|functioning|loading)/i',
            '/failed\s+to/i',
            '/unable\s+to/i',
            '/broken\s+/i',
        ],
        'keywords' => [
            'error', 'bug', 'broken', 'issue', 'problem', 'failed',
            'crash', 'freeze', 'stuck', 'slow', 'hanging',
        ],
    ];

    private array $lowPatterns = [
        'patterns' => [
            '/would\s+(like|love|be nice)/i',
            '/feature\s+request/i',
            '/suggestion/i',
            '/nice\s+to\s+have/i',
            '/when\s+(you|possible)/i',
        ],
        'keywords' => [
            'suggestion', 'idea', 'enhancement', 'improvement',
            'cosmetic', 'minor', 'small', 'tweak',
        ],
    ];

    /**
     * Analyze text to determine priority
     */
    public function analyze(string $text): array
    {
        $text = strtolower($text);

        // Check for urgent indicators
        $urgentScore = $this->calculateScore($text, $this->urgentPatterns);
        if ($urgentScore >= 2) {
            return ['priority' => 'urgent', 'confidence' => min(1.0, $urgentScore * 0.3)];
        }

        // Check for high priority indicators
        $highScore = $this->calculateScore($text, $this->highPatterns);
        if ($highScore >= 2 || $urgentScore >= 1) {
            return ['priority' => 'high', 'confidence' => min(1.0, $highScore * 0.25)];
        }

        // Check for low priority indicators
        $lowScore = $this->calculateScore($text, $this->lowPatterns);
        if ($lowScore >= 2) {
            return ['priority' => 'low', 'confidence' => min(1.0, $lowScore * 0.3)];
        }

        // Default to medium
        return ['priority' => 'medium', 'confidence' => 0.5];
    }

    /**
     * Calculate match score for patterns and keywords
     */
    private function calculateScore(string $text, array $config): int
    {
        $score = 0;

        // Check patterns (regex)
        foreach ($config['patterns'] as $pattern) {
            if (preg_match($pattern, $text)) {
                $score += 2; // Patterns count more
            }
        }

        // Check keywords
        foreach ($config['keywords'] as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $score++;
            }
        }

        return $score;
    }

    /**
     * Check if text contains SLA-related urgency
     */
    public function hasSLAIndicators(string $text): bool
    {
        $slaPatterns = [
            '/deadline/i',
            '/by\s+(today|tomorrow|end\s+of\s+day)/i',
            '/time\s+sensitive/i',
            '/sla\s+(breach|violation)/i',
            '/contract/i',
        ];

        foreach ($slaPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all matched urgency indicators (for debugging/display)
     */
    public function getMatchedIndicators(string $text): array
    {
        $text = strtolower($text);
        $matched = [];

        $allPatterns = [
            'urgent' => $this->urgentPatterns,
            'high' => $this->highPatterns,
            'low' => $this->lowPatterns,
        ];

        foreach ($allPatterns as $level => $config) {
            foreach ($config['keywords'] as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $matched[] = ['level' => $level, 'match' => $keyword];
                }
            }
        }

        return $matched;
    }
}
