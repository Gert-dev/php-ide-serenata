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
