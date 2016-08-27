CREATE TABLE properties(
    id                    integer NOT NULL PRIMARY KEY AUTOINCREMENT,

    name                  varchar(255) NOT NULL,
    file_id               integer,
    start_line            integer unsigned,
    end_line              integer unsigned,

    default_value         varchar(255),

    is_deprecated         tinyint(1) NOT NULL DEFAULT 0,
    is_magic              tinyint(1) NOT NULL DEFAULT 0,
    is_static             tinyint(1) NOT NULL DEFAULT 0,
    has_docblock          tinyint(1) NOT NULL DEFAULT 0,

    short_description     text,
    long_description      text,
    type_description      text,

    structure_id          integer unsigned NOT NULL,
    access_modifier_id    integer unsigned NOT NULL,

    types_serialized      text NOT NULL,

    FOREIGN KEY(file_id) REFERENCES files(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY(structure_id) REFERENCES structures(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY(access_modifier_id) REFERENCES access_modifiers(id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
);
