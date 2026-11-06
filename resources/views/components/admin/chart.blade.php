{{--
    Chart.js chart drawn by resources/js/charts.js.
    datasets: [['label' => 'Accuracy', 'data' => [..], 'color' => '#FF6B6B'], ...]
    format: 'percent' | 'seconds' | null
--}}
@props([
    'type' => 'bar',
    'labels' => [],
    'datasets' => [],
    'format' => null,
    'max' => null,
    'horizontal' => false,
    'height' => '16rem',
    'label' => 'Chart',
])

@php
    $config = array_filter([
        'type' => $type,
        'labels' => array_values($labels),
        'datasets' => $datasets,
        'format' => $format,
        'max' => $max,
        'horizontal' => $horizontal,
    ], fn ($value) => $value !== null);
@endphp

@if (count($labels) === 0)
    <p class="flex items-center justify-center rounded-xl bg-canvas text-sm text-muted" style="height: {{ $height }}">Not enough data yet</p>
@else
    <div wire:ignore wire:key="chart-{{ md5(json_encode($config)) }}" x-data="chart(@js($config))" style="height: {{ $height }}" {{ $attributes }}>
        <canvas x-ref="canvas" role="img" aria-label="{{ $label }}"></canvas>
    </div>
@endif
