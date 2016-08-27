CREATE TABLE access_modifiers(
    id   integer NOT NULL PRIMARY KEY AUTOINCREMENT,
    name varchar(255) NOT NULL
);

CREATE INDEX `access_modifiers_name` ON `access_modifiers` (`name`);

INSERT INTO access_modifiers (id, name) VALUES
    (NULL, 'public'),
    (NULL, 'protected'),
    (NULL, 'private');
