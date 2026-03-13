# SISELO — Backend e Banco de Dados

## Visão Geral

O **SISELO (Sistema Integrado de Saúde)** é um sistema web desenvolvido para apoiar a integração entre o **Centro de Atenção ao Diabetes e Hipertensão (CADH)** e as **Unidades Básicas de Saúde (UBS)**.

A camada de **backend** e **banco de dados** é responsável pelo processamento das regras de negócio do sistema, gerenciamento das informações assistenciais e controle de acesso dos usuários.

Essa camada garante a integridade, consistência e segurança dos dados utilizados no acompanhamento de pacientes entre as unidades de saúde.

---

## Objetivo

Centralizar e gerenciar as informações relacionadas ao acompanhamento de pacientes, permitindo o compartilhamento estruturado de dados entre **CADH** e **UBS**, garantindo continuidade do cuidado e rastreabilidade das informações.

---

## Responsabilidades do Backend

A camada de backend é responsável por:

- processamento das requisições do sistema  
- aplicação das regras de negócio  
- validação de dados recebidos  
- gerenciamento de autenticação e autorização de usuários  
- comunicação com o banco de dados  
- registro de logs e auditoria das ações realizadas no sistema  

---

## Funcionalidades

O backend oferece suporte às seguintes funcionalidades principais:

- cadastro e gerenciamento de pacientes  
- registro de atendimentos realizados  
- gestão de planos de cuidado  
- controle de transições de pacientes entre unidades  
- administração de usuários e permissões de acesso  
- registro de auditoria das operações do sistema  

---

## Banco de Dados

O sistema utiliza um **banco de dados relacional** responsável por armazenar e organizar todas as informações utilizadas pela aplicação.

Entre os principais dados armazenados estão:

- informações cadastrais de pacientes  
- histórico de atendimentos  
- planos de cuidado  
- usuários do sistema  
- permissões de acesso  
- registros de auditoria  

O banco de dados garante consistência, organização e recuperação eficiente das informações do sistema.

---

## Tecnologias Utilizadas

- **PHP** — implementação da lógica de negócio do sistema  
- **MySQL** — gerenciamento e armazenamento dos dados  
- **Bootstrap** — integração com a interface da aplicação  

---

## Estrutura da Documentação

A documentação técnica do backend e banco de dados está organizada da seguinte forma:

docs/ documentação técnica do sistema
modulos/ descrição dos módulos e funcionalidades
diagramas/ diagramas de arquitetura e fluxos do sistema

---

## Desenvolvimento

Este repositório é destinado à **documentação técnica do backend e do banco de dados do sistema SISELO**.

Não devem ser incluídos no repositório:

- dados reais de pacientes  
- credenciais de acesso  
- informações sensíveis do sistema
