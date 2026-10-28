@if($details ?? null)
    <section class="uk-card uk-card-default uk-card-body uk-padding-small uk-margin" aria-label="Informazioni sul file">
        <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap">
            <h3 class="uk-h4 uk-margin-remove uk-text-break">{{ $details['name'] }}</h3>
            <a class="uk-button uk-button-text" href="{{ $breadcrumbs[count($breadcrumbs) - 1]['url'] }}">Chiudi informazioni</a>
        </div>
        <dl class="uk-description-list">
            <dt>Archivio e percorso registrato</dt><dd class="uk-text-break">{{ $details['disk'] }} / {{ $details['path'] }}</dd>
            <dt>UUID del catalogo</dt><dd class="uk-text-break">{{ $details['id'] }}</dd>
            <dt>SHA-256 registrato</dt><dd class="uk-text-break">{{ $details['sha256'] ?? 'Non disponibile' }}</dd>
            <dt>Dimensione registrata</dt><dd>{{ $details['size'] !== null ? number_format($details['size'], 0, ',', '.').' byte' : 'Non disponibile' }}</dd>
            @if($details['media_id'])<dt>Media associato</dt><dd>{{ $details['media_id'] }}</dd>@endif
        </dl>
        <h4 class="uk-h5">Metadati</h4>
        <pre>{{ json_encode($details['metadata'] ?? new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        <h4 class="uk-h5">Collegamenti registrati</h4>
        <ul class="uk-list uk-list-divider">
            @forelse($details['links'] as $link)
                <li>{{ $link['type'] }} · {{ $link['id'] }} · {{ $link['role'] }}</li>
            @empty
                <li class="uk-text-muted">Nessun collegamento registrato.</li>
            @endforelse
        </ul>
        @foreach($detailContexts as $context)<p><a href="{{ $context['url'] }}">Dossier: {{ $context['label'] }}</a></p>@endforeach
    </section>
@endif
