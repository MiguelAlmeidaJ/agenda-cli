(() => {
  document.querySelectorAll('[data-postal-code]').forEach((input) => {
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
    const latitude = Number(element.dataset.latitude);
    const longitude = Number(element.dataset.longitude);
    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;

    const zoom = Number(element.dataset.zoom || 16);
    const name = element.dataset.name || 'Estabelecimento';
    const map = L.map(element, { scrollWheelZoom: false }).setView([latitude, longitude], zoom);

    L.tileLayer(tileUrl, {
      maxZoom: 19,
      attribution,
    }).addTo(map);

    L.marker([latitude, longitude]).addTo(map).bindPopup(name);
  });
})();
