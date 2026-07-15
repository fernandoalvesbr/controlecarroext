# controlecarroext
Controle de carro externo para tecnico de campo


Segue um texto completo, já em **Markdown**, pronto para copiar e colar no `README.md` do GitHub:

# 🚗 MLS Frotas — Sistema de Controle e Gestão de Frota

O **MLS Frotas** é um sistema web desenvolvido para auxiliar no controle e gerenciamento de veículos, técnicos, rotas, abastecimentos, lavagens, cartões de pagamento, quilometragem, despesas e manutenção preventiva de uma frota.

O projeto foi desenvolvido em **PHP**, com armazenamento de dados em arquivos **JSON**, dispensando o uso de bancos de dados como MySQL ou SQLite.

A proposta é oferecer uma solução simples, leve e de fácil instalação, podendo ser utilizada inclusive em hospedagens compartilhadas com recursos limitados.

---

## 📌 Principais recursos

O sistema conta com diversos recursos para gerenciamento completo da frota:

* Dashboard com indicadores gerais;
* Cadastro e gerenciamento de veículos;
* Cadastro de técnicos e condutores;
* Registro de rotas e utilização dos veículos;
* Controle de abastecimentos;
* Controle de lavagem dos veículos;
* Controle de cartões e formas de pagamento;
* Cálculo de consumo de combustível em **KM/L**;
* Controle de quilometragem atual dos veículos;
* Controle de gastos da frota;
* Alertas de IPVA e seguro;
* Controle de manutenção preventiva;
* Relatórios executivos;
* Relatório específico de abastecimentos;
* Exportação de dados para CSV;
* Importação de histórico por CSV;
* Sistema de anexos e comprovantes;
* Conversão automática de imagens para WEBP quando disponível;
* Sistema de usuários e permissões;
* Auditoria de criação e edição dos registros;
* Backup dos arquivos de dados;
* Tema claro e escuro;
* Layout responsivo.

---

# 🔐 Sistema de acesso

O MLS Frotas possui autenticação obrigatória.

Na primeira execução, caso ainda não exista nenhum usuário cadastrado, o sistema cria automaticamente o seguinte acesso:

```text
Usuário: admin
Senha: admin
```

> ⚠️ **IMPORTANTE:** altere a senha do usuário administrador imediatamente após o primeiro acesso.

As senhas são armazenadas utilizando o sistema de hash nativo do PHP através de `password_hash()`.

---

# 👥 Perfis de usuário

O sistema trabalha com diferentes níveis de acesso.

## Administrador

O administrador possui acesso completo ao sistema e pode:

* Cadastrar veículos;
* Editar veículos;
* Excluir veículos;
* Ativar ou desativar veículos;
* Cadastrar técnicos;
* Editar técnicos;
* Excluir técnicos;
* Cadastrar cartões;
* Editar cartões;
* Excluir cartões;
* Registrar abastecimentos;
* Editar abastecimentos;
* Excluir abastecimentos;
* Registrar lavagens;
* Editar lavagens;
* Excluir lavagens;
* Registrar rotas;
* Editar rotas;
* Excluir rotas;
* Criar usuários;
* Gerenciar usuários;
* Importar dados por CSV;
* Exportar relatórios;
* Gerar backup dos dados;
* Consultar relatórios e indicadores.

## Usuário de leitura

O usuário com perfil de leitura pode consultar as informações disponíveis no sistema, mas não possui permissão para alterar os registros administrativos.

---

# 📊 Dashboard

O Dashboard apresenta uma visão geral da operação da frota.

Entre as informações exibidas estão:

* Total de gastos;
* Total gasto com abastecimentos;
* Total gasto com lavagens;
* Quilometragem percorrida;
* Quantidade de abastecimentos;
* Quantidade de lavagens;
* Gastos por período;
* Gastos por veículo;
* Consumo médio da frota;
* Indicadores de utilização;
* Alertas de documentos;
* Informações de manutenção preventiva.

Os dados podem ser filtrados por:

* Data inicial;
* Data final;
* Veículo.

Os filtros utilizados ficam armazenados temporariamente no navegador, facilitando consultas recorrentes.

---

# 🚘 Cadastro de veículos

O módulo de veículos permite cadastrar e controlar os automóveis da frota.

Entre as informações disponíveis estão:

* Placa;
* Modelo;
* Técnico responsável;
* Quilometragem inicial;
* Quilometragem da última revisão;
* Data da última revisão;
* Data de vencimento do IPVA;
* Data de vencimento do seguro;
* Status ativo ou inativo;
* Documentos e anexos.

