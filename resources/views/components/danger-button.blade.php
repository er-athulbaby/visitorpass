<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn bg-error text-white shadow-sm hover:brightness-95 active:brightness-90']) }}>
    {{ $slot }}
</button>
