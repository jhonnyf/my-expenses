@php
    $selectedUf = $selectedUf ?? null;
@endphp
@foreach(config('brazilian-states') as $uf => $label)
    <option value="{{ $uf }}" @selected($selectedUf === $uf)>{{ $label }} ({{ $uf }})</option>
@endforeach
