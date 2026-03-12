-- Use IT490 database we made in database_setup.sql
USE it490_db;

-- Cache table for movies fetched from DMZ alr
-- Stores movies we fetch from the DMZ so we don't have to make excessive API calls (meets deliverable)
CREATE TABLE IF NOT EXISTS movies (
    movie_id INT PRIMARY KEY,           --  official movie ID
    title VARCHAR(255) NOT NULL,
    release_date DATE,
    poster_path VARCHAR(255),          -- URL for movie poster image
    overview TEXT,                      -- plot summary
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP -- tracks when last updated from API
);

-- Library table for user's movie collection
-- Links user to movie and tracks if they own it or have seen it
CREATE TABLE IF NOT EXISTS user_library (
    id INT AUTO_INCREMENT PRIMARY KEY, -- unique id for each library entry
    user_id INT NOT NULL, -- links user to movie
    movie_id INT NOT NULL, -- links user to movie
    has_seen BOOLEAN DEFAULT FALSE, -- tracks if user has seen the movie
    is_owned BOOLEAN DEFAULT FALSE, -- tracks if user owns the movie
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, -- if user deleted, so are their library entries
    FOREIGN KEY (movie_id) REFERENCES movies(movie_id) ON DELETE CASCADE, -- if movie deleted, so are library entries
    UNIQUE(user_id, movie_id)           -- Prevents adding the same movie twice
);

-- Watchlist table for movies user wants to watch later
CREATE TABLE IF NOT EXISTS user_watchlist (
    id INT AUTO_INCREMENT PRIMARY KEY, -- unique id for each watchlist entry
    user_id INT NOT NULL, -- links user to movie
    movie_id INT NOT NULL, -- links user to movie
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- tracks when movie added to watchlist
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, -- if user deleted, so are their watchlist entries
    FOREIGN KEY (movie_id) REFERENCES movies(movie_id) ON DELETE CASCADE, -- if movie deleted, so are watchlist entries
    UNIQUE(user_id, movie_id)  -- prevents adding the same movie twice
);

-- Reviews table for user's movie reviews
-- Stores 1-5 star rating and text review
CREATE TABLE IF NOT EXISTS user_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY, -- unique id for each review
    user_id INT NOT NULL, -- links user to movie
    movie_id INT NOT NULL, -- links user to movie
    rating INT, -- 1-5 star rating
    review_text TEXT, -- text review
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- tracks when review created
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, -- if user deleted, so are their reviews
    FOREIGN KEY (movie_id) REFERENCES movies(movie_id) ON DELETE CASCADE, -- if movie deleted, so are reviews
    UNIQUE(user_id, movie_id)           -- prevents adding the same review twice
    CONSTRAINT chck_rating CHECK (rating >= 1 AND rating <= 5)
);

-- Alerts table for push notifications for new movies on watchlist
-- Stores notifications for user (meets deliverable)
CREATE TABLE IF NOT EXISTS user_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY, -- unique id for each alert
    user_id INT NOT NULL, -- links user to alert
    message VARCHAR(255) NOT NULL, -- message for alert
    is_read BOOLEAN DEFAULT FALSE, -- tracks if alert has been read
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- tracks when alert created
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE -- if user deleted, so are their alerts
);