Um veículo pode ser marcado como **inativo** sem precisar ser excluído do sistema.

Veículos inativos permanecem no histórico, mas deixam de ser considerados em determinadas operações e alertas.

---

# 👨‍🔧 Cadastro de técnicos

O sistema permite cadastrar os técnicos ou colaboradores responsáveis pela utilização dos veículos.

Cada técnico pode possuir:

* Nome;
* Documento ou anexo relacionado;
* Informação de quem realizou o cadastro;
* Informação de quem realizou a última edição.

Os técnicos cadastrados podem ser utilizados nos demais módulos do sistema.

---

# 🛣️ Controle de rotas

O módulo de utilização permite registrar cada deslocamento realizado pelos veículos.

Cada registro pode conter:

* Data;
* Condutor;
* Veículo;
* Rota ou descrição do deslocamento;
* Quilometragem inicial;
* Quilometragem final.

O sistema calcula automaticamente:

```text
KM percorrido = KM final - KM inicial
```

Essas informações são utilizadas nos relatórios operacionais e no cálculo da distância total percorrida pela frota.

---

# ⛽ Controle de abastecimentos

O módulo de abastecimentos permite registrar:

* Data;
* Condutor;
* Veículo;
* Quilometragem;
* Quantidade de litros;
* Valor gasto;
* Forma de pagamento;
* Cartão utilizado;
* Comprovante ou anexo.

Os abastecimentos fazem parte dos cálculos de:

* Gastos da frota;
* Gastos por veículo;
* Gastos mensais;
* Consumo de combustível;
* KM/L.

---

# 📈 Cálculo de consumo KM/L

O consumo pode ser calculado utilizando a diferença de quilometragem entre abastecimentos e a quantidade de litros informada.

Exemplo:

```text
Quilometragem anterior: 10.000 km
Quilometragem atual:    10.500 km
Combustível:            50 litros

500 km ÷ 50 litros = 10 km/l
```

Para que o cálculo funcione corretamente, é necessário informar:

* Quilometragem correta;
* Quantidade de litros abastecida.

Registros importados sem informação de litros podem aparecer normalmente nos gastos, mas não permitem calcular o consumo de combustível.

---

# 🧽 Controle de lavagens

O sistema possui um módulo específico para registrar as lavagens dos veículos.

Cada lavagem pode conter:

* Data;
* Condutor;
* Veículo;
* Quilometragem;
* Valor;
* Forma de pagamento;
* Cartão utilizado;
* Comprovante ou anexo.

## Regra importante

As lavagens:

✅ Entram no cálculo dos gastos da frota;
✅ Entram nos gastos individuais por veículo;
✅ Atualizam a quilometragem atual do veículo;
❌ Não possuem quantidade de litros;
❌ Não entram no cálculo de consumo em KM/L.

---

# 💳 Controle de cartões

O sistema permite cadastrar cartões e formas de pagamento utilizados pela frota.

Cada cadastro pode conter:

* Nome do cartão;
* Número do cartão;
* Senha ou informação de controle;
* Anexo;
* Auditoria de criação e edição.

Por padrão, em uma instalação nova, o sistema pode criar formas de pagamento iniciais como:

```text
Dinheiro
Cartão Combustível
```

As formas de pagamento cadastradas ficam disponíveis nos registros de abastecimentos e lavagens.

---

# 📄 Controle de documentos

Os veículos podem possuir datas de vencimento para:

* IPVA;
* Seguro.

O sistema gera alertas automaticamente quando:

* O documento já está vencido;
* O vencimento ocorrerá nos próximos 30 dias.

Veículos marcados como inativos não são considerados nos alertas de documentação.

---

# 🔧 Controle de manutenção

O sistema utiliza as informações de quilometragem para acompanhar a utilização dos veículos e auxiliar no controle de manutenção preventiva.

A quilometragem atual pode ser obtida a partir do maior valor encontrado entre:

* Quilometragem inicial do veículo;
* Registros de abastecimento;
* Registros de lavagem;
* Registros de utilização e rotas.

Dessa forma, o sistema mantém uma estimativa atualizada do hodômetro do veículo.

---

# 📎 Sistema de anexos

Diversos módulos permitem o envio de arquivos e comprovantes.

É possível anexar documentos em:

* Veículos;
* Técnicos;
* Cartões;
* Abastecimentos;
* Lavagens.

Os arquivos são armazenados na pasta:

```text
/uploads/
```

Quando o servidor possui suporte às bibliotecas de manipulação de imagens do PHP, imagens JPEG e PNG podem ser convertidas automaticamente para:

```text
WEBP
```

