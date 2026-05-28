<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Bestly LLC <hello@bestly.tech>
 *
 * @license AGPL-3.0-or-later
 */

namespace OCA\TalkTranscripts\Service;

use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Generates a Markdown summary of a transcript via an LLM provider.
 *
 * Two provider shapes are supported:
 *   - "openai"     => POST /v1/chat/completions { model, messages: [...] }
 *   - "anthropic"  => POST /v1/messages { model, system, messages, max_tokens }
 *
 * Any OpenAI-compatible endpoint (Ollama, LM Studio, vLLM, OpenRouter, etc.)
 * works with provider=openai by overriding the endpoint URL in admin settings.
 */
class SummaryService {
	private const HTTP_TIMEOUT = 120;
	private const MAX_TRANSCRIPT_CHARS = 80_000; // ~20k tokens, safe for most models

	private IClient $http;

	public function __construct(
		private ConfigService $config,
		IClientService $clientService,
		private LoggerInterface $logger,
	) {
		$this->http = $clientService->newClient();
	}

	public function summarize(string $transcript, string $audioName = ''): string {
		$transcript = $this->truncate($transcript);

		$provider = $this->config->getSummaryProvider();
		$endpoint = $this->config->getSummaryEndpoint();
		$model = $this->config->getSummaryModel();
		$apiKey = $this->config->getSummaryApiKey();
		$systemPrompt = $this->config->getSummaryPrompt();

		if ($endpoint === '' || $model === '') {
			throw new RuntimeException('Summary provider is not fully configured.');
		}

		$userContent = "Transcript of: $audioName\n\n---\n\n$transcript";

		return match ($provider) {
			'anthropic' => $this->callAnthropic($endpoint, $apiKey, $model, $systemPrompt, $userContent),
			default => $this->callOpenAi($endpoint, $apiKey, $model, $systemPrompt, $userContent),
		};
	}

	private function callOpenAi(string $endpoint, string $apiKey, string $model, string $system, string $user): string {
		$headers = ['Content-Type' => 'application/json'];
		if ($apiKey !== '') {
			$headers['Authorization'] = 'Bearer ' . $apiKey;
		}

		$payload = [
			'model' => $model,
			'messages' => [
				['role' => 'system', 'content' => $system],
				['role' => 'user', 'content' => $user],
			],
			'temperature' => 0.2,
		];

		$response = $this->http->post($endpoint, [
			'headers' => $headers,
			'body' => json_encode($payload, JSON_THROW_ON_ERROR),
			'timeout' => self::HTTP_TIMEOUT,
			'connect_timeout' => 15,
			'http_errors' => false,
		]);

		$status = $response->getStatusCode();
		$body = (string)$response->getBody();

		if ($status < 200 || $status >= 300) {
			$this->logger->error('[talk_transcripts] summary API (openai) returned {status}', [
				'status' => $status,
				'body' => substr($body, 0, 500),
			]);
			throw new RuntimeException("Summary API returned HTTP $status: " . substr($body, 0, 200));
		}

		$decoded = json_decode($body, true);
		$text = $decoded['choices'][0]['message']['content'] ?? '';
		if (!is_string($text)) {
			throw new RuntimeException('Summary API returned malformed response.');
		}
		return trim($text);
	}

	private function callAnthropic(string $endpoint, string $apiKey, string $model, string $system, string $user): string {
		if ($apiKey === '') {
			throw new RuntimeException('Anthropic provider requires an API key.');
		}

		$headers = [
			'Content-Type' => 'application/json',
			'x-api-key' => $apiKey,
			'anthropic-version' => '2023-06-01',
		];

		$payload = [
			'model' => $model,
			'max_tokens' => 2048,
			'system' => $system,
			'messages' => [
				['role' => 'user', 'content' => $user],
			],
		];

		$response = $this->http->post($endpoint, [
			'headers' => $headers,
			'body' => json_encode($payload, JSON_THROW_ON_ERROR),
			'timeout' => self::HTTP_TIMEOUT,
			'connect_timeout' => 15,
			'http_errors' => false,
		]);

		$status = $response->getStatusCode();
		$body = (string)$response->getBody();

		if ($status < 200 || $status >= 300) {
			$this->logger->error('[talk_transcripts] summary API (anthropic) returned {status}', [
				'status' => $status,
				'body' => substr($body, 0, 500),
			]);
			throw new RuntimeException("Summary API returned HTTP $status: " . substr($body, 0, 200));
		}

		$decoded = json_decode($body, true);
		$blocks = $decoded['content'] ?? [];
		if (!is_array($blocks)) {
			throw new RuntimeException('Summary API returned malformed response.');
		}
		$text = '';
		foreach ($blocks as $block) {
			if (($block['type'] ?? '') === 'text') {
				$text .= ($block['text'] ?? '');
			}
		}
		return trim($text);
	}

	private function truncate(string $transcript): string {
		if (strlen($transcript) <= self::MAX_TRANSCRIPT_CHARS) {
			return $transcript;
		}
		// Keep the first 70% and last 30% — leaders/closers carry the most signal.
		$keepStart = (int)(self::MAX_TRANSCRIPT_CHARS * 0.7);
		$keepEnd = self::MAX_TRANSCRIPT_CHARS - $keepStart - 100;
		return substr($transcript, 0, $keepStart)
			. "\n\n[... transcript truncated for summary; full text follows the summary in the .md file ...]\n\n"
			. substr($transcript, -$keepEnd);
	}
}
