<?php
/**
 * ROTZ Email Butler - Multi-AI Ensemble System
 * Coordinates multiple AI providers for consensus-based email analysis
 */

namespace Rotz\EmailButler\Classes;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class MultiAIEnsemble {
    private $db;
    private $logger;
    private $httpClient;
    private $aiProviders = [];
    
    // AI Provider configurations
    private $providerConfigs = [
        'openai' => [
            'name' => 'OpenAI',
            'models' => ['gpt-4', 'gpt-4-turbo', 'gpt-3.5-turbo'],
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'headers' => ['Authorization' => 'Bearer {api_key}', 'Content-Type' => 'application/json']
        ],
        'anthropic' => [
            'name' => 'Anthropic',
            'models' => ['claude-3-5-sonnet-20241022', 'claude-3-haiku-20240307', 'claude-3-opus-20240229'],
            'endpoint' => 'https://api.anthropic.com/v1/messages',
            'headers' => ['x-api-key' => '{api_key}', 'Content-Type' => 'application/json', 'anthropic-version' => '2023-06-01']
        ],
        'google' => [
            'name' => 'Google',
            'models' => ['gemini-pro', 'gemini-pro-vision', 'gemini-flash'],
            'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent',
            'headers' => ['Content-Type' => 'application/json']
        ],
        'qwen' => [
            'name' => 'Qwen',
            'models' => ['qwen2.5-max', 'qwen2-72b', 'qvq-72b'],
            'endpoint' => 'https://dashscope.aliyuncs.com/api/v1/services/aigc/text-generation/generation',
            'headers' => ['Authorization' => 'Bearer {api_key}', 'Content-Type' => 'application/json']
        ],
        'groq' => [
            'name' => 'Groq',
            'models' => ['llama-3.1-70b-versatile', 'mixtral-8x7b-32768', 'gemma-7b-it'],
            'endpoint' => 'https://api.groq.com/openai/v1/chat/completions',
            'headers' => ['Authorization' => 'Bearer {api_key}', 'Content-Type' => 'application/json']
        ],
        'cohere' => [
            'name' => 'Cohere',
            'models' => ['command-r-plus', 'command-r', 'command-light'],
            'endpoint' => 'https://api.cohere.ai/v1/chat',
            'headers' => ['Authorization' => 'Bearer {api_key}', 'Content-Type' => 'application/json']
        ],
        'mistral' => [
            'name' => 'Mistral',
            'models' => ['mistral-large-latest', 'mistral-medium-latest', 'mistral-small-latest'],
            'endpoint' => 'https://api.mistral.ai/v1/chat/completions',
            'headers' => ['Authorization' => 'Bearer {api_key}', 'Content-Type' => 'application/json']
        ],
        'together' => [
            'name' => 'Together AI',
            'models' => ['meta-llama/Llama-2-70b-chat-hf', 'mistralai/Mixtral-8x7B-Instruct-v0.1', 'togethercomputer/RedPajama-INCITE-7B-Chat'],
            'endpoint' => 'https://api.together.xyz/v1/chat/completions',
            'headers' => ['Authorization' => 'Bearer {api_key}', 'Content-Type' => 'application/json']
        ]
    ];

    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new Logger('multi_ai_ensemble');
        $this->logger->pushHandler(new StreamHandler(__DIR__ . '/../logs/ai_ensemble.log', Logger::INFO));
        $this->httpClient = new Client(['timeout' => 30]);
        $this->loadEnabledProviders();
    }

    /**
     * Load enabled AI providers from database
     */
    private function loadEnabledProviders() {
        $sql = "SELECT * FROM ai_providers WHERE is_enabled = 1 AND status = 'active' ORDER BY priority_weight DESC";
        $providers = $this->db->fetchAll($sql);
        
        foreach ($providers as $provider) {
            $this->aiProviders[] = [
                'id' => $provider['id'],
                'provider_name' => $provider['provider_name'],
                'model_name' => $provider['model_name'],
                'api_key' => $this->db->decrypt($provider['api_key_encrypted']),
                'api_endpoint' => $provider['api_endpoint'],
                'priority_weight' => $provider['priority_weight'],
                'max_tokens' => $provider['max_tokens'],
                'temperature' => $provider['temperature']
            ];
        }
    }

    /**
     * Analyze email using multi-AI ensemble
     */
    public function analyzeEmail($emailData) {
        if (empty($this->aiProviders)) {
            throw new Exception('No AI providers are enabled');
        }

        $prompt = $this->buildAnalysisPrompt($emailData);
        $results = [];
        $startTime = microtime(true);

        // Send requests to all enabled providers in parallel
        foreach ($this->aiProviders as $provider) {
            try {
                $result = $this->callAIProvider($provider, $prompt);
                if ($result) {
                    $results[] = $result;
                }
            } catch (Exception $e) {
                $this->logger->error("AI Provider {$provider['provider_name']} failed: " . $e->getMessage());
                $this->updateProviderStats($provider['id'], false, 0, 0);
            }
        }

        $totalTime = microtime(true) - $startTime;

        if (empty($results)) {
            throw new Exception('All AI providers failed to analyze the email');
        }

        // Calculate consensus from all results
        $consensus = $this->calculateConsensus($results);
        
        // Store individual results
        foreach ($results as $result) {
            $this->storeAnalysisResult($emailData['id'], $result);
        }

        $this->logger->info("Email analysis completed", [
            'email_id' => $emailData['id'],
            'providers_used' => count($results),
            'total_time' => $totalTime,
            'consensus_confidence' => $consensus['confidence']
        ]);

        return $consensus;
    }

    /**
     * Build analysis prompt for AI providers
     */
    private function buildAnalysisPrompt($emailData) {
        $prompt = "Analyze this email and provide a structured response in JSON format:\n\n";
        $prompt .= "Subject: " . ($emailData['subject'] ?? 'No subject') . "\n";
        $prompt .= "From: " . $emailData['sender_email'] . "\n";
        $prompt .= "Body: " . substr($emailData['body_text'] ?? $emailData['body_html'] ?? '', 0, 2000) . "\n\n";
        
        $prompt .= "Please respond with a JSON object containing:\n";
        $prompt .= "{\n";
        $prompt .= "  \"category\": \"one of: work, personal, marketing, finance, travel, shopping, social, spam, important\",\n";
        $prompt .= "  \"priority\": \"high, medium, or low\",\n";
        $prompt .= "  \"sentiment\": \"positive, neutral, or negative\",\n";
        $prompt .= "  \"confidence\": \"decimal between 0.0 and 1.0\",\n";
        $prompt .= "  \"summary\": \"brief 1-2 sentence summary\",\n";
        $prompt .= "  \"action_required\": \"true or false\",\n";
        $prompt .= "  \"suggested_actions\": [\"array of suggested actions if any\"]\n";
        $prompt .= "}\n\n";
        $prompt .= "Only respond with valid JSON, no additional text.";

        return $prompt;
    }

    /**
     * Call individual AI provider
     */
    private function callAIProvider($provider, $prompt) {
        $startTime = microtime(true);
        $config = $this->providerConfigs[$provider['provider_name']] ?? null;
        
        if (!$config) {
            throw new Exception("Unknown provider: {$provider['provider_name']}");
        }

        $requestData = $this->buildProviderRequest($provider, $prompt, $config);
        $headers = $this->buildProviderHeaders($provider, $config);
        $endpoint = $this->buildProviderEndpoint($provider, $config);

        try {
            $response = $this->httpClient->post($endpoint, [
                'headers' => $headers,
                'json' => $requestData,
                'timeout' => 30
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);
            $content = $this->extractContentFromResponse($provider['provider_name'], $responseData);
            
            $processingTime = microtime(true) - $startTime;
            $tokensUsed = $this->extractTokensUsed($provider['provider_name'], $responseData);
            $cost = $this->calculateCost($provider['provider_name'], $provider['model_name'], $tokensUsed);

            // Update provider statistics
            $this->updateProviderStats($provider['id'], true, $processingTime, $cost);

            // Parse AI response
            $analysis = $this->parseAIResponse($content);
            
            return [
                'provider_id' => $provider['id'],
                'provider_name' => $provider['provider_name'],
                'model_name' => $provider['model_name'],
                'analysis' => $analysis,
                'processing_time' => $processingTime,
                'tokens_used' => $tokensUsed,
                'cost' => $cost,
                'raw_response' => json_encode($responseData),
                'priority_weight' => $provider['priority_weight']
            ];

        } catch (RequestException $e) {
            $processingTime = microtime(true) - $startTime;
            $this->updateProviderStats($provider['id'], false, $processingTime, 0);
            throw new Exception("API request failed: " . $e->getMessage());
        }
    }

    /**
     * Build request data for specific provider
     */
    private function buildProviderRequest($provider, $prompt, $config) {
        switch ($provider['provider_name']) {
            case 'openai':
            case 'groq':
            case 'mistral':
            case 'together':
                return [
                    'model' => $provider['model_name'],
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => $provider['max_tokens'],
                    'temperature' => $provider['temperature']
                ];

            case 'anthropic':
                return [
                    'model' => $provider['model_name'],
                    'max_tokens' => $provider['max_tokens'],
                    'temperature' => $provider['temperature'],
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ]
                ];

            case 'google':
                return [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => $provider['max_tokens'],
                        'temperature' => $provider['temperature']
                    ]
                ];

            case 'qwen':
                return [
                    'model' => $provider['model_name'],
                    'input' => [
                        'messages' => [
                            ['role' => 'user', 'content' => $prompt]
                        ]
                    ],
                    'parameters' => [
                        'max_tokens' => $provider['max_tokens'],
                        'temperature' => $provider['temperature']
                    ]
                ];

            case 'cohere':
                return [
                    'model' => $provider['model_name'],
                    'message' => $prompt,
                    'max_tokens' => $provider['max_tokens'],
                    'temperature' => $provider['temperature']
                ];

            default:
                throw new Exception("Unsupported provider: {$provider['provider_name']}");
        }
    }

    /**
     * Build headers for specific provider
     */
    private function buildProviderHeaders($provider, $config) {
        $headers = [];
        foreach ($config['headers'] as $key => $value) {
            $headers[$key] = str_replace('{api_key}', $provider['api_key'], $value);
        }
        return $headers;
    }

    /**
     * Build endpoint for specific provider
     */
    private function buildProviderEndpoint($provider, $config) {
        $endpoint = $config['endpoint'];
        
        if ($provider['provider_name'] === 'google') {
            $endpoint = str_replace('{model}', $provider['model_name'], $endpoint);
            $endpoint .= '?key=' . $provider['api_key'];
        } elseif ($provider['api_endpoint']) {
            $endpoint = $provider['api_endpoint'];
        }
        
        return $endpoint;
    }

    /**
     * Extract content from provider response
     */
    private function extractContentFromResponse($providerName, $responseData) {
        switch ($providerName) {
            case 'openai':
            case 'groq':
            case 'mistral':
            case 'together':
                return $responseData['choices'][0]['message']['content'] ?? '';

            case 'anthropic':
                return $responseData['content'][0]['text'] ?? '';

            case 'google':
                return $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';

            case 'qwen':
                return $responseData['output']['text'] ?? '';

            case 'cohere':
                return $responseData['text'] ?? '';

            default:
                return '';
        }
    }

    /**
     * Extract tokens used from response
     */
    private function extractTokensUsed($providerName, $responseData) {
        switch ($providerName) {
            case 'openai':
            case 'groq':
            case 'mistral':
            case 'together':
                return $responseData['usage']['total_tokens'] ?? 0;

            case 'anthropic':
                return ($responseData['usage']['input_tokens'] ?? 0) + ($responseData['usage']['output_tokens'] ?? 0);

            case 'google':
                return $responseData['usageMetadata']['totalTokenCount'] ?? 0;

            case 'qwen':
                return $responseData['usage']['total_tokens'] ?? 0;

            case 'cohere':
                return $responseData['meta']['tokens']['input_tokens'] + $responseData['meta']['tokens']['output_tokens'] ?? 0;

            default:
                return 0;
        }
    }

    /**
     * Calculate cost based on provider and tokens
     */
    private function calculateCost($providerName, $modelName, $tokensUsed) {
        // Simplified cost calculation - in production, use actual pricing
        $costPerToken = [
            'openai' => 0.00002,
            'anthropic' => 0.00003,
            'google' => 0.000001,
            'qwen' => 0.000001,
            'groq' => 0.000001,
            'cohere' => 0.00001,
            'mistral' => 0.00002,
            'together' => 0.000001
        ];

        return ($costPerToken[$providerName] ?? 0.00001) * $tokensUsed;
    }

    /**
     * Parse AI response JSON
     */
    private function parseAIResponse($content) {
        // Clean up the response to extract JSON
        $content = trim($content);
        
        // Remove markdown code blocks if present
        $content = preg_replace('/```json\s*/', '', $content);
        $content = preg_replace('/```\s*$/', '', $content);
        
        $analysis = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from AI provider: ' . json_last_error_msg());
        }

        // Validate required fields
        $required = ['category', 'priority', 'sentiment', 'confidence'];
        foreach ($required as $field) {
            if (!isset($analysis[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        return $analysis;
    }

    /**
     * Calculate consensus from multiple AI results
     */
    private function calculateConsensus($results) {
        if (empty($results)) {
            throw new Exception('No results to calculate consensus from');
        }

        $categories = [];
        $priorities = [];
        $sentiments = [];
        $confidences = [];
        $summaries = [];
        $actionsRequired = [];
        $suggestedActions = [];

        // Collect all responses with weights
        foreach ($results as $result) {
            $analysis = $result['analysis'];
            $weight = $result['priority_weight'];

            // Weighted voting for categorical fields
            $categories[$analysis['category']] = ($categories[$analysis['category']] ?? 0) + $weight;
            $priorities[$analysis['priority']] = ($priorities[$analysis['priority']] ?? 0) + $weight;
            $sentiments[$analysis['sentiment']] = ($sentiments[$analysis['sentiment']] ?? 0) + $weight;
            
            // Weighted average for confidence
            $confidences[] = $analysis['confidence'] * $weight;
            
            // Collect summaries and actions
            if (!empty($analysis['summary'])) {
                $summaries[] = $analysis['summary'];
            }
            
            if (!empty($analysis['action_required'])) {
                $actionsRequired[] = $analysis['action_required'];
            }
            
            if (!empty($analysis['suggested_actions'])) {
                $suggestedActions = array_merge($suggestedActions, $analysis['suggested_actions']);
            }
        }

        // Determine consensus values
        $finalCategory = array_keys($categories, max($categories))[0];
        $finalPriority = array_keys($priorities, max($priorities))[0];
        $finalSentiment = array_keys($sentiments, max($sentiments))[0];
        $finalConfidence = array_sum($confidences) / array_sum(array_column($results, 'priority_weight'));
        
        // Determine action required by majority vote
        $actionRequiredCount = array_count_values($actionsRequired);
        $finalActionRequired = isset($actionRequiredCount[true]) && $actionRequiredCount[true] > count($actionsRequired) / 2;
        
        // Create combined summary
        $finalSummary = $this->createCombinedSummary($summaries);
        
        // Deduplicate suggested actions
        $finalSuggestedActions = array_unique($suggestedActions);

        return [
            'category' => $finalCategory,
            'priority' => $finalPriority,
            'sentiment' => $finalSentiment,
            'confidence' => round($finalConfidence, 3),
            'summary' => $finalSummary,
            'action_required' => $finalActionRequired,
            'suggested_actions' => $finalSuggestedActions,
            'providers_used' => count($results),
            'individual_results' => $results
        ];
    }

    /**
     * Create combined summary from multiple summaries
     */
    private function createCombinedSummary($summaries) {
        if (empty($summaries)) {
            return 'No summary available';
        }

        if (count($summaries) === 1) {
            return $summaries[0];
        }

        // For multiple summaries, take the longest one as it's likely most comprehensive
        usort($summaries, function($a, $b) {
            return strlen($b) - strlen($a);
        });

        return $summaries[0];
    }

    /**
     * Store individual analysis result
     */
    private function storeAnalysisResult($emailId, $result) {
        $data = [
            'email_id' => $emailId,
            'ai_provider_id' => $result['provider_id'],
            'category' => $result['analysis']['category'],
            'priority' => $result['analysis']['priority'],
            'sentiment' => $result['analysis']['sentiment'],
            'confidence' => $result['analysis']['confidence'],
            'summary' => $result['analysis']['summary'] ?? null,
            'action_required' => $result['analysis']['action_required'] ?? false,
            'suggested_actions' => !empty($result['analysis']['suggested_actions']) ? json_encode($result['analysis']['suggested_actions']) : null,
            'processing_time' => $result['processing_time'],
            'tokens_used' => $result['tokens_used'],
            'cost' => $result['cost'],
            'raw_response' => $result['raw_response']
        ];

        return $this->db->insert('ai_analysis_results', $data);
    }

    /**
     * Update provider statistics
     */
    private function updateProviderStats($providerId, $success, $processingTime, $cost) {
        $sql = "UPDATE ai_providers SET 
                requests_count = requests_count + 1,
                successful_requests = successful_requests + ?,
                failed_requests = failed_requests + ?,
                total_cost = total_cost + ?,
                average_response_time = (average_response_time * (requests_count - 1) + ?) / requests_count,
                last_used = NOW()
                WHERE id = ?";
        
        $this->db->query($sql, [
            $success ? 1 : 0,
            $success ? 0 : 1,
            $cost,
            $processingTime,
            $providerId
        ]);
    }

    /**
     * Get available AI providers
     */
    public function getAvailableProviders() {
        return $this->providerConfigs;
    }

    /**
     * Test AI provider connection
     */
    public function testProvider($providerId) {
        $sql = "SELECT * FROM ai_providers WHERE id = ?";
        $provider = $this->db->fetchOne($sql, [$providerId]);
        
        if (!$provider) {
            throw new Exception('Provider not found');
        }

        $testPrompt = "Respond with a simple JSON object: {\"status\": \"ok\", \"message\": \"test successful\"}";
        
        try {
            $providerData = [
                'id' => $provider['id'],
                'provider_name' => $provider['provider_name'],
                'model_name' => $provider['model_name'],
                'api_key' => $this->db->decrypt($provider['api_key_encrypted']),
                'api_endpoint' => $provider['api_endpoint'],
                'priority_weight' => $provider['priority_weight'],
                'max_tokens' => 100,
                'temperature' => 0.1
            ];

            $result = $this->callAIProvider($providerData, $testPrompt);
            
            // Update provider status to active
            $this->db->update('ai_providers', 
                ['status' => 'active', 'last_error' => null], 
                'id = ?', 
                [$providerId]
            );

            return [
                'success' => true,
                'message' => 'Provider test successful',
                'response_time' => $result['processing_time'],
                'tokens_used' => $result['tokens_used']
            ];

        } catch (Exception $e) {
            // Update provider status to error
            $this->db->update('ai_providers', 
                ['status' => 'error', 'last_error' => $e->getMessage()], 
                'id = ?', 
                [$providerId]
            );

            return [
                'success' => false,
                'message' => 'Provider test failed: ' . $e->getMessage()
            ];
        }
    }
}
?>

