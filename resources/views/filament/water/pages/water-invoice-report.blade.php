<x-filament-panels::page>
    {{-- Page content --}}
    {{ $this->form }}
     <div style="width:100%;display:flex;justify-content:flex-end;margin-top: -25px;">
        <span style="font-weight:bold;">
            মোট আদায়: {{ $this->type==='water' || $this->type==='w_previous' ? $this->getTableQuery()->sum('w_total_amount') : $this->getTableQuery()->sum('s_total_amount')}} 
        </span>
    </div>
    {{ $this->table }}
</x-filament-panels::page>
