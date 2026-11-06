# Guia de Implantação — Roteirizador

> O **Instalador COBEB** ainda não existe no Framework (próxima etapa da CDP). Até lá, a implantação é manual, conforme abaixo.

## 1. Pré-requisitos

- Windows Server 2019 Datacenter (produção) ou Windows 10 + XAMPP (desenvolvimento)
- Apache 2.4 com `mod_rewrite` habilitado
- PHP 8.2 com as extensões: `pdo_mysql`, `mbstring`, `openssl`, **`zip`**, **`fileinfo`**, `xmlreader` e `simplexml`
- MariaDB
- Composer instalado globalmente

No XAMPP, confira em `php.ini` se estas linhas estão **sem** `;` no início:

```ini
extension=zip
extension=fileinfo
extension=mbstring
extension=pdo_mysql
```

### Limites de upload (obrigatório)

Os exports do Promax podem passar de 40 MB. Ajuste no `php.ini`:

```ini
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 300
memory_limit = 1024M
```

Reinicie o Apache depois de alterar o `php.ini`.

## 2. Publicação dos arquivos

1. Copiar o projeto para o servidor (fora de qualquer diretório publicamente acessível).
2. Apontar o **DocumentRoot** (ou o destino do Proxy Reverso do Portal Corporativo) exclusivamente para a pasta `public/`.
3. Nunca apontar o servidor para a raiz do projeto.

## 3. Dependências

```bash
composer install --no-dev --optimize-autoloader
```

Instala o PHPMailer (envio do e-mail de "Esqueci minha senha"). Em uma atualização de versão, rode o comando de novo.

## 4. Variáveis de ambiente

```bash
copy .env.example .env
```

Editar `.env` com as credenciais reais do ambiente (nunca versionar o `.env` real). Atrás do Portal, preencher `APP_BASE_PATH` com o caminho definido pelo Proxy Reverso.

Para o **Esqueci minha senha** funcionar, preencher também:

- `APP_URL`: endereço público completo pelo qual os usuários acessam o sistema (ex.: `https://portal.cobeb.com.br/roteirizador`). É o início do link enviado por e-mail.
- `APP_TIMEZONE`: fuso horário das datas do sistema. O padrão é `America/Sao_Paulo`; sem ele, o PHP usaria UTC (3 horas adiantado).
- `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION` (`tls`, `ssl` ou vazio), `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` e `MAIL_FROM_NAME`: servidor SMTP da COBEB (solicitar ao TI).

Sem esses valores o sistema funciona normalmente, mas o link não é enviado (o erro fica em `storage/logs`). Nesse caso, a senha é trocada pelo administrador na tela **Usuários**.

## 5. Banco de dados

```sql
CREATE DATABASE roteirizador CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
mysql -u usuario -p roteirizador < database/database.sql
```

Em uma instalação já existente, rodar o mesmo script de novo cria só as tabelas novas (ex.: `redefinicoes_senha`), sem apagar dados.

O script já cadastra:
- as Mesas por revenda: PM (52, 53, 54), LP (22, 23) e AB (24);
- os setores da PM: 520–527 (Mesa 52), 530–537 (Mesa 53) e 540–543 (Mesa 54), com os limites das planilhas.

Os setores da LP e da AB são cadastrados na tela **Setores** após a instalação.

## 6. Permissões

Garantir permissão de escrita para o usuário do Apache em `storage/logs`, `storage/cache` e **`storage/temp`** (local onde as planilhas enviadas são processadas e apagadas em seguida).

## 7. Primeiro acesso

1. Acessar o sistema: com o banco vazio, ele abre a tela **Primeiro acesso**.
2. Criar o administrador.
3. Em **Usuários**, criar os usuários da Logística e do Comercial.
   - Cada usuário do Comercial deve ser vinculado à sua Mesa; a revenda dele é a da Mesa.
   - Cada usuário da Logística recebe uma revenda, ou "Todas".
4. Em **Importações**, carregar **para cada revenda**, nesta ordem: 01.20.11, Top Line, Travas, 03.02.37 (histórico), 03.02.24 e, por último, os pedidos do Cora da data de entrega. As trocas (03.18.05 e 03.02.37 de notas de troca) podem ser enviadas pela aba **Troca**. Os relatórios da Verba de troca (03.18.05 e 03.05.09) podem ser enviados pela aba **Aprovação troca**.

## 8. Registro no Portal Corporativo

1. Conferir `manifest.json` (nome, versão, setor, URL base, status).
2. Solicitar o cadastro no Proxy Reverso do Portal Corporativo.
3. Validar que o sistema responde corretamente atrás do base path definido pelo Portal.

> **Atenção:** o `AuthMiddleware` do Framework COBEB 1.0 redireciona para `/login` sem considerar o base path. Atrás do Portal, quem acessar sem sessão pode ser enviado para o endereço errado. A correção cabe ao núcleo do Framework (usar `base_url('login')`) e deve ser solicitada à governança da CDP.

## 9. Homologação

Antes da publicação em produção, submeter o sistema ao **COBEB Validator**. O sistema precisa obter classificação Nível A ou Nível B (ver Manual de Desenvolvimento, Seção 8).
