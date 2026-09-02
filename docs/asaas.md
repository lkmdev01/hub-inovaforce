# Configuração do Asaas

O Hub usa exclusivamente o Asaas para clientes, cobranças, assinaturas, checkouts e notas fiscais.

## Ambiente de testes

Adicione ao arquivo `.env`:

```dotenv
ASAAS_API_KEY=sua_chave_do_sandbox
ASAAS_BASE_URL=https://api-sandbox.asaas.com/v3
ASAAS_CHECKOUT_URL=https://asaas.com/checkoutSession/show
ASAAS_WEBHOOK_TOKEN=um_token_longo_e_aleatorio
```

Depois, limpe o cache de configuração e execute as migrations.

## Webhook

No painel do Asaas, cadastre o endpoint público:

```text
https://seu-dominio.com/webhooks/asaas
```

Use em **Token de autenticação** exatamente o mesmo valor de `ASAAS_WEBHOOK_TOKEN`.

O endpoint trata os eventos de checkout, pagamento e assinatura, incluindo:

- `CHECKOUT_PAID`, `CHECKOUT_CANCELED` e `CHECKOUT_EXPIRED`;
- `PAYMENT_RECEIVED`, `PAYMENT_CONFIRMED` e `PAYMENT_OVERDUE`;
- eventos de estorno e chargeback;
- `SUBSCRIPTION_INACTIVATED` e `SUBSCRIPTION_DELETED`.
- eventos de nota fiscal, como `INVOICE_AUTHORIZED`, `INVOICE_CANCELED` e `INVOICE_ERROR`;
- análise de risco, estornos, reembolsos e chargebacks.

Os eventos são gravados de forma idempotente pelo identificador enviado pelo Asaas e as automações são processadas em fila, com cinco tentativas. Falhas podem ser reprocessadas em **Administração > Automações**.

Além do webhook, o Hub consulta de hora em hora as cobranças das assinaturas para recuperar eventos eventualmente perdidos. Essa consulta corrige estados e faturas silenciosamente, sem reenviar comunicações históricas.

## Produção

Para produção, troque a chave e a URL da API:

```dotenv
ASAAS_API_KEY=sua_chave_de_producao
ASAAS_BASE_URL=https://api.asaas.com/v3
```

Cada plano define a forma de pagamento aceita no checkout recorrente (`CREDIT_CARD` ou `PIX`). As faturas geradas pelo ciclo da assinatura são recebidas pelo webhook e ficam disponíveis no Hub com o link hospedado pelo Asaas. O Hub não coleta nem armazena dados de cartão.

O administrador também pode gerar cobranças avulsas por Pix, boleto ou forma escolhida pelo cliente e solicitar o estorno integral de uma cobrança paga. A confirmação final sempre vem do Asaas por webhook.

## Grupos de clientes

Os grupos criados na administração do Hub são enviados ao Asaas pelo campo `groupName`. Alterar o grupo de um cliente já sincronizado atualiza o cadastro remoto.

## Nota fiscal de serviço

Antes de ativar a emissão automática:

1. habilite e configure a emissão de NFS-e na conta Asaas;
2. confirme com a contabilidade o serviço municipal e as alíquotas;
3. preencha a seção **Nota fiscal automática** no produto do Hub;
4. mantenha selecionados no webhook os eventos de nota fiscal.

O Hub configura cada assinatura para emissão automática e sincroniza situação, número, código de validação, PDF e XML. Nenhuma nota é solicitada enquanto o produto estiver com a opção fiscal desativada ou incompleta.

## Régua de cobrança e WhatsApp

O agendador do Laravel deve rodar em produção para enviar os lembretes de vencimento e de 3, 7 e 15 dias de atraso.

No deploy da HostGator, o GitHub Actions tenta instalar automaticamente esta tarefa:

```cron
* * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home1/lukasm44/repositories/hub-inovaforce/artisan schedule:run >> /home1/lukasm44/repositories/hub-inovaforce/storage/logs/scheduler.log 2>&1
```

Se a hospedagem bloquear o comando `crontab`, cadastre essa mesma execução em **cPanel > Trabalhos Cron**. Ela processa a fila, cancelamentos, conciliação e lembretes.

Para WhatsApp, configure um gateway ou automação que aceite chamadas HTTP:

```dotenv
WHATSAPP_WEBHOOK_URL=https://seu-gateway-ou-n8n.com/webhook/cobranca
WHATSAPP_WEBHOOK_TOKEN=seu_token
```

Sem essa configuração, as mensagens ficam registradas na central de automações com a situação **Aguardando configuração**; os e-mails continuam funcionando normalmente.

## Liberação e bloqueio nos softwares

Cada produto pode ter uma URL de integração de acesso na administração. Quando o pagamento ativa, suspende ou encerra uma assinatura, o Hub envia `subscription.access_updated` para essa URL.

O corpo inclui cliente, assinatura, produto, quantidade de acessos, situação e motivo. Valide sempre o cabeçalho:

```text
X-Hub-Signature: sha256=assinatura_hmac_do_corpo
```

A assinatura é calculada com o segredo configurado no produto. Reenvios do mesmo evento não geram provisionamentos duplicados.

## Operação e segurança

- o painel administrativo exige autenticação em duas etapas por padrão (`ADMIN_REQUIRE_TWO_FACTOR=true`);
- alterações administrativas e do portal ficam registradas na central de automações;
- o agendador grava um sinal de vida a cada minuto, visível na central;
- um backup local compactado do banco é criado diariamente às 02:30 e mantido por 14 dias em `storage/app/private/backups`;
- mantenha também um backup externo da conta HostGator, pois uma cópia no mesmo servidor não protege contra indisponibilidade total;
- monitore `https://hub.inovaforce.com.br/up` em um serviço de uptime.

Depois do primeiro deploy desta versão, acesse **Configurações > Segurança**, ative o 2FA do usuário master e guarde os códigos de recuperação em local seguro.
