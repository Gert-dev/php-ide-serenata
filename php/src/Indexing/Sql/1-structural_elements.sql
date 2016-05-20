--
CREATE TABLE settings(
    id    integer NOT NULL PRIMARY KEY AUTOINCREMENT,
    name  varchar(255) NOT NULL,
    value varchar(255) NOT NULL
);

--
CREATE TABLE files(
    id           integer NOT NULL PRIMARY KEY AUTOINCREMENT,
    path         varchar(512) NOT NULL,
    indexed_time datetime NOT NULL
);

CREATE INDEX `files_path` ON `files` (`path`);

--
CREATE TABLE structure_types(
    id   integer NOT NULL PRIMARY KEY AUTOINCREMENT,
    name varchar(255) NOT NULL
);

CREATE INDEX `structure_types_name` ON `structure_types` (`name`);

INSERT INTO structure_types (id, name) VALUES
    (NULL, 'class'),
    (NULL, 'trait'),
    (NULL, 'interface');

--
CREATE TABLE access_modifiers(
    id   integer NOT NULL PRIMARY KEY AUTOINCREMENT,
    name varchar(255) NOT NULL
);

CREATE INDEX `access_modifiers_name` ON `access_modifiers` (`name`);

INSERT INTO access_modifiers (id, name) VALUES
    (NULL, 'public'),
    (NULL, 'protected'),
    (NULL, 'private');

--
CREATE TABLE files_namespaces(
    id                         integer NOT NULL PRIMARY KEY AUTOINCREMENT,

    start_line                 integer unsigned,
    end_line                   integer unsigned,
    namespace                  varchar(255),
    file_id                    integer,

    FOREIGN KEY(file_id) REFERENCES files(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE TABLE files_namespaces_imports(
    id                         integer NOT NULL PRIMARY KEY AUTOINCREMENT,

    line                       integer unsigned,
    alias                      varchar(255) NOT NULL,
    fqcn                       varchar(255) NOT NULL,
    files_namespace_id         integer,

    FOREIGN KEY(files_namespace_id) REFERENCES files_namespaces(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

--
CREATE TABLE structures(
    id                         integer NOT NULL PRIMARY KEY AUTOINCREMENT,

    name                       varchar(255) NOT NULL,
    fqcn                       varchar(255) NOT NULL,
    file_id                    integer,
    start_line                 integer unsigned,
    end_line                   integer unsigned,

    structure_type_id          integer NOT NULL,
    short_description          text,
    long_description           text,
    is_builtin                 tinyint(1) NOT NULL DEFAULT 0,
    is_abstract                tinyint(1) NOT NULL DEFAULT 0,
    is_annotation              tinyint(1) NOT NULL DEFAULT 0,
    is_deprecated              tinyint(1) NOT NULL DEFAULT 0,
    has_docblock               tinyint(1) NOT NULL DEFAULT 0,

    FOREIGN KEY(file_id) REFERENCES files(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY(structure_type_id) REFERENCES structure_types(id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
);

CREATE INDEX `structures_fqcn` ON `structures` (`fqcn`);

-- Contains references to parent structural elements for structural elements.
CREATE TABLE structures_parents_linked(
    structure_id          integer unsigned NOT NULL,
    linked_structure_fqcn varchar(255) NOT NULL,

    PRIMARY KEY(structure_id, linked_structure_fqcn),

    FOREIGN KEY(structure_id) REFERENCES structures(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- Contains interfaces implemented by structural elements.
CREATE TABLE structures_interfaces_linked(
    structure_id          integer unsigned NOT NULL,
    linked_structure_fqcn varchar(255) NOT NULL,

    PRIMARY KEY(structure_id, linked_structure_fqcn),

    FOREIGN KEY(structure_id) REFERENCES structures(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- Contains traits used by structural elements.
CREATE TABLE structures_traits_linked(
    structure_id          integer unsigned NOT NULL,
    linked_structure_fqcn varchar(255) NOT NULL,

    PRIMARY KEY(structure_id, linked_structure_fqcn),

    FOREIGN KEY(structure_id) REFERENCES structures(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- Contains trait aliases used by structural elements.
CREATE TABLE structures_traits_aliases(
    id                         integer NOT NULL PRIMARY KEY AUTOINCREMENT,

    structure_id               integer unsigned NOT NULL,
    trait_structure_fqcn       varchar(255),
    access_modifier_id         integer unsigned,

    name                       varchar(255) NOT NULL,
    alias                      varchar(255),

    FOREIGN KEY(structure_id) REFERENCES structures(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY(access_modifier_id) REFERENCES access_modifiers(id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
);

-- Contains trait precedences used by structural elements.
CREATE TABLE structures_traits_precedences(
    id                         integer NOT NULL PRIMARY KEY AUTOINCREMENT,

    structure_id               integer unsigned NOT NULL,
    trait_structure_fqcn       varchar(255) NOT NULL,

    name                       varchar(255) NOT NULL,

    FOREIGN KEY(structure_id) REFERENCES structures(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

--
CREATE TABLE functions(
    id                      integer NOT NULL PRIMARY KEY AUTOINCREMENT,

    name                    varchar(255) NOT NULL,
    fqcn                    varchar(255),
    file_id                 integer,
    start_line              integer unsigned,
    end_line                integer unsigned,

    is_builtin              tinyint(1) NOT NULL DEFAULT 0,
    is_abstract             tinyint(1) NOT NULL DEFAULT 0,
    is_deprecated           tinyint(1) NOT NULL DEFAULT 0,

    short_description       text,
    long_description        text,
    return_description      text,

    return_type_hint        varchar(255),

    -- Specific to members.
    structure_id            integer unsigned,
    access_modifier_id      integer unsigned,

    is_magic                tinyint(1),
    is_static               tinyint(1),
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

    function_id        integer unsigned,

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

--
CREATE TABLE properties(
    id                    integer NOT NULL PRIMARY KEY AUTOINCREMENT,

    name                  varchar(255) NOT NULL,
    file_id               integer,
    start_line            integer unsigned,
    end_line              integer unsigned,

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

--
CREATE TABLE constants(
    id                    integer NOT NULL PRIMARY KEY AUTOINCREMENT,

    name                  varchar(255) NOT NULL,
    fqcn                  varchar(255),
    file_id               integer,
    start_line            integer unsigned,
    end_line              integer unsigned,

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
