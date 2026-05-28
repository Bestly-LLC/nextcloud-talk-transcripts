# Talk Transcripts

**Automatic transcription and summarization for Nextcloud Talk recordings.**

When a Nextcloud Talk call recording finishes and lands in a user's Files, this app picks it up, sends the audio to a Whisper-compatible transcription service, writes the transcript as a Markdown file alongside the recording, and (optionally) generates a one-page summary with action items via an LLM.

Works on any Nextcloud 28–31 install. Built by [Bestly LLC](https://bestly.tech).

---

## How it works

1. User records a call in Nextcloud Talk. Talk's recording bot drops the resulting `.ogg` (or `.opus`/`.webm`/`.mp3` depending on your setup) into the user's `Talk Recordings/` folder.
2. This app's listener picks up the `NodeCreatedEvent`, sees an audio file in a recordings folder, and queues a background job.
3. The background job (runs via standard Nextcloud cron) downloads the audio, posts it to the configured Whisper endpoint, gets back a transcript.
4. If summary is enabled, the transcript is sent to the configured LLM with your system prompt.
5. A `<recording-name>.transcript.md` file is written next to the audio, containing the summary on top and the full transcript below.

Idempotent: if the transcript already exists, the job skips. Re-running the job is safe.

---

## Provider matrix

This app speaks **OpenAI-compatible APIs**. That means it works with anything that implements the OpenAI Whisper or Chat Completions wire format.

| Use case | Transcription endpoint | Summary endpoint |
|---|---|---|
| Cloud, easiest | OpenAI `https://api.openai.com/v1/audio/transcriptions` | OpenAI `https://api.openai.com/v1/chat/completions` |
| Cloud, Claude summaries | OpenAI Whisper | Anthropic `https://api.anthropic.com/v1/messages` (set provider to `anthropic`) |
| Self-hosted Whisper, cloud LLM | [faster-whisper-server](https://github.com/fedirz/faster-whisper-server) on your network | OpenAI or Anthropic |
| Fully on-prem | faster-whisper-server | [Ollama](https://ollama.com/) `http://ollama:11434/v1/chat/completions` |
| Fully on-prem, GPU on the Nextcloud box | whisper.cpp HTTP server | Ollama on the same box |

Nothing leaves your server except API calls to the providers you configure. Credentials are stored encrypted in `oc_appconfig` using Nextcloud's `ICrypto`.

---

## Install

### From source (now)

```bash
cd /path/to/nextcloud/custom_apps/
git clone https://github.com/bestlytech/nextcloud-talk-transcripts talk_transcripts
cd talk_transcripts
composer install --no-dev --optimize-autoloader
sudo -u www-data php /path/to/nextcloud/occ app:enable talk_transcripts
```

### From the Nextcloud App Store (after submission)

Settings → Apps → search for "Talk Transcripts" → Install.

---

## Configure

Settings → Administration → **Talk Transcripts** in the sidebar.

You need at minimum:

1. **Enabled** — checkbox
2. **Whisper endpoint URL** — default is `https://api.openai.com/v1/audio/transcriptions`
3. **Whisper model** — default is `whisper-1`
4. **Whisper API key** — your OpenAI key (or empty for unauthenticated self-hosted endpoints)

Optional summary:

5. **Generate a summary** — checkbox
6. **Provider** — OpenAI-compatible or Anthropic
7. **Summary endpoint URL**, **model**, **API key**
8. **System prompt** — sane default is provided; customize to match your team's preferred output format

Save. Make a Talk recording. Wait for the next cron run (typically up to 5 minutes). The `<name>.transcript.md` file should appear next to your recording.

---

## Requirements

- Nextcloud 28 or newer
- PHP 8.1+
- A working Nextcloud cron (Settings → Basic settings → Background jobs → Cron). AJAX/Webcron will work but is slow.
- Nextcloud Talk (`spreed`) with recording configured (typically via the `spreed-recording-backend` container)
- Network egress from the Nextcloud server to your chosen Whisper/LLM provider

---

## Privacy

- Audio files are downloaded to the Nextcloud server's `sys_get_temp_dir()` temporarily, sent to the configured Whisper endpoint, then the temp file is unlinked. They are not copied to any Bestly-controlled infrastructure.
- API keys are stored encrypted (Nextcloud's `ICrypto`, server-key-based AES-256).
- No telemetry. No phone-home. No remote logging.

---

## Roadmap

- **V1.1** — per-user opt-out toggle
- **V1.2** — process arbitrary uploaded audio files in a user-configured folder (not just Talk recordings)
- **V1.3** — chunked transcription for files >25 MB (currently the OpenAI Whisper limit)
- **V2.0** — speaker diarization where the provider supports it; Nextcloud Assistant (`OCP\TaskProcessing`) integration so admins can route through any provider they've already configured for the Assistant framework

---

## Development

```bash
git clone https://github.com/bestlytech/nextcloud-talk-transcripts
cd nextcloud-talk-transcripts
composer install
make lint
make test
make package   # builds build/release/talk_transcripts-<version>.tar.gz
```

PRs welcome. Code style follows [Nextcloud Coding Standard](https://github.com/nextcloud/coding-standard).

---

## License

AGPL-3.0-or-later. See [LICENSE](./LICENSE).

The Nextcloud app store requires apps to be released under a license compatible with Nextcloud Server (AGPLv3). This app complies.
