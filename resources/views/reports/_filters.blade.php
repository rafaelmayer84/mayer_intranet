@if(isset($filters) && count($filters) > 0)
<form method="GET" action="" class="bg-gray-50 rounded-xl border border-gray-200 p-4 mb-5">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3 items-end">
        @foreach($filters as $filter)
        <div>
            <label class="block text-xs font-bold text-gray-500 mb-1 font-mono uppercase tracking-wider">{{ $filter['label'] }}</label>
            @if($filter['type'] === 'select')
            <select name="{{ $filter['name'] }}" class="w-full rounded-lg border-gray-300 text-sm font-mono focus:ring-[#385776] focus:border-[#385776]">
                <option value="">Todos</option>
                @foreach($filter['options'] as $val => $lbl)
                <option value="{{ $val }}" {{ request($filter['name']) == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
            @elseif($filter['type'] === 'month')
            <input type="month" name="{{ $filter['name'] }}" value="{{ request($filter['name']) }}" class="w-full rounded-lg border-gray-300 text-sm font-mono focus:ring-[#385776] focus:border-[#385776]">
            @elseif($filter['type'] === 'text')
            <input type="text" name="{{ $filter['name'] }}" value="{{ request($filter['name']) }}" placeholder="{{ $filter['placeholder'] ?? '' }}" class="w-full rounded-lg border-gray-300 text-sm font-mono focus:ring-[#385776] focus:border-[#385776]">
            @endif
        </div>
        @endforeach
        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 bg-[#385776] hover:bg-[#1B334A] text-white text-xs font-bold font-mono rounded-lg transition-colors tracking-wider">FILTRAR</button>
            <a href="{{ url()->current() }}" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs font-mono rounded-lg transition-colors">LIMPAR</a>
        </div>
    </div>
</form>
@endif
