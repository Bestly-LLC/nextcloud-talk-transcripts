<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Bestly LLC <hello@bestly.tech>
 *
 * @license AGPL-3.0-or-later
 */

namespace OCA\TalkTranscripts\Settings;

use OCA\TalkTranscripts\AppInfo\Application;
use OCA\TalkTranscripts\Service\ConfigService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class Admin implements ISettings {
	public function __construct(
		private ConfigService $config,
	) {
	}

	public function getForm(): TemplateResponse {
		return new TemplateResponse(
			Application::APP_ID,
			'admin',
			[
				'config' => $this->config->exportForAdminUi(),
				'defaults' => [
					'whisper_endpoint' => ConfigService::DEFAULT_WHISPER_ENDPOINT,
					'whisper_model' => ConfigService::DEFAULT_WHISPER_MODEL,
					'summary_provider' => ConfigService::DEFAULT_SUMMARY_PROVIDER,
					'summary_endpoint' => ConfigService::DEFAULT_SUMMARY_ENDPOINT,
					'summary_model' => ConfigService::DEFAULT_SUMMARY_MODEL,
					'summary_prompt' => ConfigService::DEFAULT_SUMMARY_PROMPT,
				],
			],
			''
		);
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 50;
	}
}
