# Localização e mapas

O Agenda CLI armazena o endereço do estabelecimento em campos estruturados e calcula latitude/longitude automaticamente no backend.

## Campos públicos/editáveis

- CEP
- logradouro
- número
- complemento
- bairro
- cidade
- UF

`address_line` continua existindo por compatibilidade e é montado automaticamente a partir dos campos acima.

## Campos internos

Os campos abaixo não são exibidos como inputs para Admin ou dono:

- `latitude`
- `longitude`
- `geocoded_at`
- `geocoding_provider`

Quando os componentes relevantes do endereço mudam, o backend tenta geocodificar novamente. Se a geocodificação falhar, o endereço continua salvo e o mapa deixa de ser exibido até uma localização válida ser encontrada.

## Nominatim / OpenStreetMap

Por padrão, a geocodificação usa o Nominatim público somente ao salvar um endereço novo ou alterado. Os resultados são armazenados em `geocoding_cache` e há um limitador local para manter no máximo uma chamada por segundo.

Configure no `.env`:

```env
GEOCODING_BASE_URL=https://nominatim.openstreetmap.org
GEOCODING_USER_AGENT="AgendaCLI/1.0 (+https://seu-dominio.com.br)"
MAP_TILE_URL=https://tile.openstreetmap.org/{z}/{x}/{y}.png
```

O `User-Agent` deve identificar claramente a aplicação. Em produção, use o domínio real da instalação.

O mapa é renderizado com Leaflet e mostra a atribuição do OpenStreetMap. `MAP_TILE_URL` foi deixado configurável para permitir troca de provedor sem alteração do código.

## Escala

Os serviços públicos do OpenStreetMap são adequados para uso leve e não oferecem SLA. Antes de um crescimento significativo de tráfego, troque geocodificação/tiles por um provedor com capacidade e termos adequados ou infraestrutura própria.
