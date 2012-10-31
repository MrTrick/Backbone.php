DROP TABLE IF EXISTS people;
CREATE TABLE people ( 
    username TEXT PRIMARY KEY NOT NULL, 
    password TEXT NOT NULL, 
    first_name TEXT NOT NULL, 
    surname TEXT NOT NULL, 
    email TEXT NOT NULL
);

INSERT INTO people (username, password, first_name, surname, email) VALUES ('test1', 'test', 'Alice', 'Cooper', 'alice@example.com');
INSERT INTO people (username, password, first_name, surname, email) VALUES ('test2', 'test', 'Bob', 'Barker', 'bob@example.com');
INSERT INTO people (username, password, first_name, surname, email) VALUES ('test3', 'test', 'Chuck', 'Yaeger', 'chuck@example.com');
INSERT INTO people (username, password, first_name, surname, email) VALUES ('test4', 'test', 'Dudley', 'Do-right', 'dudley@example.com');