Essa conversão ajuda a reduzir o tamanho dos arquivos armazenados.

---

# 🔎 Auditoria dos registros

O sistema registra informações sobre quem criou e quem editou determinados registros.

Dependendo do módulo, é possível identificar:

```text
Adicionado por: nome_do_usuario
Editado por: nome_do_usuario
```

Esse recurso auxilia no controle administrativo e na identificação das alterações realizadas no sistema.

---

# 📅 Filtros

Os principais relatórios podem ser filtrados por:

* Data inicial;
* Data final;
* Placa do veículo.

Os filtros são utilizados em informações como:

* Rotas;
* Abastecimentos;
* Lavagens;
* Dashboard;
* Relatórios.

As preferências de filtro podem ser mantidas temporariamente através de cookies do navegador.

---

# ↕️ Ordenação de dados

As tabelas permitem ordenar os registros por diferentes campos.

A ordenação pode ser realizada de forma:

* Crescente;
* Decrescente.

A preferência utilizada também pode ser armazenada temporariamente no navegador.

---

# 📤 Exportação para CSV

Os dados podem ser exportados para arquivos CSV.

Atualmente podem ser exportadas informações de:

* Rotas;
* Abastecimentos;
* Lavagens.

Os arquivos são gerados utilizando codificação compatível com caracteres especiais e separação por ponto e vírgula.

Isso facilita a abertura dos dados em programas como:

* Microsoft Excel;
* LibreOffice Calc;
* Google Planilhas.

---

# 📥 Importação de CSV

O sistema possui uma ferramenta para importar registros antigos através de arquivos CSV.

O importador aceita arquivos separados por:

```text
;
```

ou:

```text
,
```

O formato esperado possui sete colunas:

```text
Data
Condutor
Rota
Placa
Valor
Cartão
KM
```

Exemplo:

```text
15/07/2026;João;Cliente Centro;ABC1D23;;Cartão Combustível;50200
```

Quando o sistema identifica um registro relacionado a abastecimento ou um valor financeiro preenchido, o registro pode ser importado como abastecimento.

Caso contrário, o registro é tratado como utilização ou rota.

> ⚠️ Abastecimentos importados através desse formato podem não possuir quantidade de litros. Nesse caso, o valor entra normalmente nos gastos, mas não será possível calcular corretamente o KM/L daquele registro.

---

# 📑 Relatórios

O sistema possui diferentes opções de relatórios.

## Relatório Executivo de Frota

O relatório executivo apresenta uma visão gerencial contendo:

* Período analisado;
* Data de emissão;
* Investimento total;
* Distância total;
* Quantidade de lançamentos;
* Desempenho por veículo;
* Gastos por veículo;
* Quantidade de abastecimentos;
* Quantidade de lavagens;
* Detalhamento das rotas.

O relatório possui layout preparado para impressão.

---

## Relatório de Abastecimentos

O relatório de abastecimentos apresenta informações como:

* Período;
* Departamento;
* Valor total abastecido;
* Quilometragem;
* Veículo;
* Cartão utilizado;
* Valor gasto;
* Distância percorrida.

O layout foi desenvolvido para facilitar a utilização administrativa e financeira.

---

# 💾 Armazenamento dos dados

O MLS Frotas não necessita de banco de dados SQL.

As informações são armazenadas em arquivos JSON.

Principais arquivos:

```text
veiculos.json
abastecimentos.json
utilizacao.json
tecnicos.json
usuarios.json
cartoes.json
lavagens.json
```

Isso torna a instalação mais simples e permite executar o sistema em servidores que não possuem MySQL ou SQLite.

---

# 💿 Backup

O sistema possui uma função administrativa para gerar um backup dos arquivos JSON.

O backup é criado em formato:

```text
ZIP
```

Exemplo:

```text
backup_mls_frotas_20260715_120000.zip
```

O backup inclui os arquivos JSON responsáveis pelos dados do sistema.

## ⚠️ Atenção aos anexos

A pasta:

```text
/uploads/
```

não faz parte automaticamente do arquivo ZIP gerado pelo sistema.

Para realizar um backup completo, recomenda-se copiar:

```text
index.php
arquivos .json
pasta /uploads/
```

---

# 🌙 Tema escuro

O MLS Frotas possui suporte a:

* Tema claro;
* Tema escuro.

A preferência de tema pode ser armazenada no navegador através de cookies.

---

# 🖥️ Tecnologias utilizadas

O projeto utiliza:

* PHP;
* JSON;
* HTML5;
* CSS3;
* JavaScript;
* Bootstrap 5;
* Bootstrap Icons;
* Chart.js.

