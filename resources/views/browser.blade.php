@extends(config('media-manager.layout', 'uikittemplate::app'))

@section('content')
<div class="uk-grid-medium" uk-grid data-media-manager-browser="{{ $source }}">
    <aside class="uk-width-1-1 uk-width-1-4@m uk-width-1-5@l" aria-label="Navigazione Media Manager">
        <div class="uk-card uk-card-default uk-card-body uk-padding-small">
            <h1 class="uk-h4 uk-margin-small-bottom">Media Manager</h1>
            <ul class="uk-nav uk-nav-default">
                @foreach($views as $key => $view)
                    <li @class(['uk-active' => $source === $key])>
                        <a href="{{ route($view['route'], ['disk' => in_array($disk, $view['disks'], true) ? $disk : $view['disks'][0]]) }}" @if($source === $key) aria-current="page" @endif>{{ $view['label'] }}</a>
                    </li>
                @endforeach
                <li class="uk-nav-divider"></li>
                <li class="uk-nav-header">Archivi</li>
                @foreach($disks as $item)
                    <li @class(['uk-active' => $disk === $item])><a href="{{ route($views[$source]['route'], ['disk' => $item]) }}"><span uk-icon="database" aria-hidden="true"></span> {{ $item }}</a></li>
                @endforeach
                <li class="uk-nav-divider"></li>
                <li class="uk-nav-header">Cartelle</li>
                <li><a href="{{ $breadcrumbs[0]['url'] }}"><span uk-icon="home" aria-hidden="true"></span> Radice</a></li>
                @if(count($breadcrumbs) > 1)
                    <li><a href="{{ $breadcrumbs[count($breadcrumbs) - 2]['url'] }}"><span uk-icon="arrow-up" aria-hidden="true"></span> Cartella superiore</a></li>
                @endif
                @forelse($folders as $folder)
                    <li><a class="uk-text-break" href="{{ $folder['url'] }}"><span uk-icon="folder" aria-hidden="true"></span> {{ $folder['name'] }}</a></li>
                @empty
                    <li class="uk-text-muted uk-text-small uk-padding-small">Nessuna sottocartella visibile.</li>
                @endforelse
            </ul>
        </div>
    </aside>
    <main class="uk-width-expand@m uk-width-1-1" aria-label="Contenuto della cartella">
        <div class="uk-card uk-card-default uk-card-body uk-padding-small">
            <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap">
                <h2 class="uk-h3 uk-margin-remove">{{ $views[$source]['label'] }}</h2>
                <a class="uk-button uk-button-default uk-button-small" href="{{ request()->fullUrl() }}"><span uk-icon="refresh" aria-hidden="true"></span> Aggiorna</a>
            </div>
            <nav aria-label="Percorso della cartella" class="uk-margin-small-top">
                <ul class="uk-breadcrumb">
                    @foreach($breadcrumbs as $crumb)
                        <li>@if($loop->last)<span aria-current="location" class="uk-text-break">{{ $crumb['name'] }}</span>@else<a class="uk-text-break" href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>@endif</li>
                    @endforeach
                </ul>
            </nav>
            @if($source === 'filesystem')
                <p class="uk-text-small uk-text-muted"><span class="uk-label">A database</span> indica un percorso presente nel catalogo. I file non indicizzati restano senza evidenziazione.</p>
            @else
                <p class="uk-text-small uk-text-muted">Voci registrate nel catalogo, comprese quelle segnalate come mancanti. Lo stato si riferisce all’ultima verifica.</p>
            @endif
            @if(session('status'))<div class="uk-alert-success" uk-alert role="status">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="uk-alert-danger" uk-alert role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
            @include('media-manager::partials.helper-result')
            @include('media-manager::partials.entry-details')
            <div class="uk-overflow-auto">
                {!! $table->renderPortion() !!}
            </div>
        </div>
    </main>
</div>
@endsection
