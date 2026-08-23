{{ $title }}

{{ $greeting }}

{{ $intro }}

@if ($actionUrl)
{{ $actionLabel }}: {{ $actionUrl }}

@endif
@foreach ($details as $detail)
- {{ $detail }}
@endforeach

{{ $outro }}

{{ $securityNote }}
Hub Inovaforce
