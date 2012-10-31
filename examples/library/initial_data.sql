DROP TABLE authors;
DROP TABLE books;
CREATE TABLE authors ( author TEXT PRIMARY KEY );
CREATE TABLE IF NOT EXISTS books ( id INTEGER PRIMARY KEY AUTOINCREMENT, title NOT NULL, author TEXT, length INTEGER );

INSERT INTO authors (author) VALUES ("Dr Seuss"); 
INSERT INTO authors (author) VALUES ("Leo Tolstoy");
INSERT INTO authors (author) VALUES ("Douglas Adams");

INSERT INTO books (title, author, length) VALUES ("The Cat In The Hat", "Dr Seuss", 123);
INSERT INTO books (title, author, length) VALUES ("The Cat In The Hat Comes Back", "Dr Seuss", 132);
INSERT INTO books (title, author, length) VALUES ("War and Peace", "Leo Tolstoy", 128937818);
INSERT INTO books (title, author, length) VALUES ("Hitchhiker's Guide to the Galaxy", "Douglas Adams", 42);

