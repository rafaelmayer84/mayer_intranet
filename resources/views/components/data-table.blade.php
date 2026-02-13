{{-- resources/views/components/data-table.blade.php --}}
@props([
    'headers' => [],
    'class' => '',
    'id' => null,
    'striped' => true,
    'hoverable' => true,
])

<div class="ma-card overflow-hidden {{ $class }}">
    <div class="overflow-x-auto">
        <table @if($id) id="{{ $id }}" @endif class="ma-table">
            @if(count($headers) > 0)
                <thead>
                    <tr>
                        @foreach($headers as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
            @endif
            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    </div>
</div>
