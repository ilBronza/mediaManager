@if($entry && !$entry->isDirectory())
    <div class="uk-flex uk-flex-middle">
        <a class="uk-icon-button uk-margin-small-right" uk-icon="info" title="Informazioni" aria-label="Informazioni: {{ $entry->name }}" href="{{ route('media-manager.index', ['disk' => $entry->disk, 'folder' => $entry->parent_id, 'entry' => $entry->id]) }}"></a>
        @foreach($helpers as $name => $helper)
            @if(!$helper['writes'] || Gate::allows('media-manager.manage', [$entry->disk, $entry]))
                <form class="uk-margin-remove uk-display-inline-block" method="post" action="{{ route('media-manager.helpers', ['entry' => $entry, 'helper' => $name]) }}">
                    @csrf
                    <button type="submit" class="uk-icon-button uk-margin-small-right" uk-icon="{{ $helper['icon'] }}" title="{{ $helper['label'] }}" aria-label="{{ $helper['label'] }}: {{ $entry->name }}"></button>
                </form>
            @endif
        @endforeach
    </div>
@endif
