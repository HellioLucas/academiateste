-- ═══════════════════════════════════════════════════════
--  ACADEMIA DA EXTENSÃO — Setup do Banco de Dados
--  Execute este arquivo no phpMyAdmin antes de subir o site
-- ═══════════════════════════════════════════════════════

-- Admins
CREATE TABLE IF NOT EXISTS admins (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  UNIQUE NOT NULL,
    password   VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Projetos
CREATE TABLE IF NOT EXISTS projects (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    year        VARCHAR(10)  NOT NULL DEFAULT '',
    title       VARCHAR(255) NOT NULL DEFAULT '',
    team        VARCHAR(255) NOT NULL DEFAULT '',
    members     INT          NOT NULL DEFAULT 1,
    period      VARCHAR(100) NOT NULL DEFAULT '',
    emoji       VARCHAR(20)  NOT NULL DEFAULT '📚',
    gradient    TEXT         NOT NULL,
    chips       LONGTEXT     NOT NULL,
    tags        LONGTEXT     NOT NULL,
    description LONGTEXT     NOT NULL,
    how_text    LONGTEXT     NOT NULL,
    impact_data LONGTEXT     NOT NULL,
    files       LONGTEXT     NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tags/Categorias
CREATE TABLE IF NOT EXISTS tags (
    id    INT AUTO_INCREMENT PRIMARY KEY,
    slug  VARCHAR(100) UNIQUE NOT NULL,
    label VARCHAR(100) NOT NULL
);

-- Tentativas de login (rate limiting)
CREATE TABLE IF NOT EXISTS login_attempts (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    ip            VARCHAR(45) UNIQUE NOT NULL,
    attempt_count INT NOT NULL DEFAULT 0,
    blocked_until DATETIME NULL,
    last_attempt  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tags padrão
INSERT IGNORE INTO tags (slug, label) VALUES
('tecnologia', 'Tecnologia'),
('educacao',   'Educação'),
('saude',      'Saúde'),
('ambiente',   'Meio Ambiente');

-- Projetos de exemplo
INSERT INTO projects (year,title,team,members,period,emoji,gradient,chips,tags,description,how_text,impact_data,files) VALUES
('2024.1','Inclusão Digital para Idosos','Equipe TechVida',6,'2024.1 — Semestre Primavera','📱','linear-gradient(135deg,#1A6B42,#2563EB)','[{"l":"Tecnologia","c":"chip-g"},{"l":"Educação","c":"chip-b"}]','["tecnologia","educacao"]','Projeto de capacitação digital voltado à terceira idade, cobrindo smartphones, aplicativos de comunicação, serviços bancários online e prevenção de golpes virtuais.','Foram realizadas 12 oficinas presenciais em centros comunitários com grupos de até 20 participantes.','[{"v":"240","l":"Idosos atendidos"},{"v":"12","l":"Oficinas"},{"v":"3","l":"Bairros"},{"v":"89%","l":"Aprovação"}]','[{"n":"Apostila — Inclusão Digital","t":"pdf","s":"2,4 MB","url":"#"},{"n":"Slides das Aulas","t":"pdf","s":"5,1 MB","url":"#"}]'),
('2024.1','Robótica Educacional nas Escolas','Equipe RoboIF',5,'2024.1 — Semestre Primavera','🤖','linear-gradient(135deg,#1A3E7C,#6D28D9)','[{"l":"Robótica","c":"chip-p"},{"l":"Educação","c":"chip-b"}]','["tecnologia","educacao"]','Introdução à robótica e programação para alunos do ensino fundamental de escolas públicas.','A equipe visitou 4 escolas municipais realizando workshops de montagem e programação de robôs simples.','[{"v":"180","l":"Estudantes"},{"v":"4","l":"Escolas"},{"v":"8","l":"Workshops"},{"v":"32","l":"Robôs montados"}]','[{"n":"Apostila — Arduino Básico","t":"pdf","s":"3,8 MB","url":"#"},{"n":"Código dos Projetos","t":"code","s":"450 KB","url":"#"}]'),
('2023.2','Monitoramento Ambiental com IoT','Equipe GreenTech',4,'2023.2 — Semestre Outono','🌱','linear-gradient(135deg,#065F46,#1A6B42)','[{"l":"IoT","c":"chip-g"},{"l":"Meio Ambiente","c":"chip-b"}]','["tecnologia","ambiente"]','Desenvolvimento de sensores de baixo custo para monitoramento da qualidade do ar em áreas urbanas.','A equipe construiu 8 estações de monitoramento com ESP32 e sensores de CO₂, temperatura e umidade.','[{"v":"8","l":"Estações IoT"},{"v":"3 meses","l":"Coleta de dados"},{"v":"50K+","l":"Leituras"}]','[{"n":"Documentação do Sistema","t":"pdf","s":"2,1 MB","url":"#"},{"n":"Firmware ESP32","t":"code","s":"280 KB","url":"#"}]'),
('2024.1','Programação para Jovens em Vulnerabilidade','Equipe CodeFuturo',7,'2024.1 — Semestre Primavera','💻','linear-gradient(135deg,#B45309,#DC8A0A)','[{"l":"Python","c":"chip-o"},{"l":"Educação","c":"chip-b"}]','["tecnologia","educacao"]','Curso gratuito de introdução à programação em Python para jovens em situação de vulnerabilidade social.','Foram oferecidas 40 horas de aula presencial num centro social parceiro, divididas em módulos semanais.','[{"v":"28","l":"Alunos formados"},{"v":"40h","l":"Carga horária"},{"v":"3","l":"Módulos"}]','[{"n":"Apostila — Python Básico","t":"pdf","s":"4,2 MB","url":"#"}]'),
('2023.2','Triagem em Saúde Comunitária','Equipe SaúdeIF',12,'2023.2 — Semestre Outono','🏥','linear-gradient(135deg,#0F7592,#1A3E7C)','[{"l":"Saúde","c":"chip-b"},{"l":"Comunidade","c":"chip-g"}]','["saude"]','Ação de saúde preventiva com triagem de pressão arterial, glicemia e orientação nutricional.','Foram realizadas 6 jornadas de saúde em bairros parceiros, com tenda de atendimento em praças públicas.','[{"v":"450","l":"Atendimentos"},{"v":"6","l":"Jornadas"},{"v":"5","l":"Bairros"}]','[{"n":"Relatório das Jornadas","t":"pdf","s":"1,8 MB","url":"#"}]'),
('2024.1','Automação Residencial com Arduino','Equipe AutomIF',5,'2024.1 — Semestre Primavera','🏠','linear-gradient(135deg,#4338CA,#1A3E7C)','[{"l":"Arduino","c":"chip-p"},{"l":"Automação","c":"chip-b"}]','["tecnologia"]','Workshop de automação residencial de baixo custo usando Arduino e módulos de relé.','A equipe criou um kit de automação com materiais custando menos de R$ 80.','[{"v":"60","l":"Participantes"},{"v":"6","l":"Workshops"},{"v":"R$80","l":"Custo do kit"}]','[{"n":"Apostila — Automação Residencial","t":"pdf","s":"5,1 MB","url":"#"}]');

-- NOTA: Os admins são criados automaticamente pelo api.php na primeira visita ao site.
-- Usuários: admin (senha: ifce2026) e eleudson (senha: academia2026@)
