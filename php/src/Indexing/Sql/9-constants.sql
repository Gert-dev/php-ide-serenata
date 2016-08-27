CREATE TABLE constants(
    id                    integer NOT NULL PRIMARY KEY AUTOINCREMENT,

    name                  varchar(255) NOT NULL,
    fqcn                  varchar(255),
    file_id               integer,
    start_line            integer unsigned,
    end_line              integer unsigned,

    default_value         varchar(255) NOT NULL,

    is_builtin            tinyint(1) NOT NULL DEFAULT 0,
    is_deprecated         tinyint(1) NOT NULL DEFAULT 0,
    has_docblock          tinyint(1) NOT NULL DEFAULT 0,

    short_description     text,
    long_description      text,
    type_description      text,

    types_serialized      text NOT NULL,

    -- Specific to member constants.
    structure_id integer unsigned,

    FOREIGN KEY(file_id) REFERENCES files(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY(structure_id) REFERENCES structures(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
