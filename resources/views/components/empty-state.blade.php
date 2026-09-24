@props(['icon' => null, 'title', 'description' => null])

<div class="flex flex-col items-center justify-center text-center py-16 px-6">
    @if ($icon)
        <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 mb-4">
            {!! $icon !!}
        </div>
    @endif
    <p class="font-semibold text-gray-900">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 text-sm text-gray-500 max-w-sm">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-5">{{ $action }}</div>
    @endisset
</div>
