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
    indexed_time timestamp NOT NULL
);

--
CREATE TABLE structural_element_types(
    id   integer NOT NULL PRIMARY KEY AUTOINCREMENT,
    name varchar(255) NOT NULL
);

INSERT INTO structural_element_types (id, name) VALUES
    (NULL, 'class'),
    (NULL, 'trait'),
    (NULL, 'interface');

--
CREATE TABLE access_modifiers(
    id   integer NOT NULL PRIMARY KEY AUTOINCREMENT,
    name varchar(255) NOT NULL
);

INSERT INTO access_modifiers (id, name) VALUES
    (NULL, 'public'),
    (NULL, 'protected'),
    (NULL, 'private');

--
CREATE TABLE structural_elements(
    id                         integer NOT NULL PRIMARY KEY AUTOINCREMENT,

    name                       varchar(255) NOT NULL,
    fqsen                      varchar(255) NOT NULL,
    file_id                    integer,
    start_line                 integer unsigned,

    structural_element_type_id integer NOT NULL,
    short_description          text,
    long_description           text,
    is_builtin                 tinyint(1) NOT NULL DEFAULT 0,
    is_abstract                tinyint(1) NOT NULL DEFAULT 0,
    is_deprecated              tinyint(1) NOT NULL DEFAULT 0,
    has_docblock               tinyint(1) NOT NULL DEFAULT 0,

    FOREIGN KEY(file_id) REFERENCES files(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY(structural_element_type_id) REFERENCES structural_element_types(id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
);

-- Contains references to parent structural elements for structural elements.
CREATE TABLE structural_elements_parents_linked(
    structural_element_id        integer unsigned NOT NULL,
    linked_structural_element_id integer unsigned NOT NULL,

    PRIMARY KEY(structural_element_id, linked_structural_element_id),

    FOREIGN KEY(structural_element_id) REFERENCES structural_elements(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY(linked_structural_element_id) REFERENCES structural_elements(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- Contains interfaces implemented by structural elements.
CREATE TABLE structural_elements_interfaces_linked(
    structural_element_id        integer unsigned NOT NULL,
    linked_structural_element_id integer unsigned NOT NULL,

    PRIMARY KEY(structural_element_id, linked_structural_element_id),

    FOREIGN KEY(structural_element_id) REFERENCES structural_elements(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY(linked_structural_element_id) REFERENCES structural_elements(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- Contains traits used by structural elements.
CREATE TABLE structural_elements_traits_linked(
    structural_element_id        integer unsigned NOT NULL,
    linked_structural_element_id integer unsigned NOT NULL,

    PRIMARY KEY(structural_element_id, linked_structural_element_id),

    FOREIGN KEY(structural_element_id) REFERENCES structural_elements(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY(linked_structural_element_id) REFERENCES structural_elements(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- Contains trait aliases used by structural elements.
CREATE TABLE structural_elements_traits_aliases(
    id                         integer NOT NULL PRIMARY KEY AUTOINCREMENT,

    structural_element_id       integer unsigned NOT NULL,
    access_modifier_id          integer unsigned,

    name                        varchar(255) NOT NULL,
    alias                       varchar(255) NOT NULL,

    FOREIGN KEY(structural_element_id) REFERENCES structural_elements(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY(access_modifier_id) REFERENCES access_modifiers(id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
);

-- Contains trait precedences used by structural elements.
CREATE TABLE structural_elements_traits_precedences(
    id                         integer NOT NULL PRIMARY KEY AUTOINCREMENT,

    structural_element_id       integer unsigned NOT NULL,
    trait_structural_element_id integer unsigned NOT NULL,

    name                        varchar(255) NOT NULL,

    FOREIGN KEY(structural_element_id) REFERENCES structural_elements(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY(trait_structural_element_id) REFERENCES structural_elements(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

--
CREATE TABLE functions(
    id                    integer NOT NULL PRIMARY KEY AUTOINCREMENT,

    name                  varchar(255) NOT NULL,
    file_id               integer,
    start_line            integer unsigned,

    is_builtin            tinyint(1) NOT NULL DEFAULT 0,
    is_deprecated         tinyint(1) NOT NULL DEFAULT 0,

    short_description     text,
    long_description      text,

    return_type           varchar(255),
    return_description    text,

    -- Specific to members.
    structural_element_id integer unsigned,
    access_modifier_id    integer unsigned,

    is_magic              tinyint(1),
    is_static             tinyint(1),
    has_docblock          tinyint(1) NOT NULL DEFAULT 0,

    FOREIGN KEY(file_id) REFERENCES files(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY(structural_element_id) REFERENCES structural_elements(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY(access_modifier_id) REFERENCES access_modifiers(id)
        ON DELETE RESTRICT
        ON UPDATE RESTRICT
);

-- Contains parameters for functions and methods.
CREATE TABLE functions_parameters(
    id           integer NOT NULL PRIMARY KEY AUTOINCREMENT,

    function_id  integer unsigned,

    name         varchar(255) NOT NULL,
    type         varchar(255),

    description  text,

    is_reference tinyint(1) NOT NULL DEFAULT 0,
    is_optional  tinyint(1) NOT NULL DEFAULT 0,
    is_variadic  tinyint(1) NOT NULL DEFAULT 0,

    FOREIGN KEY(function_id) REFERENCES functions(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

-- Contains a list of exceptions that can be thrown (specified through the docblock) for a function or method.
CREATE TABLE functions_throws(
    id          integer NOT NULL PRIMARY KEY AUTOINCREMENT,

    function_id integer unsigned,

    type        varchar(255) NOT NULL,
    description text,

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

    is_deprecated         tinyint(1) NOT NULL DEFAULT 0,

    short_description     text,
    long_description      text,

    return_type           varchar(255),
    return_description    text,

    structural_element_id integer unsigned NOT NULL,
    access_modifier_id    integer unsigned NOT NULL,

    is_magic              tinyint(1) NOT NULL DEFAULT 0,
    is_static             tinyint(1) NOT NULL DEFAULT 0,
    has_docblock          tinyint(1) NOT NULL DEFAULT 0,

    FOREIGN KEY(file_id) REFERENCES files(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY(structural_element_id) REFERENCES structural_elements(id)
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
    file_id               integer,
    start_line            integer unsigned,

    is_builtin            tinyint(1) NOT NULL DEFAULT 0,
    is_deprecated         tinyint(1) NOT NULL DEFAULT 0,
    has_docblock          tinyint(1) NOT NULL DEFAULT 0,

    short_description     text,
    long_description      text,

    return_type           varchar(255),
    return_description    text,

    -- Specific to member constants.
    structural_element_id integer unsigned,

    FOREIGN KEY(file_id) REFERENCES files(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    FOREIGN KEY(structural_element_id) REFERENCES structural_elements(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
