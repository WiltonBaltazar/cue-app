# Especificação de Funcionalidade: Cue App — Gestão de Escalas da Equipa de Filmagem e Transmissão (RTVAO)

**Feature Branch**: `001-cue-app`
**Criado em**: 2026-10-18
**Estado**: Rascunho
**Input**: "Criar um sistema independente de gestão de escala para a equipa de mídia (Rádio e Televisão Alfa & Ómega) da IEAD – Ministério Alfa & Ómega, partindo da Ficha de Cadastro e Perfil do Voluntário. Prioridades: formulário de cadastro online, geração automática de escalas, trocas e ausências, visão geral de disponibilidades, criação de dias de actividades/eventos e notificações push."

---

## Clarifications

### Session 2026-10-18

- Q: Método de acesso (FR-006)? → A: Telefone + palavra-passe; coordenadores com 2FA (aplicação autenticadora); e-mail opcional para recuperação da palavra-passe.
- Q: Nível global ou por função (FR-004)? → A: Nível por função; o cadastro pede um nível geral que é copiado para cada função escolhida e o coordenador ajusta por função.
- Q: Voluntários vêem a escala completa ou só os seus turnos (FR-027)? → A: Escala completa publicada; colegas apresentados com primeiro nome + inicial do apelido e função; turnos próprios destacados.
- Q: O que acontece a turnos sem resposta do voluntário? → A: Não há estado "Pendente": ao publicar, o turno fica Confirmado por omissão; o voluntário pode recusar se surgir um imprevisto (abre pedido de substituição); lembrete 72 h antes com opção de recusar num toque.
- Q: Como é controlado o acesso ao formulário de cadastro? → A: Um único link público que os coordenadores podem abrir/fechar, opcionalmente com data de encerramento.
- Q: Durante quanto tempo se guardam os dados de cadastros rejeitados? → A: 30 dias; nesse prazo o coordenador pode reverter a rejeição e aprovar. Depois, os dados pessoais são apagados e fica só o telefone em hash, que não impede novo cadastro — apenas indica ao coordenador que houve uma rejeição anterior.
- Q: Idade mínima para cadastro? → A: 14 anos; dos 14 aos 17 é obrigatório o contacto do encarregado e a confirmação do coordenador.
- Q: Quem coloca um voluntário como Inactivo? → A: O próprio voluntário (pausa com data de regresso opcional e retoma automática) ou o coordenador; o coordenador vê sugestões de voluntários sem turnos há 3 meses, sem inactivação automática.
- Q: Como lidar com dois coordenadores a editar a mesma escala? → A: Cada alteração é validada contra a versão carregada; se outro coordenador alterou entretanto, a gravação é recusada com "Alterado por <nome> — recarregar".
- Q: Como é tratado o pedido de remoção de dados (FR-028)? → A: Agendado para 7 dias após o pedido; o voluntário pode cancelar nesse prazo; coordenadores notificados e turnos futuros marcados "Por substituir"; depois, anonimização automática sem necessidade de aprovação.
- Q: Como conciliar a anonimização (FR-028) com o registo de auditoria (FR-029)? → A: Os registos de auditoria guardam só o identificador do voluntário; após anonimização mostram "Voluntário #id". Os registos nunca são alterados; acções, datas e nomes dos coordenadores mantêm-se.
- Q: O encarregado de um menor tem de dar consentimento? → A: Sim; o coordenador confirma o consentimento do encarregado fora da aplicação (presencialmente ou por telefone) e regista-o (quem e quando) antes de aprovar o menor.
- Q: O que acontece quando o texto da declaração muda? → A: No próximo acesso, o voluntário tem de aceitar a nova versão antes de usar a aplicação (versão e data registadas); turnos existentes não são afectados.
- Q: Quem vê o motivo de uma ausência? → A: Apenas os coordenadores e o próprio voluntário; os colegas (incluindo candidatos a troca) vêem apenas que o turno precisa de substituto.
- Q: A área do coordenador tem de funcionar no telemóvel? → A: Tarefas urgentes (aprovações, trocas, substitutos, cancelar evento, cobertura) totalmente optimizadas para telemóvel; o editor mensal da escala optimizado para tablet/computador, com editor simplificado por evento no telemóvel.

