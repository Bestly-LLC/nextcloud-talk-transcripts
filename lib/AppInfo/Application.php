<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Bestly LLC <hello@bestly.tech>
 *
 * @license AGPL-3.0-or-later
 */

namespace OCA\TalkTranscripts\AppInfo;

use OCA\TalkTranscripts\Listener\RecordingCreatedListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'talk_transcripts';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		// Trigger on both first creation and any subsequent overwrite —
		// Talk's recording bot writes the final file once, but the
		// recording uploader sometimes uses a temp-then-move pattern.
		$context->registerEventListener(NodeCreatedEvent::class, RecordingCreatedListener::class);
		$context->registerEventListener(NodeWrittenEvent::class, RecordingCreatedListener::class);
	}

	public function boot(IBootContext $context): void {
		// No-op. Background jobs are registered via info.xml.
	}
}
