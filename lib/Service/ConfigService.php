<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Bestly LLC <hello@bestly.tech>
 *
 * @license AGPL-3.0-or-later
 */

namespace OCA\TalkTranscripts\Service;

use OCA\TalkTranscripts\AppInfo\Application;
use OCP\IConfig;
use OCP\Security\ICrypto;

class ConfigService {
	// App-level config keys (all stored in oc_appconfig).
	private const KEY_ENABLED = 'enabled';
	private const KEY_FOLDER_PATTERN = 'folder_pattern';

	private const KEY_WHISPER_ENDPOINT = 'whisper_endpoint';
	private const KEY_WHISPER_MODEL = 'whisper_model';
	private const KEY_WHISPER_API_KEY = 'whisper_api_key'; // encrypted at rest

	private const KEY_SUMMARY_ENABLED = 'summary_enabled';
	private const KEY_SUMMARY_PROVIDER = 'summary_provider'; // openai | anthropic
	private const KEY_SUMMARY_ENDPOINT = 'summary_endpoint';
	private const KEY_SUMMARY_MODEL = 'summary_model';
	private const KEY_SUMMARY_API_KEY = 'summary_api_key'; // encrypted at rest
	private const KEY_SUMMARY_PROMPT = 'summary_prompt';

	public const DEFAULT_WHISPER_ENDPOINT = 'https://api.openai.com/v1/audio/transcriptions';
	public const DEFAULT_WHISPER_MODEL = 'whisper-1';

	public const DEFAULT_SUMMARY_PROVIDER = 'openai';
	public const DEFAULT_SUMMARY_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
	public const DEFAULT_SUMMARY_MODEL = 'gpt-4o-mini';

	public const DEFAULT_SUMMARY_PROMPT = <<<PROMPT
You are summarizing a meeting transcript. Produce a concise summary in Markdown with these sections:

**TL;DR** — 2-3 sentences.

**Decisions made** — bullets, only concrete decisions (not topics discussed).

**Action items** — bullets in the form "Owner: action (due date if mentioned)". If no owner was named, write "Unassigned".

**Open questions** — bullets, things left unresolved.

Be terse. Skip pleasantries. If a section has nothing, omit it entirely.
PROMPT;

	public function __construct(
		private IConfig $config,
		private ICrypto $crypto,
	) {
	}

	// ---------------------------------------------------------------------
	// General
	// ---------------------------------------------------------------------

	public function isEnabled(): bool {
		return $this->getAppValue(self::KEY_ENABLED, '1') === '1';
	}

	public function setEnabled(bool $enabled): void {
		$this->setAppValue(self::KEY_ENABLED, $enabled ? '1' : '0');
	}

	public function getRecordingsFolderPattern(): string {
		// Empty string => use the listener's built-in default patterns.
		return $this->getAppValue(self::KEY_FOLDER_PATTERN, '');
	}

	public function setRecordingsFolderPattern(string $pattern): void {
		$this->setAppValue(self::KEY_FOLDER_PATTERN, $pattern);
	}

	// ---------------------------------------------------------------------
	// Whisper / transcription
	// ---------------------------------------------------------------------

	public function getWhisperEndpoint(): string {
		return $this->getAppValue(self::KEY_WHISPER_ENDPOINT, self::DEFAULT_WHISPER_ENDPOINT);
	}

	public function setWhisperEndpoint(string $endpoint): void {
		$this->setAppValue(self::KEY_WHISPER_ENDPOINT, $endpoint);
	}

	public function getWhisperModel(): string {
		return $this->getAppValue(self::KEY_WHISPER_MODEL, self::DEFAULT_WHISPER_MODEL);
	}

	public function setWhisperModel(string $model): void {
		$this->setAppValue(self::KEY_WHISPER_MODEL, $model);
	}

	public function getWhisperApiKey(): string {
		return $this->getEncryptedAppValue(self::KEY_WHISPER_API_KEY);
	}

	public function setWhisperApiKey(string $key): void {
		$this->setEncryptedAppValue(self::KEY_WHISPER_API_KEY, $key);
	}

