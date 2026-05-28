<?php
/**
 * @var array{config: array<string,mixed>, defaults: array<string,mixed>} $_
 */

script('talk_transcripts', 'admin');
style('talk_transcripts', 'admin');

$cfg = $_['config'];
$def = $_['defaults'];
?>

<div class="section talk-transcripts-admin" id="talk_transcripts_admin">
	<h2 class="inlineblock"><?php p($l->t('Talk Transcripts')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('Automatically transcribe Nextcloud Talk recordings and optionally generate a summary with action items. Works with any OpenAI-compatible Whisper endpoint plus an LLM of your choice.')); ?>
	</p>

	<form id="talk_transcripts_form" data-csrf="<?php p(\OC::$server->getCsrfTokenManager()->getToken()->getEncryptedValue()); ?>">

		<!-- ============ General ============ -->
		<fieldset>
			<legend><?php p($l->t('General')); ?></legend>

			<p>
				<input type="checkbox" id="tt_enabled" name="enabled" class="checkbox"
					<?php if ($cfg['enabled']) { p('checked'); } ?> />
				<label for="tt_enabled"><?php p($l->t('Enable automatic transcription')); ?></label>
			</p>

			<p>
				<label for="tt_folder_pattern"><?php p($l->t('Recordings folder name')); ?></label><br/>
				<input type="text" id="tt_folder_pattern" name="folder_pattern"
					value="<?php p($cfg['folder_pattern']); ?>"
					placeholder="<?php p($l->t('Leave blank to use Talk defaults')); ?>"
					style="width: 360px;" />
				<br/><em class="settings-hint">
					<?php p($l->t('Only audio files whose path contains this fragment will be transcribed. Leave blank to auto-detect "Talk Recordings", "Talk/Recordings", and common translations.')); ?>
				</em>
			</p>
		</fieldset>

		<!-- ============ Whisper ============ -->
		<fieldset>
			<legend><?php p($l->t('Transcription (Whisper)')); ?></legend>

			<p>
				<label for="tt_whisper_endpoint"><?php p($l->t('Endpoint URL')); ?></label><br/>
				<input type="url" id="tt_whisper_endpoint" name="whisper_endpoint"
					value="<?php p($cfg['whisper_endpoint']); ?>"
					placeholder="<?php p($def['whisper_endpoint']); ?>"
					style="width: 480px;" />
				<br/><em class="settings-hint">
					<?php p($l->t('OpenAI Whisper, faster-whisper-server, whisper.cpp HTTP, or any OpenAI-compatible /v1/audio/transcriptions endpoint.')); ?>
				</em>
			</p>

			<p>
				<label for="tt_whisper_model"><?php p($l->t('Model')); ?></label><br/>
				<input type="text" id="tt_whisper_model" name="whisper_model"
					value="<?php p($cfg['whisper_model']); ?>"
					placeholder="<?php p($def['whisper_model']); ?>"
					style="width: 240px;" />
			</p>

			<p>
				<label for="tt_whisper_api_key"><?php p($l->t('API key')); ?></label><br/>
				<input type="password" id="tt_whisper_api_key" name="whisper_api_key"
					placeholder="<?php p($cfg['whisper_api_key_set'] ? $l->t('(set — leave blank to keep)') : $l->t('Paste API key')); ?>"
					autocomplete="off"
					style="width: 360px;" />
				<button type="button" id="tt_whisper_clear" class="button tertiary">
					<?php p($l->t('Clear key')); ?>
				</button>
				<br/><em class="settings-hint">
					<?php p($l->t('Stored encrypted in Nextcloud config. Leave blank if your endpoint does not require auth (self-hosted Whisper).')); ?>
				</em>
			</p>
		</fieldset>

		<!-- ============ Summary ============ -->
		<fieldset>
			<legend><?php p($l->t('Summary (optional)')); ?></legend>

			<p>
				<input type="checkbox" id="tt_summary_enabled" name="summary_enabled" class="checkbox"
					<?php if ($cfg['summary_enabled']) { p('checked'); } ?> />
				<label for="tt_summary_enabled"><?php p($l->t('Generate a summary alongside the full transcript')); ?></label>
			</p>

			<p>
				<label for="tt_summary_provider"><?php p($l->t('Provider')); ?></label><br/>
				<select id="tt_summary_provider" name="summary_provider">
					<option value="openai" <?php if ($cfg['summary_provider'] === 'openai') { p('selected'); } ?>>
						OpenAI / OpenAI-compatible
					</option>
					<option value="anthropic" <?php if ($cfg['summary_provider'] === 'anthropic') { p('selected'); } ?>>
						Anthropic (Claude)
					</option>
				</select>
			</p>

			<p>
				<label for="tt_summary_endpoint"><?php p($l->t('Endpoint URL')); ?></label><br/>
				<input type="url" id="tt_summary_endpoint" name="summary_endpoint"
					value="<?php p($cfg['summary_endpoint']); ?>"
					placeholder="<?php p($def['summary_endpoint']); ?>"
					style="width: 480px;" />
			</p>

			<p>
				<label for="tt_summary_model"><?php p($l->t('Model')); ?></label><br/>
				<input type="text" id="tt_summary_model" name="summary_model"
					value="<?php p($cfg['summary_model']); ?>"
					placeholder="<?php p($def['summary_model']); ?>"
					style="width: 240px;" />
			</p>

			<p>
				<label for="tt_summary_api_key"><?php p($l->t('API key')); ?></label><br/>
				<input type="password" id="tt_summary_api_key" name="summary_api_key"
					placeholder="<?php p($cfg['summary_api_key_set'] ? $l->t('(set — leave blank to keep)') : $l->t('Paste API key')); ?>"
					autocomplete="off"
					style="width: 360px;" />
				<button type="button" id="tt_summary_clear" class="button tertiary">
					<?php p($l->t('Clear key')); ?>
				</button>
			</p>

			<p>
				<label for="tt_summary_prompt"><?php p($l->t('System prompt')); ?></label><br/>
				<textarea id="tt_summary_prompt" name="summary_prompt"
					rows="10" style="width: 600px; font-family: monospace;"><?php p($cfg['summary_prompt']); ?></textarea>
				<br/><em class="settings-hint">
					<?php p($l->t('Sent as the system message to the LLM. The transcript is sent as the user message.')); ?>
				</em>
			</p>
		</fieldset>

		<p>
			<button type="submit" class="button primary">
				<?php p($l->t('Save')); ?>
			</button>
			<span id="tt_save_status" class="msg" style="margin-left: 1em;"></span>
		</p>
	</form>
</div>
