<fieldset class="group-picker">
    <legend>選手を4人選択 <span class="selected-count" data-selected-count aria-live="polite"></span></legend>
    <p class="muted">4人ちょうど選ぶと保存できます。選び直す場合は、チェックを外してから別の選手を選んでください。</p>
    <div class="grid">
        @foreach($players as $player)
            <label class="player-option"><input type="checkbox" name="players[]" value="{{ $player->id }}" @checked(in_array((string) $player->id, array_map('strval', $selectedIds), true))> <span>{{ $player->name }} <small>({{ $player->team_name }})</small></span></label>
        @endforeach
    </div>
</fieldset>