	// ---------------------------------------------------------------------
	// Summary / LLM
	// ---------------------------------------------------------------------

	public function isSummaryEnabled(): bool {
		return $this->getAppValue(self::KEY_SUMMARY_ENABLED, '1') === '1';
	}

	public function setSummaryEnabled(bool $enabled): void {
		$this->setAppValue(self::KEY_SUMMARY_ENABLED, $enabled ? '1' : '0');
	}

	public function getSummaryProvider(): string {
		return $this->getAppValue(self::KEY_SUMMARY_PROVIDER, self::DEFAULT_SUMMARY_PROVIDER);
	}

	public function setSummaryProvider(string $provider): void {
		$normalized = strtolower(trim($provider));
		if (!in_array($normalized, ['openai', 'anthropic'], true)) {
			$normalized = self::DEFAULT_SUMMARY_PROVIDER;
		}
		$this->setAppValue(self::KEY_SUMMARY_PROVIDER, $normalized);
	}

	public function getSummaryEndpoint(): string {
		return $this->getAppValue(self::KEY_SUMMARY_ENDPOINT, self::DEFAULT_SUMMARY_ENDPOINT);
	}

	public function setSummaryEndpoint(string $endpoint): void {
		$this->setAppValue(self::KEY_SUMMARY_ENDPOINT, $endpoint);
	}

	public function getSummaryModel(): string {
		return $this->getAppValue(self::KEY_SUMMARY_MODEL, self::DEFAULT_SUMMARY_MODEL);
	}

	public function setSummaryModel(string $model): void {
		$this->setAppValue(self::KEY_SUMMARY_MODEL, $model);
	}

	public function getSummaryApiKey(): string {
		return $this->getEncryptedAppValue(self::KEY_SUMMARY_API_KEY);
	}

	public function setSummaryApiKey(string $key): void {
		$this->setEncryptedAppValue(self::KEY_SUMMARY_API_KEY, $key);
	}

	public function getSummaryPrompt(): string {
		return $this->getAppValue(self::KEY_SUMMARY_PROMPT, self::DEFAULT_SUMMARY_PROMPT);
	}

	public function setSummaryPrompt(string $prompt): void {
		$this->setAppValue(self::KEY_SUMMARY_PROMPT, $prompt);
	}

	/**
	 * Export every setting for the admin UI. API keys are masked.
	 *
	 * @return array<string,mixed>
	 */
	public function exportForAdminUi(): array {
		return [
			'enabled' => $this->isEnabled(),
			'folder_pattern' => $this->getRecordingsFolderPattern(),
			'whisper_endpoint' => $this->getWhisperEndpoint(),
			'whisper_model' => $this->getWhisperModel(),
			'whisper_api_key_set' => $this->getWhisperApiKey() !== '',
			'summary_enabled' => $this->isSummaryEnabled(),
			'summary_provider' => $this->getSummaryProvider(),
			'summary_endpoint' => $this->getSummaryEndpoint(),
			'summary_model' => $this->getSummaryModel(),
			'summary_api_key_set' => $this->getSummaryApiKey() !== '',
			'summary_prompt' => $this->getSummaryPrompt(),
		];
	}

	// ---------------------------------------------------------------------
	// Internal storage helpers
	// ---------------------------------------------------------------------

	private function getAppValue(string $key, string $default): string {
		return $this->config->getAppValue(Application::APP_ID, $key, $default);
	}

	private function setAppValue(string $key, string $value): void {
		$this->config->setAppValue(Application::APP_ID, $key, $value);
	}

	private function getEncryptedAppValue(string $key): string {
		$raw = $this->getAppValue($key, '');
		if ($raw === '') {
			return '';
		}
		try {
			return $this->crypto->decrypt($raw);
		} catch (\Throwable $e) {
			// Corrupted or pre-encryption raw value — return empty so caller
			// re-prompts for the key rather than crashing.
			return '';
		}
	}

	private function setEncryptedAppValue(string $key, string $value): void {
		if ($value === '') {
			$this->setAppValue($key, '');
			return;
		}
		$this->setAppValue($key, $this->crypto->encrypt($value));
	}
}
