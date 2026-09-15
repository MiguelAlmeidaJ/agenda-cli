(() => {
  document.querySelectorAll('input[name="postal_code"], [data-postal-code]').forEach((input) => {
    input.maxLength = 9;
    input.autocomplete = 'postal-code';
    input.setAttribute('pattern', '\\d{5}-\\d{3}');
    input.setAttribute('title', 'Informe um CEP com 8 dígitos, no formato 00000-000.');

    const formatPostalCode = () => {
      const digits = String(input.value || '').replace(/\D/g, '').slice(0, 8);
      input.value = digits.length > 5
        ? `${digits.slice(0, 5)}-${digits.slice(5)}`
        : digits;

      if (digits.length === 0 || digits.length === 8) {
        input.setCustomValidity('');
      } else {
        input.setCustomValidity('Informe um CEP com 8 dígitos. Exemplo: 36660-000.');
      }
    };

    input.addEventListener('input', formatPostalCode);
    input.addEventListener('blur', formatPostalCode);
    formatPostalCode();
  });

  if (!window.L) return;

  const config = window.AgendaCliMapConfig || {};
  const tileUrl = config.tileUrl || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
  const attribution = config.attribution || '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

  document.querySelectorAll('[data-establishment-map]').forEach((element) => {
    if (element.dataset.mapInitialized === '1') return;

    const latitude = Number(element.dataset.latitude);
    const longitude = Number(element.dataset.longitude);
    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;

    element.dataset.mapInitialized = '1';

    const zoom = Number(element.dataset.zoom || 16);
    const name = element.dataset.name || 'Estabelecimento';
    const map = L.map(element, {
      scrollWheelZoom: false,
      zoomControl: true,
      attributionControl: true,
    }).setView([latitude, longitude], zoom);

    L.tileLayer(tileUrl, {
      minZoom: 2,
      maxZoom: 19,
      attribution,
      keepBuffer: 3,
      updateWhenIdle: false,
    }).addTo(map);

    L.marker([latitude, longitude]).addTo(map).bindPopup(name);

    const refreshSize = () => {
      if (!element.isConnected || element.clientWidth === 0 || element.clientHeight === 0) return;
      map.invalidateSize({animate: false, pan: false});
    };

    requestAnimationFrame(() => requestAnimationFrame(refreshSize));
    setTimeout(refreshSize, 120);
    setTimeout(refreshSize, 400);

    if ('ResizeObserver' in window) {
      const observer = new ResizeObserver(() => refreshSize());
      observer.observe(element);
    }

    window.addEventListener('load', refreshSize, {once: true});
    window.addEventListener('resize', refreshSize, {passive: true});
  });
})();
