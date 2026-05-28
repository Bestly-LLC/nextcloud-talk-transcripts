# Publishing Talk Transcripts to GitHub + cloud.bestly.tech

Two steps, run from your Mac terminal.

## 1. Create the GitHub repo and push

You have `gh` authed locally. Run:

```bash
cd "/Users/jared/Developer/bestlytech/nextcloud-talk-transcripts"

# Create the public repo on github.com/bestlytech and push main + tag
gh repo create bestlytech/nextcloud-talk-transcripts \
  --public \
  --source . \
  --description "Auto-transcribe Nextcloud Talk recordings. Universal — works with any OpenAI-compatible Whisper + LLM endpoint." \
  --homepage "https://bestly.tech" \
  --push

# Tag v0.1.0 to trigger the GitHub Actions release workflow
# (builds + attaches talk_transcripts-0.1.0.tar.gz to the GH Release)
git tag -a v0.1.0 -m "v0.1.0 — initial release"
git push origin v0.1.0
```

After the workflow finishes (~1 min), the release will live at
`https://github.com/bestlytech/nextcloud-talk-transcripts/releases/tag/v0.1.0`
with a downloadable tarball ready for `apps.nextcloud.com` upload (later).

## 2. Install on cloud.bestly.tech for dogfood

SSH to the Pi:

```bash
ssh pi
sudo docker exec -u www-data nextcloud-app bash -c '
  cd /var/www/html/custom_apps && \
  git clone https://github.com/bestlytech/nextcloud-talk-transcripts talk_transcripts && \
  cd talk_transcripts && \
  composer install --no-dev --optimize-autoloader 2>&1 | tail -3
'

sudo docker exec -u www-data nextcloud-app php /var/www/html/occ app:enable talk_transcripts
```

Then in the browser: **cloud.bestly.tech** → Settings → Administration → Talk Transcripts. Paste your OpenAI API key + Anthropic API key. Save.

## 3. Verify

Make a Talk call → record it → end the call → wait up to 5 minutes (next cron tick) → check your Talk Recordings folder. A `<name>.transcript.md` should appear with the summary on top and the full transcript below.

Tail the logs while you wait:

```bash
sudo docker exec nextcloud-app tail -f /var/www/html/data/nextcloud.log | grep -i 'talk_transcripts'
```

## Updating later

When you push a new tag (e.g. `v0.2.0`) the release workflow rebuilds the tarball automatically. To pull the new version onto the Pi:

```bash
sudo docker exec -u www-data nextcloud-app bash -c '
  cd /var/www/html/custom_apps/talk_transcripts && \
  git pull && \
  composer install --no-dev --optimize-autoloader
'
sudo docker exec -u www-data nextcloud-app php /var/www/html/occ upgrade
```