---

## Cenários de Utilizador e Testes *(obrigatório)*

### Perfis de utilizador

- **Coordenador**: gere voluntários, funções, eventos e escalas; aprova cadastros, trocas e ausências.
- **Voluntário**: preenche o cadastro, gere a sua disponibilidade, consulta a sua escala, pede trocas e regista ausências.

### User Story 1 — Cadastro online do voluntário (Prioridade: P1)

O voluntário acede a um link público e preenche a ficha de cadastro (equivalente digital da ficha em papel), aceita a declaração de consentimento e submete. O coordenador revê e aprova; só então o voluntário passa a ser elegível para escalas.

**Porque esta prioridade**: sem perfis completos (competências, disponibilidade) não há base para gerar escalas.

**Teste independente**: um voluntário consegue submeter a ficha completa e o coordenador vê-a na lista de pendentes e aprova-a.

**Cenários de aceitação**:

1. **Dado** um link de cadastro activo, **Quando** o voluntário preenche os campos obrigatórios e aceita a declaração, **Então** o cadastro fica no estado "Pendente" e o coordenador é notificado.
2. **Dado** um cadastro sem aceitação da declaração, **Quando** o voluntário tenta submeter, **Então** a submissão é bloqueada com mensagem explicativa.
3. **Dado** um cadastro pendente, **Quando** o coordenador o aprova, **Então** o voluntário passa a ter acesso completo à sua área pessoal e passa a ser elegível para escalas.
4. **Dado** um voluntário aprovado, **Quando** altera a sua disponibilidade ou competências, **Então** as alterações aplicam-se apenas a escalas ainda não publicadas.

---

### User Story 2 — Criar dias de actividade e eventos (Prioridade: P1)

O coordenador define actividades recorrentes (ex.: Culto de Domingo, Estudo Bíblico de Terça) e eventos extraordinários (conferências, casamentos, vigílias), cada um com data, horário, local e as posições necessárias (ex.: 3× Operação de câmara, 1× Áudio, 1× Streaming).

**Porque esta prioridade**: os eventos e as suas posições são o "molde" que a escala preenche.

**Teste independente**: o coordenador cria uma actividade recorrente semanal e um evento pontual, e ambos aparecem no calendário com as posições definidas.

**Cenários de aceitação**:

1. **Dado** um modelo de actividade recorrente (dia da semana, período, horário, posições), **Quando** o coordenador o activa, **Então** o sistema gera as ocorrências para o horizonte configurado (ex.: próximos 3 meses).
2. **Dado** uma ocorrência gerada, **Quando** o coordenador altera posições só dessa data, **Então** as restantes ocorrências mantêm o modelo original.
3. **Dado** um evento extraordinário, **Quando** é criado, **Então** inclui hora de chegada da equipa (montagem) distinta da hora de início do evento.
4. **Dado** um evento cancelado com escala publicada, **Quando** o coordenador o cancela, **Então** todos os escalados são notificados.

---

### User Story 3 — Geração automática da escala (Prioridade: P1)

O coordenador escolhe um período (ex.: Novembro) e o sistema propõe uma escala respeitando disponibilidade, ausências, competências, frequência máxima e rotação justa. O coordenador revê, ajusta manualmente e publica.

**Porque esta prioridade**: é o principal ganho de tempo e de justiça na distribuição.

**Teste independente**: com 10+ voluntários aprovados e um mês de eventos, a geração produz uma proposta válida que o coordenador consegue editar e publicar.

**Cenários de aceitação**:

