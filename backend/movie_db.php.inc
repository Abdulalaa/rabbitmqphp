<?php
// Cache, library, watchlist functions to interact w/ mysql

class MovieDB
{
    private $db;

    // Connect to the database exactly like login.php.inc 
    public function __construct()
    {
        $this->db = new mysqli("127.0.0.1", "backendVM", "password", "it490_db");

        if ($this->db->connect_errno != 0) {
            echo "Error connecting to database: " . $this->db->connect_error . PHP_EOL;
            exit(1);
        }
    }

    // Check movie cache for saving on API rate
    
    // 1. Check if user alr searched for this movie
    public function checkCache($movie_id)
    {
        $safe_id = $this->db->real_escape_string($movie_id);
        $query = "SELECT * FROM movies WHERE movie_id = '$safe_id'";
        $result = $this->db->query($query);

        if ($result->num_rows > 0) {
            echo "DB: Movie $movie_id found in local cache\n";
            return $result->fetch_assoc();
        }
        echo "DB: Movie $movie_id NOT in cache. Must ask DMZ.\n";
        return false;
    }

    // Function to cache movies 
    public function cacheMovie($movieData)
    {
        // Extract and sanitize movie
        $id = $this->db->real_escape_string($movieData['id']);
        $title = $this->db->real_escape_string($movieData['title']);
        $release = $this->db->real_escape_string($movieData['release_date']);
        $poster = $this->db->real_escape_string($movieData['poster_path']);
        $overview = $this->db->real_escape_string($movieData['overview']);

        // Insert OR Update if it alr exists using ON DUPLICATE KEY UPDATE
        $query = "INSERT INTO movies (movie_id, title, release_date, poster_path, overview) 
                  VALUES ('$id', '$title', '$release', '$poster', '$overview')
                  ON DUPLICATE KEY UPDATE title='$title', overview='$overview'";

        if ($this->db->query($query)) {
            echo "DB: Successfully cached movie: $title\n";
            return true;
        } else {
            echo "DB: Failed to cache movie: " . $this->db->error . "\n";
            return false;
        }
    }

    // Functions for library and watchlist

    // Add to watchlist 
    public function addToWatchlist($username, $movie_id)
    {
        // Find user ID through un
        $user_query = "SELECT id FROM users WHERE username = '" . $this->db->real_escape_string($username) . "'";
        $user_result = $this->db->query($user_query);
        
        if ($user_result->num_rows == 0) return array("status" => "error", "message" => "User not found");
        $user_row = $user_result->fetch_assoc();
        $user_id = $user_row['id'];

        // Insert into watchlist sql table
        $query = "INSERT IGNORE INTO user_watchlist (user_id, movie_id) VALUES ('$user_id', '$movie_id')";
        
        if ($this->db->query($query)) {
            return array("status" => "success", "message" => "Added to Watchlist!");
        }
        return array("status" => "error", "message" => "Failed to add to watchlist");
    }

    // Add movie to library
    public function addToLibrary($username, $movie_id, $has_seen, $is_owned)
    {
        // Get User ID
        $user_query = "SELECT id FROM users WHERE username = '" . $this->db->real_escape_string($username) . "'";
        $user_row = $this->db->query($user_query)->fetch_assoc();
        $user_id = $user_row['id'];

        // Convert boolean to tinyint for SQL (1 or 0)
        $seen = $has_seen ? 1 : 0;
        $owned = $is_owned ? 1 : 0;

        // Insert into library or update in case they own it
        $query = "INSERT INTO user_library (user_id, movie_id, has_seen, is_owned) 
                  VALUES ('$user_id', '$movie_id', '$seen', '$owned')
                  ON DUPLICATE KEY UPDATE has_seen='$seen', is_owned='$owned'";
        
        if ($this->db->query($query)) {
            return array("status" => "success", "message" => "Library updated!");
        }
        return array("status" => "error", "message" => "Failed to update library");
    }

    // Ratings and reviews (text + 1-5 stars)
    public function addReview($username, $movie_id, $rating, $review_text)
    {
        // Get user ID from un
        $user_query = "SELECT id FROM users WHERE username = '" . $this->db->real_escape_string($username) . "'";
        $user_result = $this->db->query($user_query);
        
        if ($user_result->num_rows == 0) return array("status" => "error", "message" => "User not found");
        $user_id = $user_result->fetch_assoc()['id'];

        // Sanitize the input
        $safe_review = $this->db->real_escape_string($review_text);
        
        // Verify 1-5
        $safe_rating = max(1, min(5, (int)$rating));

        // Insert or update existing review
        $query = "INSERT INTO user_reviews (user_id, movie_id, rating, review_text) 
                  VALUES ('$user_id', '$movie_id', '$safe_rating', '$safe_review')
                  ON DUPLICATE KEY UPDATE rating='$safe_rating', review_text='$safe_review'";
        
        if ($this->db->query($query)) {
            return array("status" => "success", "message" => "Review successfully saved");
        }
        return array("status" => "error", "message" => "Failed to save review: " . $this->db->error);
    }

    // Recommendations
    // Logic of our recs: Check what genre/movies user has been logging and rating highly
    // Find highest rated movies in our local cache that this user hasn't already seen or added to watchlist

    public function getRecommendations($username)
    {
        // Get user ID from un
        $user_query = "SELECT id FROM users WHERE username = '" . $this->db->real_escape_string($username) . "'";
        $user_result = $this->db->query($user_query);
        
        if ($user_result->num_rows == 0) return array();
        $user_id = $user_result->fetch_assoc()['id'];

        // Check cache for highly rated movies based on average user rating, exclude movies seen by user alr
        $query = "
            SELECT m.movie_id, m.title, m.poster_path, m.release_date, AVG(r.rating) as avg_rating
            FROM movies m
            LEFT JOIN user_reviews r ON m.movie_id = r.movie_id
            WHERE m.movie_id NOT IN (SELECT movie_id FROM user_library WHERE user_id = '$user_id')
              AND m.movie_id NOT IN (SELECT movie_id FROM user_watchlist WHERE user_id = '$user_id')
            GROUP BY m.movie_id
            ORDER BY avg_rating DESC, m.release_date DESC
            LIMIT 5
        ";

        $result = $this->db->query($query);
        $recommendations = array();

        while ($row = $result->fetch_assoc()) {
            $recommendations[] = $row;
        }

        return $recommendations;
    }
}
?>