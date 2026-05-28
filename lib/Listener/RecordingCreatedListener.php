<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Bestly LLC <hello@bestly.tech>
 *
 * @license AGPL-3.0-or-later
 */

namespace OCA\TalkTranscripts\Listener;

use OCA\TalkTranscripts\BackgroundJob\ProcessRecordingJob;
use OCA\TalkTranscripts\Service\ConfigService;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\File;
use OCP\Files\Node;
use Psr\Log\LoggerInterface;

/**
 * @implements IEventListener<NodeCreatedEvent|NodeWrittenEvent>
 */
class RecordingCreatedListener implements IEventListener {
	/**
	 * Audio mimetypes Whisper can transcribe. Talk records to .ogg/.opus
	 * by default; we accept the broader Whisper-supported set so other
	 * recording flows (uploads, manual conversions) work too.
	 */
	private const AUDIO_MIMETYPES = [
		'audio/ogg',
		'audio/opus',
		'audio/webm',
		'audio/mpeg',
		'audio/mp4',
		'audio/x-m4a',
		'audio/wav',
		'audio/x-wav',
		'audio/flac',
		'audio/x-flac',
	];

	/**
	 * Recording-folder name patterns. Talk by default uses "Talk Recordings"
	 * (translatable per-language). Admin can override via config; this is
	 * the safety net for English-default installs.
	 */
	private const DEFAULT_FOLDER_PATTERNS = [
		'/Talk Recordings/',
		'/Talk/Recordings/',
		'/Talk-Aufzeichnungen/',     // de
		'/Enregistrements Talk/',    // fr
		'/Grabaciones de Talk/',     // es
	];

	public function __construct(
		private IJobList $jobList,
		private ConfigService $config,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof NodeCreatedEvent) && !($event instanceof NodeWrittenEvent)) {
			return;
		}

		$node = $event->getNode();
		if (!($node instanceof File)) {
			return;
		}

		if (!$this->shouldTranscribe($node)) {
			return;
		}

		// Hand off to background job — transcription can take 30s+.
		$this->jobList->add(ProcessRecordingJob::class, [
			'fileId' => $node->getId(),
			'userId' => $this->extractOwnerId($node),
			'queuedAt' => time(),
		]);

		$this->logger->info('[talk_transcripts] queued file {fileId} for transcription', [
			'fileId' => $node->getId(),
			'path' => $node->getPath(),
		]);
	}

	private function shouldTranscribe(File $node): bool {
		if (!$this->config->isEnabled()) {
			return false;
		}

		// Mimetype gate
		$mimetype = strtolower($node->getMimeType());
		if (!in_array($mimetype, self::AUDIO_MIMETYPES, true)) {
			return false;
		}

		// Path gate — must live under a recordings folder
		$path = $node->getPath();
		$customPattern = $this->config->getRecordingsFolderPattern();
		$patterns = $customPattern !== '' ? [$customPattern] : self::DEFAULT_FOLDER_PATTERNS;

		foreach ($patterns as $needle) {
			if (stripos($path, $needle) !== false) {
				return true;
			}
		}

		return false;
	}

	private function extractOwnerId(Node $node): ?string {
		try {
			$owner = $node->getOwner();
			return $owner?->getUID();
		} catch (\Throwable $e) {
			$this->logger->warning('[talk_transcripts] could not determine owner for node', [
				'exception' => $e,
			]);
			return null;
		}
	}
}