1. **Dado** voluntários com disponibilidade e eventos definidos, **Quando** o coordenador gera a escala, **Então** nenhum voluntário é escalado num dia/período em que está indisponível ou ausente.
2. **Dado** uma posição que exige nível mínimo (ex.: Realização ≥ Intermédio), **Quando** a escala é gerada, **Então** só voluntários com esse nível nessa função são atribuídos.
3. **Dado** um voluntário com frequência "Uma vez por mês", **Quando** a escala mensal é gerada, **Então** não é escalado mais de uma vez nesse mês.
4. **Dado** voluntários igualmente elegíveis, **Quando** a escala é gerada, **Então** é preferido quem tem menos atribuições recentes e, em seguida, quem indicou a função como área de maior interesse.
5. **Dado** um voluntário de nível Iniciante, **Quando** é escalado, **Então** o sistema tenta escalar na mesma função/evento alguém marcado como "Posso apoiar outros membros".
6. **Dado** posições que não puderam ser preenchidas, **Quando** a proposta é apresentada, **Então** essas lacunas aparecem destacadas com sugestões de voluntários mais próximos de elegíveis.
7. **Dado** uma proposta revista, **Quando** o coordenador publica, **Então** todos os escalados recebem notificação e a escala fica visível na área pessoal.

---

### User Story 4 — Ausências e trocas (Prioridade: P2)

O voluntário regista indisponibilidades pontuais (ex.: viagem de 10 a 20) e pede trocas de turnos já atribuídos, directamente a um colega ou em aberto para quem estiver elegível. O coordenador aprova.

**Porque esta prioridade**: reduz faltas e mensagens dispersas, mas depende das escalas existirem.

**Teste independente**: um voluntário escalado pede troca, um colega elegível aceita, o coordenador aprova e a escala reflecte a mudança.

**Cenários de aceitação**:

1. **Dado** um voluntário escalado, **Quando** regista uma ausência que coincide com a escala, **Então** o turno fica marcado "Por substituir" e o coordenador é notificado com sugestões de substitutos.
2. **Dado** um pedido de troca em aberto, **Quando** é publicado, **Então** só voluntários elegíveis (disponíveis, competentes, sem conflito) o vêem e podem aceitar.
3. **Dado** uma troca aceite por um colega, **Quando** o coordenador aprova, **Então** a escala é actualizada e ambos são notificados.
4. **Dado** um pedido feito com menos de 48 h de antecedência (valor configurável), **Quando** é submetido, **Então** é marcado como urgente.
5. **Dado** um turno publicado, **Quando** o voluntário o recebe, **Então** o turno fica Confirmado por omissão; se surgir um imprevisto, o voluntário pode recusá-lo até ao início do evento, o que abre automaticamente um pedido de substituição e notifica o coordenador (urgente se faltarem menos de 48 h).
6. **Dado** um turno confirmado, **Quando** faltam 72 h (configurável) para o evento, **Então** o voluntário recebe um lembrete com a opção de recusar num toque.

---

### User Story 5 — Visão geral de disponibilidades (Prioridade: P2)

O coordenador vê, numa grelha, quantos voluntários estão disponíveis por dia da semana, período e função, e a cobertura de cada evento futuro.

**Porque esta prioridade**: permite planear recrutamento e formação onde há falta de pessoas.

**Teste independente**: o coordenador abre a visão geral e identifica, sem cálculos manuais, as funções/dias com menos voluntários do que as posições exigidas.

**Cenários de aceitação**:

1. **Dado** voluntários aprovados, **Quando** o coordenador abre a visão geral, **Então** vê uma matriz dia × período com contagens filtráveis por função e nível.
2. **Dado** eventos dos próximos 30 dias, **Quando** consulta a cobertura, **Então** cada evento mostra posições preenchidas/necessárias e alertas de lacunas.
3. **Dado** uma função com procura superior à oferta, **Quando** a visão é aberta, **Então** essa função aparece sinalizada como "em falta".
4. **Dado** um filtro por voluntário, **Quando** aplicado, **Então** mostra o histórico de participações e a frequência real vs. declarada.

