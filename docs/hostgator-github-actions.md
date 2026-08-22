# Deploy automático na HostGator

O workflow `deploy-hostgator` publica a branch `main` depois que o workflow de testes termina com sucesso. Ele compila os arquivos do Vite no GitHub, atualiza o repositório da HostGator por SSH, instala as dependências PHP e executa as migrações.

## Estrutura esperada

- Usuário do cPanel: `lukasm44`
- Porta SSH da hospedagem compartilhada: `2222`
- Repositório: `/home1/lukasm44/repositories/hub-inovaforce`
- Branch de produção: `main`
- PHP: `/opt/cpanel/ea-php83/root/usr/bin/php`

O domínio deve apontar para a pasta `public` do projeto. O arquivo `.env` deve existir somente na HostGator e nunca deve ser enviado ao GitHub.

## 1. Habilitar e testar o SSH

Habilite o acesso SSH da hospedagem e autorize uma chave exclusiva para o GitHub Actions em **cPanel > Acesso SSH > Gerenciar chaves do SSH**.

Não reutilize a senha do cPanel nem a chave da API do Asaas. A chave privada do deploy não deve possuir senha, pois será usada de forma não interativa pelo GitHub Actions.

Teste a chave antes de configurar o GitHub:

```shell
ssh -p 2222 -i caminho/da/chave_privada lukasm44@IP_OU_HOST_DA_HOSTGATOR
```

## 2. Preparar a aplicação na HostGator

No repositório clonado, crie o `.env`, configure MySQL, SMTP e Asaas e confirme que o Composer está disponível como `composer`, `$HOME/composer.phar` ou `$HOME/bin/composer.phar`.

O primeiro deploy também exige que as tabelas do banco já possam ser criadas pelo usuário MySQL configurado no `.env`.

## 3. Configurar os Secrets no GitHub

No repositório, acesse **Settings > Secrets and variables > Actions > New repository secret** e cadastre:

| Secret | Conteúdo |
|---|---|
| `HOSTGATOR_SSH_HOST` | IP ou hostname SSH da HostGator, sem protocolo e sem porta |
| `HOSTGATOR_SSH_PRIVATE_KEY` | Conteúdo completo da chave privada autorizada no cPanel |
| `HOSTGATOR_SSH_KNOWN_HOSTS` | Linha verificada da chave pública do servidor HostGator para a porta 2222 |

Na mesma página, abra a aba **Variables**, crie `HOSTGATOR_DEPLOY_ENABLED` e use inicialmente o valor `false`. Depois que a conexão SSH, o `.env` e o domínio estiverem validados, altere para `true`. Essa variável funciona como a chave geral do deploy automático.

Para obter a linha de `known_hosts`, execute localmente e compare a impressão digital com a informada pela HostGator antes de salvar:

```shell
ssh-keyscan -p 2222 -H IP_OU_HOST_DA_HOSTGATOR
```

Não cole esses valores em arquivos do projeto, issues, commits ou conversas.

## 4. Primeiro deploy

Depois de cadastrar os Secrets e alterar `HOSTGATOR_DEPLOY_ENABLED` para `true`, abra **Actions > deploy-hostgator > Run workflow** e execute pela branch `main`.

Nos próximos pushes para `main`, o deploy será iniciado automaticamente apenas depois que os testes passarem. Os deploys são serializados para evitar duas migrações simultâneas.

## Tarefas agendadas

O deploy não substitui os Cron Jobs do Laravel. A fila e o agendador continuam precisando das tarefas configuradas no cPanel, respeitando o intervalo mínimo permitido pelo plano HostGator.
