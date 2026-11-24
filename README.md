# Plataforma Digital de Treinamento em EPI (NR-6)

Módulo de treinamentos online desenvolvido para o sistema interno de RH da Nórdica Veículos S/A, utilizado na capacitação dos colaboradores operacionais sobre o uso, a guarda e a conservação de Equipamentos de Proteção Individual, conforme a Norma Regulamentadora 6.

Projeto apresentado na Atividade Extensionista I do CST em Análise e Desenvolvimento de Sistemas – UNINTER.

**Autor:** Rafael Zamboni

## Funcionalidades

- Editor de apresentações com slides, falas narradas, instrutor virtual 3D e atividades interativas (quiz, abas, cartões, checklist, passos e comparativo)
- Narração em áudio de todo o conteúdo, com sincronia labial do instrutor
- Prova final com sorteio de questões, nota mínima e limite de tentativas
- Certificado com código e QR Code, com validação pública
- Apostila em PDF gerada a partir do conteúdo do curso
- Matrículas com link individual (sem usuário e senha), prazo, progresso e reenvio por e-mail
- Portal do colaborador com acesso por CPF e data de nascimento
- Treinamento obrigatório por cargo
- Acompanhamento por unidade, relatórios e exportação

## Tecnologias

- PHP 8 e MySQL
- Bootstrap 5 e Bootstrap Icons
- JavaScript (three.js e @pixiv/three-vrm para o instrutor 3D, jsPDF, html-to-image)
- Kokoro-82M (kokoro-js) para geração da narração no editor

## Estrutura

```
treinamentos/
├── index.php              lista de treinamentos
├── editor.php             editor de slides, falas e instrutor
├── questoes.php           banco de questões e configuração da prova
├── matriculas.php         matrículas, links individuais e situação
├── acompanhamento.php     acompanhamento por unidade
├── obrigatorios.php       obrigatoriedade por cargo
├── treinar.php            acesso do colaborador por token
├── portal.php             portal do colaborador
├── certificado.php        certificado de conclusão
├── validar.php            validação pública do certificado
├── _*.php                 bibliotecas internas do módulo
└── assets/                CSS e JavaScript
database/
└── curso_nr6_slides.sql   conteúdo do curso de NR-6 (slides)
```

## Instalação

O módulo roda dentro do sistema de RH da empresa e utiliza o arquivo `app/bootstrap.php` desse sistema, que fornece a conexão com o banco (`db()`), a autenticação (`require_login()`), o controle de permissões e funções utilitárias. Esse arquivo não faz parte deste repositório por conter configurações de acesso.

As páginas `treinar.php`, `portal.php`, `ver.php`, `certificado.php` e `validar.php` são públicas e devem ser liberadas no bootstrap.
