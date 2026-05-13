CREATE DATABASE umu_vote;
drop DATABASE umu_vote;
USE umu_vote;
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    google_id VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    has_voted TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE contestants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    gender ENUM('male','female') NOT NULL,
    photo VARCHAR(255) NOT NULL,
    bio TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    gender ENUM('male','female') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    contestant_id INT NOT NULL,
    category_id INT NOT NULL,
    score TINYINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_vote (user_id, contestant_id, category_id),
    CONSTRAINT fk_votes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_votes_contestant FOREIGN KEY (contestant_id) REFERENCES contestants(id) ON DELETE CASCADE,
    CONSTRAINT fk_votes_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- delete extra categories beyond your top 10 per gender
DELETE FROM categories
WHERE id NOT IN (
  SELECT id FROM (
    SELECT id FROM categories WHERE gender='male' ORDER BY id LIMIT 10
  ) m
  UNION ALL
  SELECT id FROM (
    SELECT id FROM categories WHERE gender='female' ORDER BY id LIMIT 10
  ) f
);
TRUNCATE TABLE categories;
INSERT INTO categories (name, gender) VALUES
('Smartest', 'male'),
('Most Approachable', 'male'),
('Most Stylish', 'male'),
('Most Influential', 'male'),
('Most Creative', 'male'),
('Most Social', 'male'),
('Best Smile', 'male'),
('Most Entertaining', 'male'),
('Smart (Dress Code)', 'male'),
('Brains (Outside the Box)', 'male'),
('Talent', 'male'),
('Confidence', 'male'),
('Self Awareness', 'male'),
('Smartest', 'female'),
('Most Approachable', 'female'),
('Most Stylish', 'female'),
('Most Influential', 'female'),
('Most Creative', 'female'),
('Most Social', 'female'),
('Best Smile', 'female'),
('Most Entertaining', 'female'),
('Smart (Dress Code)', 'female'),
('Brains (Outside the Box)', 'female'),
('Talent', 'female'),
('Confidence', 'female'),
('Self Awareness', 'female');

UPDATE users SET has_voted = 0 WHERE id = 1;
DELETE FROM votes WHERE user_id = 1;