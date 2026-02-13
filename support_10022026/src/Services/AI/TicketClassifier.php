<?php

namespace App\Services\AI;

use App\Core\Database;
use App\Models\Category;

class TicketClassifier
{
    private Database $db;
    private int $companyId;
    private array $config;

    // Priority keywords mapping
    private array $priorityKeywords = [
        'urgent' => ['urgent', 'asap', 'immediately', 'emergency', 'critical', 'down', 'broken', 'not working', 'production down', 'outage'],
        'high' => ['important', 'serious', 'major', 'blocking', 'cannot', 'unable', 'error', 'failed', 'crash', 'data loss'],
        'medium' => ['issue', 'problem', 'help', 'question', 'confused', 'difficulty', 'trouble'],
        'low' => ['suggestion', 'idea', 'feature request', 'minor', 'cosmetic', 'nice to have', 'when possible'],
    ];

    public function __construct(Database $db, int $companyId)
    {
        $this->db = $db;
        $this->companyId = $companyId;
        $this->config = require dirname(__DIR__, 3) . '/config/services.php';
    }

    /**
     * Classify a ticket based on its content
     */
    public function classify(string $text): array
    {
        $text = strtolower($text);

        // Try OpenAI first if configured
        if (!empty($this->config['openai']['api_key'])) {
            $result = $this->classifyWithOpenAI($text);
            if ($result) {
                return $result;
            }
        }

        // Fall back to keyword-based classification
        return $this->classifyWithKeywords($text);
    }

    /**
     * Classify using OpenAI API
     */
    private function classifyWithOpenAI(string $text): ?array
    {
        $apiKey = $this->config['openai']['api_key'];
        $model = $this->config['openai']['model'] ?? 'gpt-3.5-turbo';

        // Get categories for the prompt
        $categoryModel = new Category($this->db);
        $categoryModel->setCompanyId($this->companyId);
        $categories = $categoryModel->getActive();

        if (empty($categories)) {
            return null;
        }

        $categoryList = array_map(function($cat) {
            return "{$cat['id']}: {$cat['name']}";
        }, $categories);

        $prompt = "You are a support ticket classifier. Analyze the following ticket and respond with ONLY a JSON object (no markdown, no explanation).

Categories:
" . implode("\n", $categoryList) . "

Ticket text:
{$text}

Respond with JSON only:
{\"category_id\": <number or null>, \"priority\": \"<low|medium|high|urgent>\", \"confidence\": <0.0-1.0>}";

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a helpful support ticket classifier.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 100,
                    'temperature' => 0.3,
                ]),
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                return null;
            }

            $data = json_decode($response, true);
            $content = $data['choices'][0]['message']['content'] ?? null;

            if (!$content) {
                return null;
            }

            // Parse the JSON response
            $result = json_decode($content, true);
            if (!$result) {
                // Try to extract JSON from the response
                preg_match('/\{.*\}/s', $content, $matches);
                if ($matches) {
                    $result = json_decode($matches[0], true);
                }
            }

            if ($result && isset($result['priority'])) {
                // Validate and sanitize priority to match ENUM values
                $validPriorities = ['low', 'medium', 'high', 'urgent'];
                $priority = strtolower($result['priority'] ?? 'medium');
                if (!in_array($priority, $validPriorities)) {
                    // Map common variations
                    $priorityMap = [
                        'critical' => 'urgent',
                        'normal' => 'medium',
                        'standard' => 'medium',
                        'moderate' => 'medium',
                        'severe' => 'high',
                    ];
                    $priority = $priorityMap[$priority] ?? 'medium';
                }

                return [
                    'category_id' => $result['category_id'] ?? null,
                    'priority' => $priority,
                    'confidence' => $result['confidence'] ?? 0.7,
                    'source' => 'openai',
                ];
            }

            return null;
        } catch (\Exception $e) {
            error_log('OpenAI classification failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Classify using keyword matching (fallback)
     */
    private function classifyWithKeywords(string $text): array
    {
        // Detect priority
        $priority = $this->detectPriority($text);

        // Detect category
        $categoryResult = $this->detectCategory($text);

        return [
            'category_id' => $categoryResult['category_id'],
            'priority' => $priority,
            'confidence' => $categoryResult['confidence'],
            'source' => 'keywords',
        ];
    }

    /**
     * Detect priority based on keywords
     */
    private function detectPriority(string $text): string
    {
        foreach ($this->priorityKeywords as $priority => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($text, $keyword) !== false) {
                    return $priority;
                }
            }
        }

        return 'medium';
    }

    /**
     * Detect category based on keywords
     */
    private function detectCategory(string $text): array
    {
        $categoryModel = new Category($this->db);
        $categoryModel->setCompanyId($this->companyId);
        $categories = $categoryModel->getWithKeywords();

        $bestMatch = null;
        $bestScore = 0;
        $totalKeywords = 0;

        foreach ($categories as $category) {
            $keywords = json_decode($category['keywords'], true) ?? [];
            $totalKeywords = max($totalKeywords, count($keywords));
            $score = 0;

            foreach ($keywords as $keyword) {
                if (stripos($text, strtolower($keyword)) !== false) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $category['id'];
            }
        }

        // Calculate confidence based on keyword matches
        $confidence = $totalKeywords > 0 ? min(1.0, ($bestScore / max(1, $totalKeywords * 0.3))) : 0;

        return [
            'category_id' => $bestMatch,
            'confidence' => round($confidence, 4),
        ];
    }

    /**
     * Analyze sentiment (basic)
     */
    public function analyzeSentiment(string $text): string
    {
        $negativeWords = ['angry', 'frustrated', 'terrible', 'awful', 'worst', 'hate', 'disappointed', 'unacceptable'];
        $positiveWords = ['thank', 'appreciate', 'great', 'love', 'excellent', 'wonderful', 'happy'];

        $text = strtolower($text);
        $negativeScore = 0;
        $positiveScore = 0;

        foreach ($negativeWords as $word) {
            if (stripos($text, $word) !== false) {
                $negativeScore++;
            }
        }

        foreach ($positiveWords as $word) {
            if (stripos($text, $word) !== false) {
                $positiveScore++;
            }
        }

        if ($negativeScore > $positiveScore) {
            return 'negative';
        } elseif ($positiveScore > $negativeScore) {
            return 'positive';
        }

        return 'neutral';
    }
}