---

### User Story 6 — Notificações push (Prioridade: P2)

Voluntários e coordenadores recebem notificações push no telemóvel/computador sobre escalas, lembretes e pedidos.

**Porque esta prioridade**: garante que a informação chega sem depender de grupos de mensagens.

**Teste independente**: um voluntário activa as notificações e recebe um lembrete antes do seu turno.

**Cenários de aceitação**:

1. **Dado** um voluntário que activou notificações, **Quando** uma escala que o inclui é publicada, **Então** recebe uma notificação com data, evento, função e hora de chegada.
2. **Dado** um turno agendado, **Quando** faltam 24 h (configurável), **Então** o voluntário recebe um lembrete.
3. **Dado** um pedido de troca, ausência ou novo cadastro, **Quando** é criado, **Então** o coordenador é notificado.
4. **Dado** um voluntário sem notificações activas, **Quando** entra na área pessoal, **Então** vê um aviso com instruções para as activar (incluindo instalação no ecrã principal quando o dispositivo o exige).

---

### Casos limite

- Voluntário aprovado que depois fica inactivo (pausa pelo próprio ou pelo coordenador, ver FR-005a): deixa de ser elegível sem perder histórico.
- Dois eventos sobrepostos no mesmo dia: um voluntário não pode ser escalado em ambos se os horários (incluindo montagem/desmontagem) se cruzarem.
- Voluntário com várias competências: só ocupa **uma** posição por evento.
- Escala já publicada e voluntário altera disponibilidade: o sistema alerta os turnos em conflito em vez de os remover silenciosamente.
- Dois coordenadores editam a mesma atribuição em simultâneo: a segunda gravação é recusada com indicação de quem alterou (FR-013).
- Não há voluntários suficientes: a geração conclui com lacunas assinaladas, nunca viola restrições obrigatórias.
- Pessoa com menos de 14 anos tenta registar-se: a submissão é bloqueada com mensagem explicativa.
- Voluntário menor faz 18 anos: o contacto do encarregado deixa de ser obrigatório, sem alterar o estado.
- Cadastro duplicado (mesmo telefone): o sistema bloqueia e sugere recuperar a conta existente. Excepção: se o cadastro anterior foi rejeitado e já apagado, o novo cadastro é aceite e assinalado ao coordenador.

---

## Requisitos *(obrigatório)*

### Requisitos funcionais

**Cadastro e perfil**

