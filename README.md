# Projeto Breno

Aplicação PHP com PostgreSQL.

## Configuração para Render

1. Crie um repositório Git no GitHub e adicione este código.
2. No Render, crie um novo Web Service:
   - Environment: Docker
   - Build command: deixar em branco
   - Start command: deixar em branco
   - Branch: `main`
3. Configure as variáveis de ambiente no Render:
   - `DB_HOST`
   - `DB_PORT`
   - `DB_DATABASE`
   - `DB_USER`
   - `DB_PASSWORD`

## Arquivos adicionados

- `Dockerfile`: build do PHP + Apache + PostgreSQL
- `render.yaml`: configuração mínima de serviço Render
- `.gitignore`: padrões recomendados para PHP
- `config/conexao.php`: agora lê variáveis de ambiente com fallback local

## Observações

- O banco de dados deve ser PostgreSQL.
- O app usa `index.php` como ponto de entrada.
- Se você usar o banco de dados do Render, configure as variáveis no dashboard.
