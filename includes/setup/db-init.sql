CREATE TABLE {%TablePrefix%}group (
    group_id        int(11)         NOT NULL AUTO_INCREMENT,
    level           smallint(6)     NOT NULL,
    label           varchar(64)     NOT NULL,
    color           char(6)         DEFAULT NULL,
    PRIMARY KEY (group_id),
    UNIQUE KEY level (level)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {%TablePrefix%}poi (
    id              int(11)         NOT NULL AUTO_INCREMENT,
    name            varchar(128)    NOT NULL,
    latitude        double          NOT NULL,
    longitude       double          NOT NULL,
    created_on      timestamp       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by      varchar(64)     DEFAULT NULL,
    last_updated    timestamp       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by      varchar(64)     DEFAULT NULL,
    evil_reported   timestamp       DEFAULT NULL,
    objective       varchar(32)     NOT NULL,
    obj_params      varchar(128)    NOT NULL,
    reward          varchar(32)     NOT NULL,
    rew_params      varchar(128)    NOT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {%TablePrefix%}arena (
    id              int(11)         NOT NULL AUTO_INCREMENT,
    name            varchar(128)    NOT NULL,
    latitude        double          NOT NULL,
    longitude       double          NOT NULL,
    created_on      timestamp       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by      varchar(64)     DEFAULT NULL,
    last_updated    timestamp       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by      varchar(64)     DEFAULT NULL,
    ex              tinyint(1)      NOT NULL DEFAULT '0',
    PRIMARY KEY (id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {%TablePrefix%}user (
    id              varchar(64)     NOT NULL,
    provider_id     varchar(64)     NOT NULL,
    nick            varchar(64)     NOT NULL,
    token           char(32)        NOT NULL,
    approved        tinyint(1)      NOT NULL,
    permission      smallint(6)     NOT NULL,
    user_signup     timestamp       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE {%TablePrefix%}api (
    id              int(11)         NOT NULL AUTO_INCREMENT,
    user_id         varchar(16)     DEFAULT NULL,
    name            varchar(64)     NOT NULL,
    color           char(6)         NOT NULL,
    token           char(64)        NOT NULL,
    access          varchar(1024)   NOT NULL,
    level           smallint(6)     NOT NULL,
    seen            timestamp       NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY level (user_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO {%TablePrefix%}group (level, label, color) VALUES
    (0,     '{i18n:group.level.anonymous}',     NULL    ),
    (40,    '{i18n:group.level.read_only}',     NULL    ),
    (80,    '{i18n:group.level.registered}',    NULL    ),
    (120,   '{i18n:group.level.submitter}',     '008282'),
    (160,   '{i18n:group.level.moderator}',     'bb00bb'),
    (200,   '{i18n:group.level.admin}',         'ff0000'),
    (250,   '{i18n:group.level.host}',          '008040');
