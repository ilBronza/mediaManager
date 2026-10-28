@if($result = session('file_helper_result'))
    <section class="{{ $result['matches'] ? 'uk-alert-success' : 'uk-alert-warning' }} uk-padding-small" aria-label="Risultato operazione sul file" role="status">
        <h3 class="uk-h5 uk-margin-small-bottom">{{ $result['path'] }}</h3>
        @if($result['operation'] === 'sha256')
            <p>{{ $result['matches'] ? 'Il contenuto corrisponde all’impronta registrata.' : 'Il contenuto non corrisponde all’impronta registrata. Il catalogo non è stato modificato.' }}</p>
            <dl class="uk-description-list">
                <dt>SHA-256 rilevato</dt><dd class="uk-text-break">{{ $result['sha256'] }}</dd>
                <dt>SHA-256 registrato</dt><dd class="uk-text-break">{{ $result['stored_sha256'] ?? 'Non disponibile' }}</dd>
            </dl>
        @else
            <p>{{ $result['operation'] === 'write-uuid' ? 'UUID scritto e verificato.' : ($result['matches'] ? 'L’UUID corrisponde al catalogo.' : 'UUID assente, diverso o non leggibile su questo filesystem.') }}</p>
            <dl class="uk-description-list">
                <dt>UUID sul file</dt><dd class="uk-text-break">{{ $result['uuid'] ?? 'Non rilevato' }}</dd>
                <dt>UUID del catalogo</dt><dd class="uk-text-break">{{ $result['entry_id'] }}</dd>
            </dl>
        @endif
    </section>
@endif
