CREATE TABLE structure_types(
    id   integer NOT NULL PRIMARY KEY AUTOINCREMENT,
    name varchar(255) NOT NULL
);

CREATE INDEX `structure_types_name` ON `structure_types` (`name`);

INSERT INTO structure_types (id, name) VALUES
    (NULL, 'class'),
    (NULL, 'trait'),
    (NULL, 'interface');
