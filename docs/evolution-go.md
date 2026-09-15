# Evolution GO no Agenda CLI

O Agenda CLI pode enviar automaticamente as mensagens da `notification_outbox` usando uma instância Evolution GO.

## Pré-requisitos

- URL HTTPS da sua instância/servidor Evolution GO.
- Token da instância.
- PHP com extensão cURL habilitada.
- PR/ciclo de notificações já instalado e o cron `bin/cron.php` configurado.

## 1. Guarde o token fora do projeto

Crie uma pasta privada no servidor, fora de `public/` e, de preferência, fora do diretório do repositório:

```bash
mkdir -p ~/.agenda-cli
chmod 700 ~/.agenda-cli
nano ~/.agenda-cli/evolution-go.headers
```

Dentro do arquivo coloque apenas o cabeçalho fornecido pela Evolution GO:

```text
apikey: COLE_SEU_TOKEN_AQUI
```

Depois proteja o arquivo:

```bash
chmod 600 ~/.agenda-cli/evolution-go.headers
```

Nunca faça commit desse arquivo e não envie o token em tickets, prints ou conversas.

## 2. Configure o `.env`

Adicione:

```env
EVOLUTION_GO_BASE_URL=https://URL-FORNECIDA-PELA-EVOLUTION-GO
EVOLUTION_GO_HEADER_FILE=/home/SEU_USUARIO/.agenda-cli/evolution-go.headers
```

A URL deve ser HTTPS e não deve terminar em `/send/text`; informe apenas a URL base recebida do provedor.

## 3. Ative no painel

Entre como dono e abra **Gestão > Notificações**.

- Ative mensagens de WhatsApp.
- Em **Modo de envio**, selecione **Evolution GO — automático**.
- Escolha quais eventos devem gerar mensagens.
- Salve.

O botão **Processar agora** cria as mensagens pendentes e tenta enviá-las imediatamente. O modo manual continua disponível como contingência.

## 4. Cron

O cron também passa a enviar a caixa de saída automaticamente:

```cron
*/5 * * * * /usr/bin/php /caminho/agenda-cli/bin/cron.php >> /caminho/agenda-cli/storage-cron.log 2>&1
```

Cada mensagem registra status, número de tentativas, último erro e, quando retornado pela API, o ID da mensagem do provedor. Falhas são tentadas novamente pelo cron até 3 vezes, respeitando um intervalo mínimo de 5 minutos.

## Endpoint utilizado

O gateway envia texto para:

```text
POST {EVOLUTION_GO_BASE_URL}/send/text
```

Payload:

```json
{
  "number": "5511999999999",
  "text": "Mensagem",
  "id": "agenda-identificador"
}
```

A autenticação é lida do arquivo protegido e enviada como header HTTP.

## Observação sobre multi-tenancy

Nesta primeira integração a instância Evolution GO é configurada no nível da instalação do Agenda CLI. Portanto, estabelecimentos que selecionarem `evolution_go` usam o mesmo número conectado nessa instalação.

Antes de disponibilizar a plataforma para vários estabelecimentos independentes com números próprios, a próxima evolução deve criar um cofre de credenciais por tenant ou um serviço de secrets externo.
