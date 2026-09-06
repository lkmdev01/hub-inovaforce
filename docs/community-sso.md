# Entrada automática na Comunidade Inovaforce

Esta integração permite abrir o Hub a partir do dashboard de um produto sem pedir uma segunda senha ao cliente. A credencial nunca deve ser enviada ao navegador: somente o backend do produto conversa com o Hub.

## Fluxo

1. O usuário já autenticado clica em **Comunidade Inovaforce** no produto.
2. O backend do produto envia uma solicitação assinada ao Hub.
3. O Hub confirma usuário, empresa e assinatura ativa e devolve uma URL descartável.
4. O navegador é redirecionado para essa URL e o Hub cria a sessão.

A solicitação e a URL expiram em dois minutos. Cada `nonce` e cada URL podem ser usados uma única vez.

## Configuração

No painel administrativo do Hub, abra **Produtos e planos**, localize o produto e ative **Entrada automática na comunidade**. Copie a credencial exibida e guarde-a como variável de ambiente no backend do produto:

```env
INOVAFORCE_HUB_URL=https://hub.inovaforce.com.br
INOVAFORCE_HUB_SSO_SECRET=credencial_copiada_no_hub
```

## Solicitação do backend

Envie `POST /api/community/sso/{slug-do-produto}` com JSON:

```json
{
  "email": "cliente@empresa.com.br",
  "team": "slug-da-empresa-no-hub",
  "timestamp": 1788739200,
  "nonce": "valor-aleatorio-com-pelo-menos-16-caracteres"
}
```

Monte o conteúdo canônico nesta ordem, separado por quebra de linha:

```text
email em minúsculas
slug da empresa
timestamp
nonce
```

Calcule `HMAC-SHA256` com a credencial do produto e envie o resultado no cabeçalho `X-Inovaforce-Signature` com o prefixo `sha256=`.

Exemplo em Laravel/PHP:

```php
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

$payload = [
    'email' => Str::lower(auth()->user()->email),
    'team' => auth()->user()->inovaforce_team_slug,
    'timestamp' => now()->timestamp,
    'nonce' => Str::random(40),
];

$canonical = implode("\n", [
    $payload['email'],
    $payload['team'],
    (string) $payload['timestamp'],
    $payload['nonce'],
]);

$signature = hash_hmac('sha256', $canonical, config('services.inovaforce.sso_secret'));

$response = Http::withHeaders([
    'X-Inovaforce-Signature' => 'sha256='.$signature,
])->post(config('services.inovaforce.hub_url').'/api/community/sso/seu-produto', $payload)
  ->throw();

return redirect()->away($response->json('launch_url'));
```

O e-mail precisa pertencer à empresa informada, estar verificado no Hub e a empresa precisa ter uma assinatura ativa do produto. No primeiro acesso, o usuário ainda poderá precisar aceitar os termos de uso; nenhuma senha será solicitada.

## Cuidados

- Nunca exponha a credencial em JavaScript, aplicativo móvel ou URL.
- Use HTTPS em produção.
- Gere uma nova credencial imediatamente se houver suspeita de vazamento.
- Ao trocar a credencial, a anterior deixa de funcionar na mesma hora.
- O botão do produto deve chamar uma rota do próprio backend, não o endpoint do Hub diretamente.