- **FR-001**: O sistema DEVE disponibilizar um formulário público de cadastro com todos os campos da Ficha de Cadastro (secções I a VII e Declaração), acessível por um único link público que os coordenadores podem abrir ou fechar, opcionalmente com data de encerramento automático. Com o cadastro fechado, o link mostra uma mensagem informativa em vez do formulário. O formulário inclui a criação da palavra-passe. Enquanto o cadastro estiver Pendente, Em correcção ou Rejeitado, o voluntário pode entrar mas vê apenas a página de estado do cadastro.
- **FR-002**: O sistema DEVE exigir a aceitação da declaração de consentimento, registando a versão do texto e a data e hora da aceitação. Quando a declaração é alterada (nova versão), cada voluntário DEVE aceitar a nova versão no próximo acesso antes de usar a aplicação; os turnos já atribuídos não são afectados. O coordenador DEVE poder editar o texto da declaração; ao guardar, é criada uma nova versão (registada na auditoria).
- **FR-002a**: O sistema DEVE recusar cadastros de pessoas com menos de 14 anos. Para idades entre 14 e 17 anos, DEVE exigir nome e telefone de um encarregado. O cadastro só pode ser aprovado depois de o coordenador confirmar o consentimento do encarregado (obtido presencialmente ou por telefone) e o registar na aplicação, com data e nome do coordenador.
- **FR-003**: O sistema DEVE permitir ao coordenador aprovar, rejeitar ou pedir correcção de cadastros pendentes.
- **FR-003a**: Uma rejeição DEVE poder ser revertida pelo coordenador durante 30 dias. Após esse prazo, os dados pessoais do cadastro rejeitado DEVEM ser apagados automaticamente, guardando apenas o telefone em hash e a data da rejeição. A pessoa PODE voltar a registar-se a qualquer momento; o novo cadastro mostra ao coordenador o aviso "cadastro anterior rejeitado em <data>", sem o bloquear.
- **FR-004**: O sistema DEVE registar, por função, se o voluntário tem interesse, experiência e o respectivo nível (Iniciante, Intermédio, Avançado, Mentor) **por função**. No cadastro, o voluntário indica um nível geral, que é aplicado a todas as funções escolhidas; o coordenador pode ajustar o nível de cada função na aprovação ou depois.
- **FR-005**: O sistema DEVE permitir ao voluntário editar disponibilidade (dias, períodos, frequência) e dados de contacto após aprovação.
- **FR-005a**: O voluntário DEVE poder pausar a sua participação (estado Inactivo), com data de regresso opcional; na data indicada volta automaticamente a Activo. O coordenador DEVE poder pausar e retomar qualquer voluntário. Turnos publicados durante a pausa passam a "Por substituir" e o coordenador é notificado. O sistema NÃO inactiva ninguém automaticamente; mostra ao coordenador uma lista de sugestão de voluntários activos sem turnos nos últimos 3 meses.
- **FR-006**: O sistema DEVE autenticar voluntários e coordenadores com número de telefone e palavra-passe. Os coordenadores DEVEM usar autenticação de dois factores (aplicação autenticadora). O e-mail é opcional e permite recuperar a palavra-passe; sem e-mail, o coordenador gera um link de redefinição de uso único, válido por 24 h, entregue pessoalmente ao voluntário. A recuperação da palavra-passe NÃO DEVE revelar se um telefone ou e-mail está registado.

**Funções e eventos**

- **FR-007**: O sistema DEVE permitir gerir o catálogo de funções (pré-carregado com as áreas da ficha: Operação de câmara, Realização e mistura de vídeo, Relações com o público, Informática, Electrónica, Cablagem, Iluminação, Áudio, Transporte de equipamentos, Montagem/desmontagem, Streaming).
- **FR-008**: O sistema DEVE permitir criar modelos de actividade recorrente e eventos extraordinários com data, horário de chegada, início, fim, local e posições (função, quantidade, nível mínimo).
- **FR-009**: O sistema DEVE permitir editar ou cancelar ocorrências individuais sem alterar o modelo recorrente.

**Geração de escala**

- **FR-010**: O sistema DEVE gerar uma proposta de escala para um intervalo de datas escolhido.
- **FR-011**: A geração DEVE respeitar como restrições obrigatórias: disponibilidade (dia e período), ausências registadas, nível mínimo da posição, frequência máxima declarada, uma posição por evento e ausência de sobreposição de horários.
- **FR-012**: A geração DEVE optimizar, por ordem: (1) preencher o máximo de posições, (2) equilibrar o número de atribuições entre voluntários, (3) privilegiar a área de maior interesse, (4) emparelhar iniciantes com mentores.
- **FR-013**: O coordenador DEVE poder editar manualmente qualquer atribuição antes e depois da publicação, sendo avisado quando a alteração viola uma restrição. Se outro coordenador alterou a mesma atribuição desde que a página foi carregada, a gravação DEVE ser recusada com a mensagem "Alterado por <nome> — recarregar", sem perder as restantes alterações.
- **FR-014**: O sistema DEVE manter estados de escala: Rascunho → Publicada; e de atribuição: Confirmada (estado inicial ao publicar) → Recusada / Por substituir. Não existe confirmação obrigatória por parte do voluntário.
- **FR-015**: O sistema DEVE permitir exportar/partilhar a escala publicada (vista para impressão e PDF).

**Ausências e trocas**

