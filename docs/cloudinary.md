# Cloudinary no Agenda CLI

O Cloudinary é o storage de imagens do Agenda CLI. O banco MySQL guarda apenas a URL HTTPS e o `public_id` necessário para gerenciar o ciclo de vida do arquivo.

## Estrutura

A organização adotada usa IDs estáveis para não depender de nomes ou slugs que podem mudar:

```text
agenda-cli/
├── establishments/
│   └── {establishment_id}/
│       ├── cover/
│       ├── logo/
│       └── services/
│           └── {service_id}/
└── users/
    └── {user_id}/
        └── profile/
```

Cada upload recebe um `public_id` único dentro da pasta. Ao trocar uma imagem, o arquivo novo é enviado primeiro e o antigo é removido depois que a referência no banco foi atualizada com sucesso.

## Credenciais

No painel do Cloudinary, copie o **Cloud Name**, **API Key** e **API Secret** da configuração da conta e coloque somente no `.env` do servidor:

```env
CLOUDINARY_CLOUD_NAME=seu-cloud-name
CLOUDINARY_API_KEY=sua-api-key
CLOUDINARY_API_SECRET=sua-api-secret
```

Nunca faça commit do `.env`, da API Secret ou de qualquer arquivo com credenciais.

## Banco de dados

Em instalações existentes aplique:

```bash
mysql -u USUARIO -p BANCO < database/migrations/2026_09_15_cloudinary_media.sql
```

A migration adiciona:

- `users.avatar_url` e `users.avatar_public_id`;
- `establishments.logo_url`, `logo_public_id`, `cover_url` e `cover_public_id`;
- `services.image_url` e `services.image_public_id`;
- a tabela `media_cleanup_queue`.

A migration é preparada para ser executada novamente sem tentar recriar colunas já existentes.

## Requisitos PHP

A integração usa as extensões nativas `curl` e `fileinfo`. Verifique no servidor:

```bash
php -m | grep -E 'curl|fileinfo'
```

O limite do Agenda CLI é 5 MB por imagem. O `upload_max_filesize` e o `post_max_size` do PHP também precisam permitir esse tamanho.

Formatos aceitos:

- JPEG
- PNG
- WebP
- GIF
- AVIF

Além do MIME informado pelo navegador, o backend valida o arquivo com `fileinfo` e `getimagesize()` antes do envio.

## Exclusão e substituição

O fluxo de substituição é:

1. envia a nova imagem ao Cloudinary;
2. recebe `secure_url` e `public_id`;
3. atualiza a referência no MySQL em transação;
4. solicita a exclusão do `public_id` antigo com invalidação de CDN.

No fluxo de remoção, a referência é retirada do MySQL e o arquivo é excluído do Cloudinary.

Se uma exclusão falhar temporariamente, o `public_id` é inserido em `media_cleanup_queue`. O cron tenta novamente, evitando que arquivos antigos fiquem abandonados no storage.

## Cron de limpeza

O `bin/cron.php` já processa a fila de limpeza junto com as demais rotinas do sistema. Mantenha o cron existente ativo. Exemplo:

```cron
*/5 * * * * /usr/bin/php /caminho/agenda-cli/bin/cron.php >> /caminho/agenda-cli/storage-cron.log 2>&1
```

A limpeza tenta cada arquivo até 10 vezes. Depois de uma falha, uma nova tentativa é agendada para 30 minutos depois.

## Transformações

O banco mantém a URL original retornada pelo Cloudinary. Para exibição, o Agenda CLI monta URLs derivadas com transformações do CDN, por exemplo:

- capa em formato horizontal;
- logo quadrado;
- imagem de serviço em miniatura;
- avatar com recorte de rosto;
- `q_auto` e `f_auto` para qualidade e formato automáticos.

Isso evita gerar várias cópias locais da mesma imagem.
