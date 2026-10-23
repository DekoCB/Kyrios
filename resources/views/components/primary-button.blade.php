@props(['type' => 'submit'])

<button type="{{ $type }}" {{ $attributes->merge(['class' => 'inline-flex items-center px-4 py-2 bg-[#44413A] border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-[#094A73] focus:bg-[#094A73] active:bg-[#44413A] focus:outline-none focus:ring-2 focus:ring-[#4F758C] focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>