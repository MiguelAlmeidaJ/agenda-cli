(() => {
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
