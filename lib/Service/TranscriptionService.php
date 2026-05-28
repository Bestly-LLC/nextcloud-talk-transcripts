<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Bestly LLC <hello@bestly.tech>
 *
 * @license AGPL-3.0-or-later
 */

namespace OCA\TalkTranscripts\Service;

use OCP\Files\File;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Sends audio to an OpenAI-compatible Whisper endpoint and returns plain text.
 *
 * Works with:
 *   - OpenAI Whisper (https://api.openai.com/v1/audio/transcriptions)
 *   - faster-whisper-server (https://github.com/fedirz/faster-whisper-server)
 *   - whisper.cpp HTTP server
 *   - any other OpenAI-API-compatible endpoint
 */
class TranscriptionService {
	/** Whisper API hard limit. */
	private const MAX_BYTES = 25 * 1024 * 1024;

	/** HTTP timeout in seconds for the transcription call itself. */
	private const HTTP_TIMEOUT = 600;

	private IClient $http;

	public function __construct(
		private ConfigService $config,
		IClientService $clientService,
		private LoggerInterface $logger,
	) {
		$this->http = $clientService->newClient();
	}

	/**
	 * Transcribe the given audio file. Returns the transcript text (possibly empty
	 * if the audio was silent). Throws on hard errors.
	 */
	public function transcribe(File $audio): string {
		$endpoint = $this->config->getWhisperEndpoint();
		$model = $this->config->getWhisperModel();
		$apiKey = $this->config->getWhisperApiKey();

		if ($endpoint === '') {
			throw new RuntimeException('Whisper endpoint is not configured.');
		}

		$size = $audio->getSize();
		if ($size > self::MAX_BYTES) {
			throw new RuntimeException(sprintf(
				'Audio file is %d bytes, exceeds Whisper API limit of %d. (Future: chunked transcription not yet implemented.)',
				$size, self::MAX_BYTES
			));
		}

		// We need a real file on disk for the multipart upload. Nextcloud
		// storage may be remote (S3, etc.), so download to a temp file.
		$tmpPath = $this->materializeToTemp($audio);

		try {
			$headers = [];
			if ($apiKey !== '') {
				$headers['Authorization'] = 'Bearer ' . $apiKey;
			}

			$multipart = [
				[
					'name' => 'model',
					'contents' => $model,
				],
				[
					'name' => 'response_format',
					'contents' => 'text',
				],
				[
					'name' => 'file',
					'contents' => fopen($tmpPath, 'r'),
					'filename' => $audio->getName(),
				],
			];

			$response = $this->http->post($endpoint, [
				'headers' => $headers,
				'multipart' => $multipart,
				'timeout' => self::HTTP_TIMEOUT,
				'connect_timeout' => 15,
				'http_errors' => false,
			]);

			$status = $response->getStatusCode();
			$body = (string)$response->getBody();

			if ($status < 200 || $status >= 300) {
				$this->logger->error('[talk_transcripts] whisper API returned {status}', [
					'status' => $status,
					'body' => substr($body, 0, 500),
				]);
				throw new RuntimeException("Whisper API returned HTTP $status: " . substr($body, 0, 200));
			}

			// response_format=text returns plain text; some servers ignore the
			// param and return JSON. Handle both.
			$trimmed = trim($body);
			if ($trimmed === '') {
				return '';
			}
			if ($trimmed[0] === '{') {
				$decoded = json_decode($trimmed, true);
				if (is_array($decoded) && isset($decoded['text'])) {
					return (string)$decoded['text'];
				}
			}
			return $trimmed;
		} finally {
			@unlink($tmpPath);
		}
	}

	private function materializeToTemp(File $audio): string {
		$tmpPath = tempnam(sys_get_temp_dir(), 'talktx_');
		if ($tmpPath === false) {
			throw new RuntimeException('Could not allocate temp file for audio download.');
		}

		$src = $audio->fopen('rb');
		if ($src === false) {
			@unlink($tmpPath);
			throw new RuntimeException('Could not open audio file for reading.');
		}

		$dst = fopen($tmpPath, 'wb');
		if ($dst === false) {
			fclose($src);
			@unlink($tmpPath);
			throw new RuntimeException('Could not open temp file for writing.');
		}

		try {
			stream_copy_to_stream($src, $dst);
		} finally {
			fclose($src);
			fclose($dst);
		}

		return $tmpPath;
	}
}
