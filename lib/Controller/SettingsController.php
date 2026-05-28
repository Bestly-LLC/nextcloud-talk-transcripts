<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Bestly LLC <hello@bestly.tech>
 *
 * @license AGPL-3.0-or-later
 */

namespace OCA\TalkTranscripts\Controller;

use OCA\TalkTranscripts\Service\ConfigService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class SettingsController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private ConfigService $config,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Save admin settings. Required: admin-authenticated CSRF-checked POST.
	 *
	 * @param array<string,mixed> $config
	 * @return DataResponse<Http::STATUS_OK, array{config: array<string,mixed>}, array{}>
	 */
	public function save(array $config): DataResponse {
		if (array_key_exists('enabled', $config)) {
			$this->config->setEnabled((bool)$config['enabled']);
		}
		if (array_key_exists('folder_pattern', $config)) {
			$this->config->setRecordingsFolderPattern((string)$config['folder_pattern']);
		}

		if (array_key_exists('whisper_endpoint', $config)) {
			$this->config->setWhisperEndpoint((string)$config['whisper_endpoint']);
		}
		if (array_key_exists('whisper_model', $config)) {
			$this->config->setWhisperModel((string)$config['whisper_model']);
		}
		// Only update API key if a non-empty string was supplied — empty means "leave as-is".
		if (!empty($config['whisper_api_key'])) {
			$this->config->setWhisperApiKey((string)$config['whisper_api_key']);
		}
		if (array_key_exists('whisper_api_key_clear', $config) && $config['whisper_api_key_clear']) {
			$this->config->setWhisperApiKey('');
		}

		if (array_key_exists('summary_enabled', $config)) {
			$this->config->setSummaryEnabled((bool)$config['summary_enabled']);
		}
		if (array_key_exists('summary_provider', $config)) {
			$this->config->setSummaryProvider((string)$config['summary_provider']);
		}
		if (array_key_exists('summary_endpoint', $config)) {
			$this->config->setSummaryEndpoint((string)$config['summary_endpoint']);
		}
		if (array_key_exists('summary_model', $config)) {
			$this->config->setSummaryModel((string)$config['summary_model']);
		}
		if (!empty($config['summary_api_key'])) {
			$this->config->setSummaryApiKey((string)$config['summary_api_key']);
		}
		if (array_key_exists('summary_api_key_clear', $config) && $config['summary_api_key_clear']) {
			$this->config->setSummaryApiKey('');
		}
		if (array_key_exists('summary_prompt', $config)) {
			$this->config->setSummaryPrompt((string)$config['summary_prompt']);
		}

		return new DataResponse(['config' => $this->config->exportForAdminUi()]);
	}
}