- **FR-016**: O voluntário DEVE poder registar ausências por intervalo de datas, com motivo opcional. O motivo é visível apenas aos coordenadores e ao próprio voluntário; os colegas, incluindo candidatos a troca, vêem apenas que o turno precisa de substituto.
- **FR-017**: O voluntário DEVE poder pedir troca de um turno a um colega específico ou em aberto; só voluntários elegíveis podem aceitar.
- **FR-018**: Trocas aceites DEVEM requerer aprovação do coordenador (configurável para aprovação automática quando o substituto tem nível igual ou superior).
- **FR-019**: O sistema DEVE sugerir substitutos ordenados por elegibilidade e menor carga recente.

**Visão geral**

- **FR-020**: O sistema DEVE apresentar a matriz de disponibilidade dia × período com filtros por função e nível.
- **FR-021**: O sistema DEVE apresentar a cobertura dos eventos futuros e sinalizar lacunas e funções em falta.
- **FR-022**: O sistema DEVE mostrar o histórico de participação por voluntário (turnos realizados, recusas, trocas).

**Notificações**

- **FR-023**: O sistema DEVE enviar notificações push para: escala publicada, alteração/cancelamento de turno, lembretes antes do turno (72 h com opção de recusar e 24 h, ambos configuráveis), pedidos de troca, decisões de aprovação e novos cadastros (coordenador).
- **FR-024**: O sistema DEVE funcionar como aplicação instalável no telemóvel para suportar push em Android e iOS.
- **FR-025**: Cada utilizador DEVE poder escolher que tipos de notificação recebe.

**Privacidade e acessos**

- **FR-026**: Dados pessoais sensíveis (data de nascimento, escolaridade, compromissos, motivações, contacto do encarregado, motivos de ausência) DEVEM ser visíveis apenas aos coordenadores e ao próprio voluntário.
- **FR-027**: Voluntários DEVEM ver a escala publicada completa da equipa, com os colegas identificados apenas pelo primeiro nome + inicial do apelido e função, e os seus próprios turnos destacados. Contactos e dados de perfil dos colegas não são mostrados.
- **FR-028**: O sistema DEVE permitir ao voluntário pedir a remoção dos seus dados (com confirmação da palavra-passe). A remoção fica agendada para 7 dias depois; durante esse prazo o voluntário pode cancelar o pedido. No momento do pedido, os coordenadores são notificados e os turnos futuros passam a "Por substituir". Findo o prazo, os dados pessoais são anonimizados automaticamente, sem depender de aprovação, mantendo apenas registos anónimos de participação.
- **FR-029**: O sistema DEVE registar quem alterou escalas, aprovações e cadastros, e quando. Os registos de auditoria DEVEM identificar voluntários apenas pelo seu identificador interno, nunca por nome ou dados pessoais; o nome é obtido no momento da consulta. Após anonimização (FR-028), o voluntário passa a aparecer como "Voluntário #id" sem que os registos sejam alterados.

**Utilização em dispositivos**

- **FR-030**: Todas as funcionalidades do voluntário DEVEM estar optimizadas para telemóvel. No coordenador, as tarefas urgentes (aprovar cadastros e trocas, escolher substitutos, cancelar eventos, consultar a cobertura) DEVEM estar totalmente optimizadas para telemóvel; o editor mensal da escala DEVE estar optimizado para tablet/computador e oferecer no telemóvel um editor simplificado por evento.

**Segurança, privacidade e usabilidade**

- **FR-031**: Visibilidade por perfil:
  - **Visitante não autenticado**: apenas o formulário de cadastro, a página de cadastro fechado e a confirmação de envio.
  - **Voluntário**: os seus próprios dados completos (perfil, disponibilidade, ausências com motivo, histórico, trocas). De colegas vê apenas primeiro nome + inicial do apelido e função, nas escalas publicadas e nos pedidos de troca que lhe são dirigidos ou abertos para si. Nunca vê contactos, perfil, ausências ou histórico de colegas.
  - **Coordenador**: todos os dados.
