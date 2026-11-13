@php $fotoBase = \App\Support\FotoProducto::base($codigo); @endphp
<a href="{{ $fotoBase }}.jpg" target="_blank" rel="noopener" class="cat-foto-link" onclick="event.stopPropagation()">
    <img src="{{ $fotoBase }}.jpg" alt="{{ $alt ?? '' }}" class="{{ $clase ?? 'cat-foto' }}" loading="lazy"
         onerror="if (this.dataset.png !== '1') { this.dataset.png = '1'; this.src = '{{ $fotoBase }}.png'; this.parentNode.href = this.src; } else { this.onerror = null; this.src = '{{ \App\Support\FotoProducto::SIN_IMAGEN }}'; this.parentNode.removeAttribute('href'); }">
</a>
