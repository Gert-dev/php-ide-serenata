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
    is_final                   tinyint(1) NOT NULL DEFAULT 0,
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
