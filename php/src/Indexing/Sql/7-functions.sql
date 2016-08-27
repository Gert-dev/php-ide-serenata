CREATE TABLE functions(
    id                      integer NOT NULL PRIMARY KEY AUTOINCREMENT,

    name                    varchar(255) NOT NULL,
    fqcn                    varchar(255),
    file_id                 integer,
    start_line              integer unsigned,
    end_line                integer unsigned,

    is_builtin              tinyint(1) NOT NULL DEFAULT 0,
    is_deprecated           tinyint(1) NOT NULL DEFAULT 0,

    short_description       text,
    long_description        text,
    return_description      text,

    return_type_hint        varchar(255),

    -- Specific to members.
    structure_id            integer unsigned,
    access_modifier_id      integer unsigned,

    is_magic                tinyint(1) NOT NULL DEFAULT 0,
    is_static               tinyint(1) NOT NULL DEFAULT 0,
    is_abstract             tinyint(1) NOT NULL DEFAULT 0,
    is_final                tinyint(1) NOT NULL DEFAULT 0,
    has_docblock            tinyint(1) NOT NULL DEFAULT 0,

    -- Holds data that is added to link tables in a serialized format. This allows very fast access
    -- to them without having to perform JOINs or queries inside a loop. This can save half a second
    -- on a large class info fetch that normally takes about 750 milliseconds, which can make all
    -- the difference in snappiness when requesting autocompletion.
    throws_serialized       text NOT NULL,
    parameters_serialized   text NOT NULL,
    return_types_serialized text NOT NULL,

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

-- Contains parameters for functions and methods.
CREATE TABLE functions_parameters(
    id                 integer NOT NULL PRIMARY KEY AUTOINCREMENT,

    function_id        integer unsigned NOT NULL,

    name               varchar(255) NOT NULL,

    type_hint          varchar(255),
    types_serialized   text NOT NULL,

    description        text,

    default_value      varchar(255),

    is_nullable        tinyint(1) NOT NULL DEFAULT 0,
    is_reference       tinyint(1) NOT NULL DEFAULT 0,
    is_optional        tinyint(1) NOT NULL DEFAULT 0,
    is_variadic        tinyint(1) NOT NULL DEFAULT 0,

    FOREIGN KEY(function_id) REFERENCES functions(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