- **FR-032**: Segurança da conta: no máximo 5 tentativas de entrada por minuto para o mesmo telefone e origem, seguidas de bloqueio temporário com mensagem explicativa. Sessões com "lembrar-me" duram até 30 dias; sessões de coordenador sem "lembrar-me" terminam após 2 h de inactividade. Cada utilizador DEVE poder ver e terminar as suas sessões noutros dispositivos; alterar a palavra-passe termina as restantes sessões.
- **FR-033**: O formulário público de cadastro DEVE estar protegido contra abuso: no máximo 5 submissões por hora por origem, verificação anti-robô invisível e campo-armadilha. Submissões bloqueadas recebem uma mensagem genérica.
- **FR-034**: Retenção de dados: os dados de voluntários activos e inactivos mantêm-se enquanto a conta existir e podem ser removidos a pedido (FR-028); cadastros rejeitados seguem FR-003a; registos de auditoria mantêm-se por tempo indeterminado, por não conterem dados pessoais (FR-029); notificações na aplicação são apagadas após 90 dias; cópias de segurança são mantidas 30 dias.
- **FR-035**: O registo de auditoria (FR-029) DEVE incluir: decisões sobre cadastros (aprovar, rejeitar, reverter, pedir correcção, confirmar consentimento do encarregado), alterações de nível e de estado (pausa/retoma), criação, alteração e cancelamento de eventos, geração, publicação e edição de escalas, sobreposições de restrições com a justificação, decisões sobre trocas, alterações de definições e da declaração, emissão de links de redefinição de palavra-passe, pedidos e cancelamentos de remoção de dados, e cada consulta por um coordenador do perfil completo (dados sensíveis) de um voluntário.
- **FR-036**: Exportações e vistas de impressão da escala DEVEM conter apenas data, evento, horários, função e nome curto (primeiro nome + inicial). Notificações push DEVEM conter apenas título, evento, data, função e hora de chegada. Nenhuma das duas inclui contactos ou dados sensíveis.
- **FR-037**: O formulário de cadastro DEVE ser dividido em passos (secções I a VII + Declaração) com indicador de progresso. Se a submissão falhar por ligação lenta, os dados permanecem no ecrã para nova tentativa; se a página for recarregada, os campos não sensíveis são recuperados. Os erros aparecem junto ao campo respectivo.
- **FR-038**: Cada ecrã DEVE ter mensagem e próxima acção definidas para os estados: sem turnos ("Ainda não tem turnos"), cadastro pendente, em correcção (com as notas do coordenador), não aprovado, cadastro fechado, participação em pausa (com data de regresso e botão "Retomar"), sem ligação à Internet e notificações desactivadas.
- **FR-039**: O sistema DEVE pedir confirmação, indicando quem será afectado, antes de: publicar uma escala, alterar uma escala publicada, cancelar um evento, recusar um turno, pausar a participação, pedir a remoção de dados, rejeitar um cadastro e publicar uma nova versão da declaração.
- **FR-040**: Todas as notificações DEVEM ficar também disponíveis num centro de notificações na aplicação, independentemente do push; os próximos turnos, lembretes e alterações são visíveis na página inicial do voluntário.
- **FR-041**: A interface DEVE cumprir as WCAG 2.1 nível AA (contraste, tamanho dos alvos de toque, etiquetas, navegação por teclado e leitor de ecrã).

### Entidades principais

