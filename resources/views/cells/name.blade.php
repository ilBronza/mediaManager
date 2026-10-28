@if($url)<a class="uk-text-break" href="{{ $url }}"><span uk-icon="folder" aria-hidden="true"></span> {{ $item['name'] }}</a>@else<span class="uk-text-break">{{ $item['name'] }}</span>@endif