O projeto foi desenvolvido com foco em compatibilidade com hospedagens PHP simples.

---

# ⚙️ Requisitos

Para executar o sistema é necessário:

* Servidor web com PHP;
* Permissão de escrita na pasta do projeto;
* Permissão para criação e alteração de arquivos JSON;
* Permissão de escrita na pasta `uploads`.

Recursos opcionais:

### GD / ImageWEBP

Utilizado para converter imagens para WEBP.

### ZipArchive

Utilizado para gerar backups compactados em ZIP.

Caso a extensão `ZipArchive` não esteja ativa no servidor, a função de backup automático poderá não funcionar.

---

# 📂 Estrutura básica do projeto

```text
/
├── index.php
├── veiculos.json
├── abastecimentos.json
├── utilizacao.json
├── tecnicos.json
├── usuarios.json
├── cartoes.json
├── lavagens.json
└── uploads/
```

Os arquivos JSON podem ser criados automaticamente pelo sistema caso ainda não existam.

---

# 🚀 Instalação

1. Faça o download dos arquivos do projeto.

2. Envie os arquivos para o seu servidor ou hospedagem.

3. Garanta que o PHP tenha permissão para criar e alterar arquivos na pasta do sistema.

4. Garanta permissão de escrita na pasta:

```text
/uploads/
```

5. Acesse o sistema pelo navegador.

Exemplo:

```text
https://seudominio.com.br/frotas/
```

6. Utilize o acesso inicial:

```text
Usuário: admin
Senha: admin
```

7. Altere imediatamente a senha padrão.

---

# 🔒 Recomendações de segurança

Para utilizar o sistema em ambiente de produção:

* Altere imediatamente a senha padrão;
* Utilize HTTPS;
* Faça backups regularmente;
* Proteja os arquivos JSON contra acesso direto;
* Restrinja o acesso à pasta de uploads quando necessário;
* Utilize senhas fortes;
* Mantenha o PHP atualizado sempre que possível;
* Mantenha uma cópia externa dos backups;
* Evite armazenar senhas sensíveis de cartões caso isso não seja necessário.

---

# ⚠️ Observação sobre dados sensíveis

O módulo de cartões possui campos administrativos que podem armazenar informações relacionadas ao cartão e senha de controle.

Antes de utilizar esse recurso em produção, avalie a política de segurança da empresa.

Nunca armazene:

* Senhas bancárias;
* PINs financeiros reais;
* CVV;
* Informações completas de cartões bancários sem proteção adequada.

O ideal é utilizar o campo apenas para códigos internos ou informações operacionais não sensíveis.

---

# 🔄 Migração e restauração

Para mover o sistema para outro servidor:

1. Copie o arquivo principal do sistema;
2. Copie todos os arquivos JSON;
3. Copie a pasta `uploads`;
4. Envie os arquivos para o novo servidor;
5. Garanta as permissões de escrita;
6. Acesse o sistema normalmente.

Como os dados são armazenados em JSON, não é necessário importar banco de dados SQL.

---

# 🧩 Objetivo do projeto

O MLS Frotas foi criado para centralizar informações relacionadas à utilização de veículos de uma empresa, oferecendo uma visão clara de:

* Quem utilizou cada veículo;
* Para onde o veículo foi;
* Quantos quilômetros percorreu;
* Quanto foi gasto em combustível;
* Quanto foi gasto em lavagens;
* Qual cartão foi utilizado;
* Qual é a quilometragem atual;
* Quando documentos estão próximos do vencimento;
* Como estão distribuídos os gastos da frota.

O objetivo é facilitar o controle administrativo e operacional sem exigir uma infraestrutura complexa.

---

# 📌 Status do projeto

O sistema está em desenvolvimento contínuo.

Novos recursos, melhorias de interface, relatórios e ferramentas administrativas podem ser adicionados em futuras versões.

---

# 🤝 Contribuições

Sugestões, melhorias e correções são bem-vindas.

Você pode:

* Abrir uma Issue;
* Sugerir melhorias;
* Reportar erros;
* Enviar um Pull Request.

---

# 📄 Licença

Defina aqui a licença desejada para o projeto.

Exemplos:

* MIT;
* GPL-3.0;
* Projeto privado;
* Uso interno.

> Antes de publicar o repositório, recomenda-se adicionar um arquivo `LICENSE` informando claramente as condições de utilização do código.

---

## 🚗 MLS Frotas

**Sistema simples, leve e completo para controle de veículos, rotas, abastecimentos, lavagens, despesas e manutenção de frota.**

Esse texto já pode ser colocado diretamente no arquivo **`README.md`** do repositório.