- **Voluntário**: identificação, contactos, escolaridade/formação, estado (Pendente, Activo, Inactivo, Rejeitado), consentimento, motivações e comentários.
- **Função**: nome, descrição, activa/inactiva.
- **CompetênciaVoluntário**: voluntário × função, interesse, experiência, nível, área preferida (sim/não).
- **Disponibilidade**: voluntário × dia da semana × período; frequência máxima; disponibilidade para formações/reuniões.
- **Ausência**: voluntário, data início, data fim, motivo.
- **ModeloActividade**: nome, recorrência, horários, posições padrão.
- **Evento**: ocorrência concreta (de modelo ou extraordinária), data, horários, local, estado.
- **Posição**: evento × função, quantidade, nível mínimo.
- **Escala**: período, estado (Rascunho/Publicada), autor, data de publicação.
- **Atribuição**: posição × voluntário, estado (Confirmada/Recusada/Por substituir); Confirmada por omissão ao publicar.
- **PedidoTroca**: atribuição de origem, solicitante, destinatário (opcional), aceitante, estado, aprovação.
- **Notificação/Subscrição**: utilizador, dispositivo, preferências.

### Terminologia

Termos canónicos usados na interface e nesta especificação:

- **Função**: área de serviço da equipa (ex.: Áudio, Streaming). Não confundir com o perfil de acesso (Coordenador/Voluntário).
- **Competência**: interesse, experiência e nível de um voluntário numa função.
- **Posição**: vaga de uma função num evento, com quantidade e nível mínimo.
- **Turno**: termo da interface para uma atribuição — um voluntário numa posição de um evento. "Atribuição" é o nome técnico da entidade.
- **Escala**: conjunto de turnos de um período (Rascunho ou Publicada).
- **Por substituir**: estado de um turno cujo voluntário deixou de poder comparecer.
- **Pausa**: estado Inactivo escolhido pelo voluntário ou pelo coordenador.

---

## Critérios de Sucesso *(obrigatório)*

### Resultados mensuráveis

- **SC-001**: Um voluntário completa o cadastro online em menos de 10 minutos.
- **SC-002**: O coordenador gera e publica a escala de um mês em menos de 15 minutos (contra o processo manual actual).
- **SC-003**: Em 100% das escalas geradas não há violações de restrições obrigatórias (indisponibilidade, ausência, nível, sobreposição).
- **SC-004**: Quando há voluntários suficientes, pelo menos 95% das posições ficam preenchidas na proposta automática.
- **SC-005**: Entre voluntários com a mesma disponibilidade e frequência, a diferença de atribuições no mês não ultrapassa 1.
- **SC-006**: 80% das ausências e trocas ficam resolvidas pelo menos 48 h antes do evento.
- **SC-007**: Notificações de publicação e alteração chegam aos dispositivos subscritos em menos de 1 minuto.
- **SC-008**: A taxa de faltas não comunicadas reduz-se em 50% nos primeiros 3 meses de uso.
- **SC-009**: As acções frequentes do voluntário (ver o próximo turno, recusar um turno, registar uma ausência, pedir uma troca) estão a no máximo 3 toques da página inicial, num ecrã de telemóvel.
- **SC-010**: As páginas principais (página inicial, cadastro, a minha escala, editor da escala) não têm falhas de nível AA na auditoria automática de acessibilidade e obtêm pelo menos 95 na pontuação de acessibilidade em telemóvel.

---

## Assunções

- Sistema independente, dedicado à RTVAO (não integrado na plataforma do Ministério nesta fase).
- Um ou mais coordenadores com permissões iguais; não há, nesta fase, líderes por área.
- Actividades base: Culto de Domingo e Estudo Bíblico de Terça; outras criadas como eventos.
- Períodos: Manhã, Tarde, Noite; "Horários variáveis" é tratado como disponível em todos os dias e períodos, mas com prioridade mais baixa na geração.
- Frequências mapeadas para limite mensal: Todas as semanas = sem limite (máx. 1 por semana), 2–3 vezes/mês = 3, 1 vez/mês = 1, Ocasionalmente = só por atribuição manual.
- Idade mínima de cadastro: 14 anos (ver FR-002a).
- Interface em português; fuso horário Africa/Maputo.
- Notificações por WhatsApp/SMS ficam fora do âmbito desta fase.

## Fora do âmbito

- Gestão de inventário de equipamentos.
- Controlo de presenças por QR code no local.
- Integração com a plataforma multi-tenant do Ministério Alfa & Ómega.
