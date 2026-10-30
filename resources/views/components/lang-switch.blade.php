{{--
    Toggle bahasa ID / EN situs customer (route "lang.switch": session + cookie 1 tahun, kembali ke halaman yang sama).
    variant="dark"  → di atas latar terakota (navbar / hero), variant="light" → di atas latar terang (form login, menu HP).
    Style inline supaya tidak bergantung pada kelas Tailwind yang belum ter-build.
--}}
@props(['variant' => 'dark'])

@php
    $current = app()->getLocale();
    $dark = $variant === 'dark';
    $wrap = $dark
        ? 'border:1px solid rgba(247,240,219,0.25); background:#511D18;'
        : 'border:1px solid #E6DAC0; background:#FFFFFF;';
    $active = $dark ? 'background:#F7F0DB; color:#662721;' : 'background:#662721; color:#F7F0DB;';
    $idle = $dark ? 'color:rgba(247,240,219,0.75);' : 'color:#7A5A52;';
@endphp

<div {{ $attributes->merge(['class' => 'c61-lang-switch']) }} role="group" aria-label="{{ __('common.language') }}"
    style="display:inline-flex; align-items:center; border-radius:999px; padding:2px; font-size:11px; font-weight:700; line-height:1; {{ $wrap }}">
    @foreach (['id' => 'ID', 'en' => 'EN'] as $code => $label)
        <a href="{{ route('lang.switch', $code) }}" hreflang="{{ $code }}" @if ($current === $code) aria-current="true" @endif
            style="padding:6px 10px; border-radius:999px; text-decoration:none; {{ $current === $code ? $active : $idle }}">{{ $label }}</a>
    @endforeach
</div>
