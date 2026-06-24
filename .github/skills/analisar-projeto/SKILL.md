---
name: analisar-projeto
description: "Use when: you need to read, understand, and summarize a codebase quickly and accurately."
argument-hint: "Qual projeto ou pasta você quer analisar?"
---

# Analisar projeto

Use esta habilidade para entender rapidamente a estrutura, a arquitetura e os pontos de entrada de um projeto antes de editar, testar ou explicar algo.

## Objetivo

O resultado esperado é uma análise prática com:
- qual é o propósito do projeto;
- quais tecnologias e ferramentas estão em uso;
- como o fluxo principal funciona;
- onde ficam os arquivos mais importantes;
- quais riscos, gaps ou pontos de atenção existem.

## Passo a passo

### 1. Identificar a stack principal
- Verifique arquivos de configuração como `composer.json`, `package.json`, `Dockerfile`, `docker-compose.yml`, `vite.config.js` e `phpunit.xml`.
- Determine quais linguagens, frameworks e ferramentas estão sendo usados.
- Observe se o projeto é backend, frontend, full-stack ou integra serviços externos.

### 2. Ler a documentação base
- Procure `README.md` e outros arquivos de setup.
- Entenda como rodar o projeto, quais variáveis de ambiente são necessárias e quais comandos são usados para desenvolvimento e testes.

### 3. Mapear a estrutura do repositório
- Liste pastas principais (`app/`, `config/`, `database/`, `routes/`, `resources/`, `tests/`).
- Identifique o papel de cada área do projeto.
- Note quais pastas parecem ser centrais para a lógica de negócio.

### 4. Encontrar os pontos de entrada
- Verifique rotas, controladores, services, jobs, commands e views.
- Entenda como uma requisição começa e onde os dados são processados.
- Se houver importação, API ou integração externa, registre isso explicitamente.

### 5. Entender o modelo de dados
- Leia migrations e modelos para saber quais entidades existem.
- Veja relações importantes entre tabelas, campos críticos e possíveis regras de negócio.

### 6. Revisar a camada de apresentação
- Confira templates, componentes, assets e scripts de frontend.
- Identifique se a interface depende de lógica server-side, API ou JavaScript.

### 7. Checar qualidade e validação
- Procure testes, lint, build e scripts de setup.
- Observe se o projeto possui cobertura de testes e quais áreas ainda parecem frágeis.

### 8. Produzir um resumo acionável
- Resuma o projeto em linguagem clara.
- Destaque os principais módulos, fluxos e arquivos recomendados para leitura inicial.
- Liste dúvidas, riscos e próximos passos.

## Decisões importantes

- Se o projeto tiver múltiplas tecnologias, analise cada uma separadamente antes de unir a visão geral.
- Se a documentação estiver incompleta, priorize os arquivos de configuração e os pontos de entrada reais do código.
- Se houver um fluxo crítico (como importação de arquivos, autenticação ou pagamentos), descreva esse fluxo com detalhes.
- Se o projeto não tiver testes ou setup claro, marque isso como um ponto de atenção.

## Critérios de qualidade

A análise deve:
- basear-se em arquivos reais do repositório;
- evitar suposições sem evidência;
- apontar arquivos e pastas específicos;
- explicar o funcionamento em termos simples;
- indicar onde começar a trabalhar.
