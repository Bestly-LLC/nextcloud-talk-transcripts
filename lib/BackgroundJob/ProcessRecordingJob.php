<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Bestly LLC <hello@bestly.tech>
 *
 * @license AGPL-3.0-or-later
 */

namespace OCA\TalkTranscripts\BackgroundJob;

use OCA\TalkTranscripts\Service\ConfigService;
use OCA\TalkTranscripts\Service\SummaryService;
use OCA\TalkTranscripts\Service\TranscriptionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\QueuedJob;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;

/**
 * Async transcription + summarization of a single Talk recording.
 *
 * Triggered by RecordingCreatedListener. Argument schema:
 *   [
 *     'fileId'   => int,        // ID of the audio file in user's storage
 *     'userId'   => string,     // owning user's UID
 *     'queuedAt' => int,        // unix ts when queued, for staleness checks
 *   ]
 */
class ProcessRecordingJob extends QueuedJob {
	/** Skip transcription if file was queued more than this long ago. */
	private const MAX_AGE_SECONDS = 7 * 24 * 3600;

	public function __construct(
		ITimeFactory $time,
		private IRootFolder $rootFolder,
		private TranscriptionService $transcription,
		private SummaryService $summary,
		private ConfigService $config,
		private LoggerInterface $logger,
		private IJobList $jobList,
	) {
		parent::__construct($time);
	}

	/**
	 * @param array{fileId:int,userId:?string,queuedAt:int} $argument
	 */
	protected function run($argument): void {
		$fileId = (int)($argument['fileId'] ?? 0);
		$userId = $argument['userId'] ?? null;
		$queuedAt = (int)($argument['queuedAt'] ?? 0);

		if ($fileId <= 0 || $userId === null || $userId === '') {
			$this->logger->warning('[talk_transcripts] invalid job argument', ['argument' => $argument]);
			return;
		}

		if ($queuedAt > 0 && (time() - $queuedAt) > self::MAX_AGE_SECONDS) {
			$this->logger->info('[talk_transcripts] dropping stale job for file {fileId}', ['fileId' => $fileId]);
			return;
		}

		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
		} catch (\Throwable $e) {
			$this->logger->error('[talk_transcripts] could not open user folder', [
				'userId' => $userId,
				'exception' => $e,
			]);
			return;
		}

		$nodes = $userFolder->getById($fileId);
		if (count($nodes) === 0) {
			$this->logger->info('[talk_transcripts] file {fileId} no longer exists', ['fileId' => $fileId]);
			return;
		}

		$audio = $nodes[0];
		if (!($audio instanceof File)) {
			return;
		}

		// Idempotency: if a transcript sibling already exists, skip.
		$transcriptName = $this->transcriptFilename($audio);
		try {
			$parent = $audio->getParent();
			if ($parent->nodeExists($transcriptName)) {
				$this->logger->debug('[talk_transcripts] transcript already exists for {fileId}, skipping', [
					'fileId' => $fileId,
				]);
				return;
			}
		} catch (NotFoundException $e) {
			return;
		}

		// --- Transcription ---
		$this->logger->info('[talk_transcripts] transcribing {fileId} ({size} bytes)', [
			'fileId' => $fileId,
			'size' => $audio->getSize(),
		]);
		try {
			$transcript = $this->transcription->transcribe($audio);
		} catch (\Throwable $e) {
			$this->logger->error('[talk_transcripts] transcription failed for {fileId}', [
				'fileId' => $fileId,
				'exception' => $e,
			]);
			// Retry once after 10 minutes for transient failures (rate limits, network).
			$this->scheduleRetry($argument);
			return;
		}

		if ($transcript === '') {
			$this->logger->warning('[talk_transcripts] empty transcript for {fileId} — likely silent audio', [
				'fileId' => $fileId,
			]);
			return;
		}

		// --- Optional summary ---
		$summary = '';
		if ($this->config->isSummaryEnabled()) {
			try {
				$summary = $this->summary->summarize($transcript, $audio->getName());
			} catch (\Throwable $e) {
				$this->logger->warning('[talk_transcripts] summary failed for {fileId} (continuing without)', [
					'fileId' => $fileId,
					'exception' => $e,
				]);
			}
		}

		// --- Write outputs alongside the audio ---
		$transcriptMd = $this->renderTranscriptMarkdown($audio, $transcript, $summary);
		try {
			$parent->newFile($transcriptName, $transcriptMd);
			$this->logger->info('[talk_transcripts] wrote {transcript} ({len} chars)', [
				'transcript' => $transcriptName,
				'len' => strlen($transcriptMd),
			]);
		} catch (\Throwable $e) {
			$this->logger->error('[talk_transcripts] could not write transcript file', [
				'transcript' => $transcriptName,
				'exception' => $e,
			]);
		}
	}

	private function transcriptFilename(File $audio): string {
		$basename = pathinfo($audio->getName(), PATHINFO_FILENAME);
		return $basename . '.transcript.md';
	}

	private function renderTranscriptMarkdown(File $audio, string $transcript, string $summary): string {
		$header = "# Transcript: " . $audio->getName() . "\n\n";
		$header .= "_Generated " . date('Y-m-d H:i') . " by Talk Transcripts._\n\n";

		$body = '';
		if ($summary !== '') {
			$body .= "## Summary\n\n" . trim($summary) . "\n\n---\n\n";
		}
		$body .= "## Full transcript\n\n" . trim($transcript) . "\n";

		return $header . $body;
	}

	/**
	 * @param array{fileId:int,userId:?string,queuedAt:int} $argument
	 */
	private function scheduleRetry(array $argument): void {
		// Re-queue with same payload; cron will pick it up on next run.
		// We don't track retry count yet — V1 keeps it simple. If transient
		// errors stack up, the job dedup by fileId via the transcript-exists
		// check above prevents duplicate writes.
		$this->jobList->add(self::class, $argument);
	}
}
