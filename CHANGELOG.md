# Changelog

All notable changes to this project will be documented in this file. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] — 2026-05-28

Initial release.

### Added

- Listener on `NodeCreatedEvent` / `NodeWrittenEvent` that detects audio files written into Talk recording folders and queues a background transcription job.
- `ProcessRecordingJob` background job that downloads the audio, calls the configured Whisper-compatible endpoint, and writes a `<name>.transcript.md` sibling file. Idempotent — re-running on the same file is a no-op once a transcript exists.
- `TranscriptionService` — multipart POST to any OpenAI-compatible `/v1/audio/transcriptions` endpoint. Accepts both `response_format=text` (plain) and JSON responses.
- `SummaryService` with two provider shapes:
  - `openai` — POST `/v1/chat/completions` (works with OpenAI, Ollama, LM Studio, vLLM, OpenRouter, etc.)
  - `anthropic` — POST `/v1/messages` with `x-api-key` and `anthropic-version` headers.
- Admin settings page at Settings → Administration → Talk Transcripts. Stores Whisper + summary endpoint, model, and API key (encrypted via `ICrypto`). Customizable summary system prompt.
- AGPL-3.0-or-later licensed.
- Built-in folder-name pattern matching for `Talk Recordings/`, `Talk/Recordings/`, and the German/French/Spanish translated variants. Custom pattern overridable via admin settings.
