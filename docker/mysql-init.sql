-- Виконується автоматично при першому старті контейнера MySQL.
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    balance       DECIMAL(10,2) NOT NULL DEFAULT 1000.00,
    role          ENUM('player','admin') NOT NULL DEFAULT 'player',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_balance_non_negative CHECK (balance >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS game_rounds (
    id         BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    game       VARCHAR(20) NOT NULL,
    bet        DECIMAL(10,2) NOT NULL,
    win        DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rounds_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT chk_round_bet CHECK (bet > 0),
    CONSTRAINT chk_round_win CHECK (win >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rocket_rounds (
    id                 BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id            INT NOT NULL,
    bet                DECIMAL(10,2) NOT NULL,
    crash_point        DECIMAL(5,2) NOT NULL,
    started_at         DECIMAL(14,3) NOT NULL,
    cashout_multiplier DECIMAL(5,2) NULL,
    status             ENUM('active','cashed_out','crashed') NOT NULL DEFAULT 'active',
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    settled_at         TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_rocket_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT chk_rocket_bet CHECK (bet > 0),
    CONSTRAINT chk_rocket_crash CHECK (crash_point >= 1.30)
) ENGINE=InnoDB;

CREATE INDEX idx_rocket_active_user ON rocket_rounds (user_id, status, id);

-- Індекси (завдання 5). Їх можна тимчасово DROP-нути, щоб зняти EXPLAIN "до" — див. perf/sql/optimize.sql
CREATE INDEX idx_rounds_user_id    ON game_rounds (user_id, id);
CREATE INDEX idx_rounds_created_at ON game_rounds (created_at, user_id, bet, win);
