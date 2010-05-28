-- Create the database for our example
CREATE TABLE post (
	id int(10) NOT NULL auto_increment,
	message varchar(255) NOT NULL,
	posted timestamp default current_timestamp,
	PRIMARY KEY(id)
);