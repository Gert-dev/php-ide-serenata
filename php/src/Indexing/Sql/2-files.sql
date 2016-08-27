CREATE TABLE files(
    id           integer NOT NULL PRIMARY KEY AUTOINCREMENT,
    path         varchar(512) NOT NULL,
    indexed_time datetime NOT NULL
);

CREATE INDEX `files_path` ON `files` (`path`);
